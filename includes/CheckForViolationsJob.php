<?php

namespace WikidataQuality\ConstraintReport;

use Job;
use Title;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\Repo\WikibaseRepo;
use WikidataQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;


class CheckForViolationsJob extends Job {

	private $db;

	public static function newInsertNow(
		$specialPage,
		EntityDocument $entity,
		$checkTimestamp,
		$results ) {
		// The Job class wants a Title object for some reason. Supply a dummy.
		$dummyTitle = Title::newFromText( "CheckForViolationsJob", NS_SPECIAL );

		$params = array ();

		$params[ 'specialPage' ] = $specialPage;
		$params[ 'entity' ] = $entity;
		$params[ 'results' ] = $results;
		$params[ 'checkTimestamp' ] = $checkTimestamp;
		$params[ 'referenceTimestamp' ] = null;

		return new CheckForViolationsJob( $dummyTitle, $params );
	}

	public static function newInsertDeferred(
		$specialPage,
		EntityDocument $entity,
		$referenceTimestamp = null,
		$releaseTimestamp = 0 ) {
		// The Job class wants a Title object for some reason. Supply a dummy.
		$dummyTitle = Title::newFromText( "CheckForViolationsJob", NS_SPECIAL );

		$params = array ();

		$params[ 'specialPage' ] = $specialPage;
		$params[ 'entity' ] = $entity;
		$params[ 'results' ] = null;
		$params[ 'referenceTimestamp' ] = $referenceTimestamp;
		$params[ 'releaseTimestamp' ] = wfTimestamp( TS_MW ) + $releaseTimestamp;

		return new CheckForViolationsJob( $dummyTitle, $params );
	}

	public function __construct( Title $title, $params ) {
		parent::__construct( 'CheckForViolations', $title, $params );
		wfWaitForSlaves();
		$loadBalancer = wfGetLB();
		$this->db = $loadBalancer->getConnection( DB_MASTER );
	}

	public function run() {
		$checkTimestamp = array_key_exists( 'checkTimestamp', $this->params ) ? $this->params[ 'checkTimestamp' ] : wfTimestamp( TS_MW );

		if ( $this->params[ 'results' ] === null ) {
			$constraintChecker = new ConstraintChecker( WikibaseRepo::getDefaultInstance()->getEntityLookup() );
			$results = $constraintChecker->execute( $this->params[ 'entity' ] );
		} else {
			$results = $this->params[ 'results' ];
		}

		$accumulator = array (
			'special_page_id' => $this->params[ 'specialPage' ],
			'entity_id' => $this->params[ 'entity' ]->getId()->getSerialization(),
			'insertion_timestamp' => $checkTimestamp,
			'reference_timestamp' => $this->params[ 'referenceTimestamp' ],
			'result_string' => $this->getResultSerialization( $results )
		);
		$success = $this->db->insert( EVALUATION_TABLE, $accumulator );

		return $success;
	}

	private function getResultSerialization( $results ) {
		$serialization = '';
		$compliances = $violations = $exceptions = array ();
		foreach ( $results as $result ) {
			switch ( $result->getStatus() ) {
				case 'compliance':
					if ( array_key_exists( $result->getConstraintName(), $compliances ) ) {
						$compliances[ $result->getConstraintName() ] += 1;
					} else {
						$compliances[ $result->getConstraintName() ] = 1;
					}
					break;
				case 'violation':
					if ( array_key_exists( $result->getConstraintName(), $violations ) ) {
						$violations[ $result->getConstraintName() ] += 1;
					} else {
						$violations[ $result->getConstraintName() ] = 1;
					}
					break;
				case 'exception':
					if ( array_key_exists( $result->getConstraintName(), $exceptions ) ) {
						$exceptions[ $result->getConstraintName() ] += 1;
					} else {
						$exceptions[ $result->getConstraintName() ] = 1;
					}
			}
		}

		$serialization .= '{Compliances: {';
		foreach ( array_keys( $compliances ) as $key ) {
			$serialization .= $key . ': ' . $compliances[ $key ] . ', ';
		}
		$serialization .= '}, ';

		$serialization .= '{Violations: {';
		foreach ( array_keys( $violations ) as $key ) {
			$serialization .= $key . ': ' . $violations[ $key ] . ', ';
		}
		$serialization .= '}, ';

		$serialization .= '{Exceptions: {';
		foreach ( array_keys( $compliances ) as $key ) {
			$serialization .= $key . ': ' . $compliances[ $key ] . ', ';
		}
		$serialization .= '}, ';

		return $serialization;
	}

}