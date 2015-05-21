<?php

namespace WikibaseQuality\ConstraintReport;

use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\Repo\WikibaseRepo;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;


class EvaluateConstraintReportJobService {

	public function writeToLog( $message ) {
		wfDebugLog( 'wbq_evaluation', $message );
	}

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