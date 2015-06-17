<?php

namespace WikibaseQuality\ConstraintReport;

use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\Repo\WikibaseRepo;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;


/**
 * Class EvaluateConstraintReportJobService
 *
 * @package WikibaseQuality\ConstraintReport
 *
 * Service that gets used be EvaluateConstraintReportJob
 * to build a summary to be logged from the results
 * (or, in case of a deferred job, gets the results from
 * the ConstraintChecker first)
 * and finally writes it to wfDebugLog
 */
class EvaluateConstraintReportJobService {

	public function writeToLog( $message ) {
		wfDebugLog( 'wbq_evaluation', $message );
	}

	/**
	 * @param string $resultSummary (json representation of checkResults)
	 * @param int $timestamp (TS_UNIX)
	 * @param array $params
	 *
	 * @return string
	 */
	public function buildMessageForLog( $resultSummary, $timestamp, $params ) {
		return json_encode(
			array (
				'special_page_id' => 'SpecialConstraintReport',
				'entity_id' => $params['entityId'],
				'insertion_timestamp' => $timestamp,
				'reference_timestamp' => $params['referenceTimestamp'],
				'result_summary' => $resultSummary
			)
		);
	}

	/**
	 * @param $results
	 *
	 * @return string
	 */
	public function buildResultSummary( $results ) {
		$summary = array();

		foreach ( $results as $result ) {
			$constraintName = $result->getConstraintName();
			$status = $result->getStatus();
			if( !array_key_exists( $constraintName, $summary ) ) {
				$summary[$constraintName] = array(
					CheckResult::STATUS_COMPLIANCE => 0,
					CheckResult::STATUS_VIOLATION => 0,
					CheckResult::STATUS_EXCEPTION => 0,
					CheckResult::STATUS_TODO => 0
				);
			}
			if( array_key_exists( $status, $summary[$constraintName] ) ) {
				$summary[$constraintName][$status]++;
			}
		}

		return json_encode( $summary );
	}

	/**
	 * Returns json-representation of CheckResults,
	 * either from params or from ConstraintChecker
	 * (when invoked via a deferred job; see EvaluateConstraintReportJob)
	 *
	 * @param $params
	 *
	 * @return string
	 */
	public function getResults( $params ) {
		if ( !array_key_exists( 'results', $params ) ) {
			$lookup = WikibaseRepo::getDefaultInstance()->getEntityLookup();
			$constraintChecker = ConstraintReportFactory::getDefaultInstance()->getConstraintChecker();
			$entityId = $params['entityId'][0] === 'Q' ? new ItemId( $params['entityId'] ) : new PropertyId( $params['entityId'] );
			$results = $constraintChecker->checkAgainstConstraints( $lookup->getEntity( $entityId ) );
			return $this->buildResultSummary( $results );
		} else {
			return $params['results'];
		}
	}
}