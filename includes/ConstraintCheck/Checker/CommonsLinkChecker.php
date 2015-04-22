<?php

namespace WikidataQuality\ConstraintReport\ConstraintCheck\Checker;

use WikidataQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper;
use Wikibase\DataModel\Statement\Statement;
use WikidataQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;


/**
 * Class CommonsLinkChecker.
 * Checks 'Commons link' constraint.
 *
 * @package WikidataQuality\ConstraintReport\ConstraintCheck\Checker
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class CommonsLinkChecker {

	/**
	 * Class for helper functions for constraint checkers.
	 *
	 * @var ConstraintReportHelper
	 */
	private $helper;

	/**
	 * @param ConstraintReportHelper $helper
	 */
	public function __construct( ConstraintReportHelper $helper ) {
		$this->helper = $helper;
	}

	/**
	 * Checks if data value is well-formed and links to an existing page.
	 *
	 * @param Statement $statement
	 * @param string $namespace
	 *
	 * @return CheckResult
	 */
	public function checkCommonsLinkConstraint( Statement $statement, $namespace ) {
		$dataValue = $statement->getClaim()->getMainSnak()->getDataValue();

		$parameters = array ();

		$parameters[ 'namespace' ] = $this->helper->parseSingleParameter( $namespace );

		/*
		 * error handling:
		 *   type of $dataValue for properties with 'Commons link' constraint has to be 'string'
		 *   parameter $namespace can be null, works for commons galleries
		 */
		if ( $dataValue->getType() !== 'string' ) {
			$message = 'Properties with \'Commons link\' constraint need to have values of type \'string\'.';
			return new CheckResult( $statement, 'Commons link', $parameters, CheckResult::STATUS_VIOLATION, $message );
		}

		$commonsLink = $dataValue->getValue();

		if ( $this->commonsLinkIsWellFormed( $commonsLink ) ) {
			if ( $this->urlExists( $commonsLink, $namespace ) ) {
				$message = '';
				$status = CheckResult::STATUS_COMPLIANCE;
			} else {
				$message = 'Commons link must exist.';
				$status = CheckResult::STATUS_VIOLATION;
			}
		} else {
			$message = 'Commons link must be well-formed.';
			$status = CheckResult::STATUS_VIOLATION;
		}

		return new CheckResult( $statement, 'Commons link', $parameters, $status, $message );
	}

	/**
	 * @param string $commonsLink
	 * @param string $namespace
	 *
	 * @return bool
	 */
	private function urlExists( $commonsLink, $namespace ) {
		if ( $namespace !== null ) {
			$namespace .= ':';
		}
		$response = get_headers( 'http://commons.wikimedia.org/wiki/' . $namespace . str_replace( ' ', '_', $commonsLink ) );
		$responseCode = substr( $response[ 0 ], 9, 3 );
		return $responseCode < 400;
	}

	/**
	 * @param string $commonsLink
	 *
	 * @return bool
	 */
	private function commonsLinkIsWellFormed( $commonsLink ) {
		$toReplace = array ( "_", ":", "%20" );
		$compareString = trim( str_replace( $toReplace, '', $commonsLink ) );
		return $commonsLink === $compareString;
	}

}