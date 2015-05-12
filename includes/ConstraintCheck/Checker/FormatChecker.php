<?php

namespace WikidataQuality\ConstraintReport\ConstraintCheck\Checker;

use Wikibase\DataModel\Snak\PropertyValueSnak;
use WikidataQuality\ConstraintReport\Constraint;
use WikidataQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikidataQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper;
use Wikibase\DataModel\Statement\Statement;
use WikidataQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use Wikibase\DataModel\Entity\Entity;

/**
 * Class FormatChecker.
 * Checks 'Format' constraint.
 *
 * @package WikidataQuality\ConstraintReport\ConstraintCheck\Checker
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class FormatChecker implements ConstraintChecker {

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
	 * @param Constraint constraint
	 * @param Entity $entity
	 *
	 * @return CheckResult
	 */
	public function checkConstraint( Statement $statement, Constraint $constraint, Entity $entity = null ) {
		$parameters = array ();
		$constraintParameters = $constraint->getConstraintParameter();

		$parameters['pattern'] = $this->helper->parseParameterArray( $constraintParameters['pattern'], true );

		$mainSnak = $statement->getClaim()->getMainSnak();

		/*
		 * error handling:
		 *   $mainSnak must be PropertyValueSnak, neither PropertySomeValueSnak nor PropertyNoValueSnak is allowed
		 */
		if ( !$mainSnak instanceof PropertyValueSnak ) {
			$message = 'Properties with \'Format\' constraint need to have a value.';
			return new CheckResult( $statement, $constraint->getConstraintTypeQid(), $parameters, CheckResult::STATUS_VIOLATION, $message );
		}

		$dataValue = $mainSnak->getDataValue();

		/*
		 * error handling:
		 *   type of $dataValue for properties with 'Format' constraint has to be 'string'
		 *   parameter $pattern must not be null
		 */
		if ( $dataValue->getType() !== 'string' ) {
			$message = 'Properties with \'Format\' constraint need to have values of type \'string\'.';
			return new CheckResult( $statement, $constraint->getConstraintTypeQid(), $parameters, CheckResult::STATUS_VIOLATION, $message );
		}
		if ( $constraintParameters['pattern'][0] === null ) {
			$message = 'Properties with \'Format\' constraint need a parameter \'pattern\'.';
			return new CheckResult( $statement, $constraint->getConstraintTypeQid(), $parameters, CheckResult::STATUS_VIOLATION, $message );
		}

		$comparativeString = $dataValue->getValue();

		if ( preg_match( '/^' . str_replace( '/', '\/', $constraintParameters['pattern'][0] ) . '$/', $comparativeString ) ) {
			$message = '';
			$status = CheckResult::STATUS_COMPLIANCE;
		} else {
			$message = 'The property\'s value must match the pattern defined in the parameters.';
			$status = CheckResult::STATUS_VIOLATION;
		}

		return new CheckResult( $statement, $constraint->getConstraintTypeQid(), $parameters, $status, $message );
	}

}