<?php

namespace WikibaseQuality\ConstraintReport;

use Job;
use Title;

class EvaluateConstraintReportJob extends Job {

	private $service;

	/**
	 * @param string $entityId
	 * @param int $checkTimestamp
	 * @param string $results
	 *
	 * @return EvaluateConstraintReportJob
	 * @throws \MWException
	 */
	public static function newInsertNow( $entityId, $checkTimestamp, $results ) {
		// The Job class wants a Title object for some reason. Supply a dummy.
		$dummyTitle = Title::newFromText( "EvaluateConstraintReportJob", NS_SPECIAL );

		$params = array ();

		$params['entityId'] = $entityId;
		$params['results'] = $results;
		$params['checkTimestamp'] = $checkTimestamp;
		$params['referenceTimestamp'] = null;

		return new EvaluateConstraintReportJob( $dummyTitle, $params );
	}

	/**
	 * @param string $entityId
	 * @param int $referenceTimestamp
	 * @param int $delay
	 *
	 * @return EvaluateConstraintReportJob
	 * @throws \MWException
	 */
	public static function newInsertDeferred( $entityId, $referenceTimestamp = 'null', $delay = 0 ) {
		// The Job class wants a Title object for some reason. Supply a dummy.
		$dummyTitle = Title::newFromText( "EvaluateConstraintReportJob", NS_SPECIAL );

		$params = array ();

		$params['entityId'] = $entityId;
		$params['referenceTimestamp'] = $referenceTimestamp;
		$params['jobReleaseTimestamp'] = wfTimestamp( TS_UNIX ) + $delay;

		return new EvaluateConstraintReportJob( $dummyTitle, $params );
	}

	/**
	 * @param Title $title
	 * @param array|bool $params
	 */
	public function __construct( Title $title, $params ) {
		parent::__construct( 'evaluateConstraintReportJob', $title, $params );
		$this->service = new EvaluateConstraintReportJobService();
	}

	public function run() {
		$checkTimestamp = array_key_exists( 'checkTimestamp', $this->params ) ? $this->params[ 'checkTimestamp' ] : wfTimestamp( TS_UNIX );

		$resultSummary = $this->service->getResults( $this->params );
		$messageToLog = $this->service->buildMessageForLog( $resultSummary, $checkTimestamp, $this->params );
		$this->service->writeToLog( $messageToLog );

	}

}