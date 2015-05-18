<?php

namespace WikibaseQuality\ConstraintReport;

use Wikibase\Repo\WikibaseRepo;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikibaseQuality\ConstraintReport\ConstraintCheck\DelegatingConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\CheckerMapBuilder;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper;


class EvaluateConstraintReportJobService {

	public function writeToLog( $message ) {
		wfDebugLog( 'wdqa_evaluation', $message );
	}

	public function buildMessageForLog( $results, $timestamp, $params ) {
		return json_encode(
			array (
				'special_page_id' => 'SpecialConstraintReport',
				'entity_id' => $params['entityId']->getSerialization(),
				'insertion_timestamp' => $timestamp,
				'reference_timestamp' => $params['referenceTimestamp'],
				'result_summary' => $this->buildResultSummary( $results )
			)
		);
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

	public function getResults( $params ) {
		if ( $params[ 'results' ] === null ) {
			$lookup = WikibaseRepo::getDefaultInstance()->getEntityLookup();
			$checkerMap = new CheckerMapBuilder( $lookup, new ConstraintReportHelper() );
			$constraintChecker = new DelegatingConstraintChecker( $lookup, $checkerMap->getCheckerMap() );

			return $constraintChecker->checkAgainstConstraints( $lookup->getEntity( $params[ 'entityId' ] ) );
		} else {
			return $params['results'];
		}
	}
}