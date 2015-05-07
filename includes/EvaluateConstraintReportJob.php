<?php

namespace WikidataQuality\ConstraintReport;

use Job;
use Title;
use Wikibase\DataModel\Entity\Entity;

class EvaluateConstraintReportJob extends Job {

	private $service;

	/**
	 * @param EvaluateConstraintReportJobService $service
	 * @param Entity $entity
	 * @param $checkTimestamp
	 * @param $results
	 *
	 * @return EvaluateConstraintReportJob
	 * @throws \MWException
	 */
	public static function newInsertNow( EvaluateConstraintReportJobService $service, Entity $entity, $checkTimestamp, $results ) {
		// The Job class wants a Title object for some reason. Supply a dummy.
		$dummyTitle = Title::newFromText( "EvaluateConstraintReportJob", NS_SPECIAL );

		$params = array ();

		$params['entity'] = $entity;
		$params['results'] = $results;
		$params['checkTimestamp'] = $checkTimestamp;
		$params['referenceTimestamp'] = null;

		return new EvaluateConstraintReportJob( $service, $dummyTitle, $params );
	}

	/**
	 * @param EvaluateConstraintReportJobService $service
	 * @param Entity $entity
	 * @param null $referenceTimestamp
	 * @param int $releaseTimestamp
	 *
	 * @return EvaluateConstraintReportJob
	 * @throws \MWException
	 */
	public static function newInsertDeferred( EvaluateConstraintReportJobService $service, Entity $entity, $referenceTimestamp = null, $releaseTimestamp = 0 ) {
		// The Job class wants a Title object for some reason. Supply a dummy.
		$dummyTitle = Title::newFromText( "EvaluateConstraintReportJob", NS_SPECIAL );

		$params = array ();

		$params['entity'] = $entity;
		$params['results'] = null;
		$params['referenceTimestamp'] = $referenceTimestamp;
		$params['releaseTimestamp'] = wfTimestamp( TS_MW ) + $releaseTimestamp;

		return new EvaluateConstraintReportJob( $service, $dummyTitle, $params );
	}

	/**
	 * @param EvaluateConstraintReportJobService $service
	 * @param Title $title
	 * @param array|bool $params
	 */
	public function __construct( EvaluateConstraintReportJobService $service, Title $title, $params ) {
		parent::__construct( 'checkForConstraintViolations', $title, $params );
		$this->service = $service;
	}

	public function run() {
		$checkTimestamp = array_key_exists( 'checkTimestamp', $this->params ) ? $this->params[ 'checkTimestamp' ] : wfTimestamp( TS_MW );

		$results = $this->service->getResults( $this->params );
		$messageToLog = $this->service->buildMessageForLog( $results, $checkTimestamp, $this->params );
		$this->service->writeToLog( $messageToLog );

	}

}