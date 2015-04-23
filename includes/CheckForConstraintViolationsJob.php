<?php

namespace WikidataQuality\ConstraintReport;

use Job;
use Title;
use Wikibase\DataModel\Entity\Entity;
use Wikibase\Repo\WikibaseRepo;
use WikidataQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikidataQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;


class CheckForConstraintViolationsJob extends Job {

	public static function newInsertNow(
		Entity $entity,
		$checkTimestamp,
		$results ) {
		// The Job class wants a Title object for some reason. Supply a dummy.
		$dummyTitle = Title::newFromText( "CheckForConstraintViolationsJob", NS_SPECIAL );

		$params = array ();

		$params[ 'entity' ] = $entity;
		$params[ 'results' ] = $results;
		$params[ 'checkTimestamp' ] = $checkTimestamp;
		$params[ 'referenceTimestamp' ] = null;

		return new CheckForConstraintViolationsJob( $dummyTitle, $params );
	}

	public static function newInsertDeferred(
		Entity $entity,
		$referenceTimestamp = null,
		$releaseTimestamp = 0 ) {
		// The Job class wants a Title object for some reason. Supply a dummy.
		$dummyTitle = Title::newFromText( "CheckForConstraintViolationsJob", NS_SPECIAL );

		$params = array ();

		$params[ 'entity' ] = $entity;
		$params[ 'results' ] = null;
		$params[ 'referenceTimestamp' ] = $referenceTimestamp;
		$params[ 'releaseTimestamp' ] = wfTimestamp( TS_MW ) + $releaseTimestamp;

		return new CheckForConstraintViolationsJob( $dummyTitle, $params );
	}

	public function __construct( Title $title, $params ) {
		parent::__construct( 'checkForConstraintViolations', $title, $params );
	}

	public function run() {
		wfWaitForSlaves();
		$loadBalancer = wfGetLB();
		$db = $loadBalancer->getConnection( DB_MASTER );

		$checkTimestamp = array_key_exists( 'checkTimestamp', $this->params ) ? $this->params[ 'checkTimestamp' ] : wfTimestamp( TS_MW );

		if ( $this->params[ 'results' ] === null ) {
			$constraintChecker = new ConstraintChecker( WikibaseRepo::getDefaultInstance()->getEntityLookup() );
			$results = $constraintChecker->execute( $this->params[ 'entity' ] );
		} else {
			$results = $this->params[ 'results' ];
		}

		$accumulator = array (
			'special_page_id' => 1,
			'entity_id' => $this->params[ 'entity' ]->getId()->getSerialization(),
			'insertion_timestamp' => $checkTimestamp,
			'reference_timestamp' => $this->params[ 'referenceTimestamp' ],
			'result_string' => $this->getResultSerialization( $results )
		);
		$success = $db->insert( EVALUATION_TABLE, $accumulator );

		return $success;
	}

	private function getResultSerialization( $results ) {
		$serialization = '';
		$compliances = $violations = $exceptions = $constraints = array ();
		foreach ( $results as $result ) {
			$constraintName = $result->getConstraintName();
			if( !array_key_exists( $constraintName, $constraints ) ) {
				$constraints[ $constraintName ] = true;
			}
			switch ( $result->getStatus() ) {
				case CheckResult::STATUS_COMPLIANCE:
					if ( array_key_exists( $constraintName, $compliances ) ) {
						$compliances[ $constraintName ] += 1;
					} else {
						$compliances[ $constraintName ] = 1;
					}
					break;
				case CheckResult::STATUS_VIOLATION:
					if ( array_key_exists( $constraintName, $violations ) ) {
						$violations[ $constraintName ] += 1;
					} else {
						$violations[ $constraintName ] = 1;
					}
					break;
				case CheckResult::STATUS_EXCEPTION:
					if ( array_key_exists( $constraintName, $exceptions ) ) {
						$exceptions[ $constraintName ] += 1;
					} else {
						$exceptions[ $constraintName ] = 1;
					}
			}
		}

		foreach ( array_keys( $constraints ) as $constraint ) {
			$serialization .= $constraint . ': {Compliances: ';
			if( array_key_exists( $constraint, $compliances ) ){
				$serialization .= $compliances[ $constraint ] . ', ';
			} else {
				$serialization .= 0 . ', ';
			}

			$serialization .= 'Exceptions: ';
			if( array_key_exists( $constraint, $exceptions ) ){
				$serialization .= $exceptions[ $constraint ] . ', ';
			} else {
				$serialization .= 0 . ', ';
			}

			$serialization .= 'Violations: ';
			if( array_key_exists( $constraint, $violations ) ){
				$serialization .= $violations[ $constraint ] . '}, ';
			} else {
				$serialization .= 0 . '}, ';
			}
		}

		return substr( $serialization, 0, -2 );
	}

}