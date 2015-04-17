<?php

namespace WikidataQuality\ConstraintReport\ConstraintCheck\Checker;

use WikidataQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper;
use Wikibase\DataModel\Statement\Statement;
use WikidataQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;


/**
 * Class FormatChecker.
 * Checks 'Format' constraint.
 *
 * @package WikidataQuality\ConstraintReport\ConstraintCheck\Checker
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class FormatChecker {

	/**
	 * Class for helper functions for constraint checkers.
	 *
	 * @var ConstraintReportHelper
	 */
	private $helper;

	/**
	 * @param ConstraintReportHelper $helper
	 */
	public function __construct( $helper ) {
		$this->helper = $helper;
	}

	/**
	 * Checks 'Format' constraint.
	 *
	 * @param Statement $statement
	 * @param string $pattern
	 *
	 * @return CheckResult
	 */
	public function checkFormatConstraint( Statement $statement, $pattern ) {
		$dataValue = $statement->getClaim()->getMainSnak()->getDataValue();

		$parameters = array ();

		$parameters[ 'pattern' ] = $this->helper->parseSingleParameter( $pattern );

		/*
		 * error handling:
		 *   type of $dataValue for properties with 'Format' constraint has to be 'string'
		 *   parameter $pattern must not be null
		 */
		if ( $dataValue->getType() !== 'string' ) {
			$message = 'Properties with \'Format\' constraint need to have values of type \'string\'.';
			return new CheckResult( $statement, 'Format', $parameters, 'violation', $message );
		}
		if ( $pattern === null ) {
			$message = 'Properties with \'Format\' constraint need a parameter \'pattern\'.';
			return new CheckResult( $statement, 'Format', $parameters, 'violation', $message );
		}

		$comparativeString = $dataValue->getValue();

		if ( preg_match( '/^' . str_replace( '/', '\/', $pattern ) . '$/', $comparativeString ) ) {
			$message = '';
			$status = 'compliance';
		} else {
			$message = 'The property\'s value must match the pattern defined in the parameters.';
			$status = 'violation';
		}

		return new CheckResult( $statement, 'Format', $parameters, $status, $message );
	}

}