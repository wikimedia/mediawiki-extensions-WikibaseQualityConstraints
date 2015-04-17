<?php

namespace WikidataQuality\ConstraintReport\ConstraintCheck\Checker;

use WikidataQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper;
use Wikibase\DataModel\Statement\Statement;
use WikidataQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;


/**
 * Class QualifierChecker.
 * Checks 'Qualifier' and 'Qualifiers' constraint.
 *
 * @package WikidataQuality\ConstraintReport\ConstraintCheck\Checker
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class QualifierChecker {

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
	 * If this method gets invoked, it is automatically a violation since this method only gets invoked
	 * for properties used in statements.
	 *
	 * @param Statement $statement
	 *
	 * @return CheckResult
	 */
	public function checkQualifierConstraint( Statement $statement ) {
		$message = 'The property must only be used as a qualifier.';
		return new CheckResult( $statement, 'Qualifier', array (), 'violation', $message );
	}

	/**
	 * Checks 'Qualifiers' constraint.
	 *
	 * @param Statement $statement
	 * @param array $propertyArray
	 *
	 * @return CheckResult
	 */
	public function checkQualifiersConstraint( Statement $statement, $propertyArray ) {
		$parameters = array ();

		$parameters[ 'property' ] = $this->helper->parseParameterArray( $propertyArray, 'PropertyId' );

		/*
		 * error handling:
		 *  parameter $propertyArray can be null, meaning that there are explicitly no qualifiers allowed
		 */

		$message = '';
		$status = 'compliance';

		foreach ( $statement->getQualifiers() as $qualifier ) {
			$pid = $qualifier->getPropertyId()->getSerialization();
			if ( !in_array( $pid, $propertyArray ) ) {
				$message = 'The property must only be used with (no other than) the qualifiers defined in the parameters.';
				$status = 'violation';
				break;
			}
		}

		return new CheckResult( $statement, 'Qualifiers', $parameters, $status, $message );
	}

	/**
	 * @param Statement $statement
	 * @param array $propertyArray
	 *
	 * @return CheckResult
	 */
	public function checkMandatoryQualifiersConstraint( Statement $statement, $propertyArray ) {
		$parameters = array ();

		$parameters[ 'property' ] = $this->helper->parseParameterArray( $propertyArray, 'PropertyId' );
		$qualifiersList = $statement->getQualifiers();
		$qualifiers = array ();

		foreach ( $qualifiersList as $qualifier ) {
			$qualifiers[ $qualifier->getPropertyId()->getSerialization() ] = true;
		}

		$message = '';
		$status = 'compliance';

		foreach ( $propertyArray as $property ) {
			if ( !array_key_exists( $property, $qualifiers ) ) {
				$message = 'The properties defined in the parameters have to be used as qualifiers on this statement.';
				$status = 'violation';
				break;
			}
		}

		return new CheckResult( $statement, 'Mandatory Qualifiers', $parameters, $status, $message );
	}

}