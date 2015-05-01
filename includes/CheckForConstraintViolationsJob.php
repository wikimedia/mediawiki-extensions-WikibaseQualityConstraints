<?php

namespace WikidataQuality\ConstraintReport;

use Job;
use Title;
use Wikibase\DataModel\Entity\Entity;
use Wikibase\Repo\WikibaseRepo;
use WikidataQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikidataQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;


class CheckForConstraintViolationsJob extends Job {

	public static function newInsertNow( Entity $entity, $checkTimestamp, $results ) {
		// The Job class wants a Title object for some reason. Supply a dummy.
		$dummyTitle = Title::newFromText( "CheckForConstraintViolationsJob", NS_SPECIAL );

		$params = array ();

		$params['entity'] = $entity;
		$params['results'] = $results;
		$params['checkTimestamp'] = $checkTimestamp;
		$params['referenceTimestamp'] = null;

		return new CheckForConstraintViolationsJob( $dummyTitle, $params );
	}

	public static function newInsertDeferred( Entity $entity, $referenceTimestamp = null, $releaseTimestamp = 0 ) {
		// The Job class wants a Title object for some reason. Supply a dummy.
		$dummyTitle = Title::newFromText( "CheckForConstraintViolationsJob", NS_SPECIAL );

		$params = array ();

		$params['entity'] = $entity;
		$params['results'] = null;
		$params['referenceTimestamp'] = $referenceTimestamp;
		$params['releaseTimestamp'] = wfTimestamp( TS_MW ) + $releaseTimestamp;

		return new CheckForConstraintViolationsJob( $dummyTitle, $params );
	}

	public function __construct( Title $title, $params ) {
		parent::__construct( 'checkForConstraintViolations', $title, $params );
	}

	public function run() {
		$checkTimestamp = array_key_exists( 'checkTimestamp', $this->params ) ? $this->params['checkTimestamp'] : wfTimestamp( TS_MW );

		if ( $this->params['results'] === null ) {
			$constraintChecker = new ConstraintChecker( WikibaseRepo::getDefaultInstance()->getEntityLookup() );
			$results = $constraintChecker->execute( $this->params['entity'] );
		} else {
			$results = $this->params['results'];
		}

		$accumulator = array (
			'special_page_id' => 'SpecialConstraintReport',
			'entity_id' => $this->params['entity']->getId()->getSerialization(),
			'insertion_timestamp' => $checkTimestamp,
			'reference_timestamp' => $this->params['referenceTimestamp'],
			'result_summary' => $this->buildResultSummary( $results )
		);

		wfDebugLog( 'wdqa_evaluation', json_encode( $accumulator ) );
	}

	private function buildResultSummary( $results ) {
		$summary = array();

		foreach ( $results as $result ) {
			$constraintName = $result->getConstraintName();
			$status = $result->getStatus();
			if( !array_key_exists( $constraintName, $summary ) ) {
				$summary[$constraintName] = array(
					CheckResult::STATUS_COMPLIANCE => 0,
					CheckResult::STATUS_VIOLATION => 0,
					CheckResult::STATUS_EXCEPTION => 0
				);
			}
			if( array_key_exists( $status, $summary[$constraintName] ) ) {
				$summary[$constraintName][$status]++;
			}
		}

		return $summary;
	}

}