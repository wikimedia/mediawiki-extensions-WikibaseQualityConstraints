<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Checker;

use Wikibase\DataModel\Snak\PropertyValueSnak;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\RangeCheckerHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Entity\Entity;


/**
 * Class RangeChecker.
 * Checks 'Range' constraints.
 *
 * @package WikibaseQuality\ConstraintReport\ConstraintCheck\Checker
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class RangeChecker implements ConstraintChecker {

	/**
	 * Class for helper functions for constraint checkers.
	 *
	 * @var ConstraintReportHelper
	 */
	private $constraintReportHelper;

	/**
	 * @var RangeCheckerHelper
	 */
	private $rangeCheckerHelper;

	/**
	 * @param ConstraintReportHelper $helper
	 * @param RangeCheckerHelper $rangeCheckerHelper
	 */
	public function __construct( ConstraintReportHelper $helper, RangeCheckerHelper $rangeCheckerHelper ) {
		$this->constraintReportHelper = $helper;
		$this->rangeCheckerHelper = $rangeCheckerHelper;
	}

	/**
	 * Checks 'Range' constraint.
	 *
	 * @param Statement $statement
	 * @param Constraint $constraint
	 * @param Entity $entity
	 *
	 * @return CheckResult
	 */
	public function checkConstraint( Statement $statement, Constraint $constraint, Entity $entity = null ) {
		$parameters = array ();
		$constraintParameters = $constraint->getConstraintParameters();

		if ( array_key_exists( 'constraint_status', $constraintParameters ) ) {
			$parameters[ 'constraint_status' ] = $this->helper->parseSingleParameter( $constraintParameters['constraint_status'], true );
		}

		$mainSnak = $statement->getClaim()->getMainSnak();

		/*
		 * error handling:
		 *   $mainSnak must be PropertyValueSnak, neither PropertySomeValueSnak nor PropertyNoValueSnak is allowed
		 */
		if ( !$mainSnak instanceof PropertyValueSnak ) {
			$message = 'Properties with \'Range\' constraint need to have a value.';
			return new CheckResult( $statement, $constraint->getConstraintTypeQid(), $parameters, CheckResult::STATUS_VIOLATION, $message );
		}

		$dataValue = $mainSnak->getDataValue();

		/*
		 * error handling:
		 *   type of $dataValue for properties with 'Range' constraint has to be 'quantity' or 'time' (also 'number' and 'decimal' could work)
		 *   parameters (minimum_quantity and maximum_quantity) or (minimum_date and maximum_date) must not be null
		 */
		if ( $dataValue->getType() === 'quantity' ) {
			if ( array_key_exists( 'minimum_quantity', $constraintParameters ) && array_key_exists( 'maximum_quantity', $constraintParameters ) && !array_key_exists( 'minimum_date', $constraintParameters ) && !array_key_exists( 'maximum_date', $constraintParameters ) ) {
				$min = $constraintParameters['minimum_quantity'];
				$max = $constraintParameters['maximum_quantity'];
				$parameters[ 'minimum_quantity' ] = $this->constraintReportHelper->parseSingleParameter( $constraintParameters['minimum_quantity'] );
				$parameters[ 'maximum_quantity' ] = $this->constraintReportHelper->parseSingleParameter( $constraintParameters['maximum_quantity'] );
			} else {
				$message = 'Properties with values of type \'quantity\' with \'Range\' constraint need the parameters \'minimum quantity\' and \'maximum quantity\'.';
			}
		} elseif ( $dataValue->getType() === 'time' ) {
			if ( !array_key_exists( 'minimum_quantity', $constraintParameters ) && !array_key_exists( 'maximum_quantity', $constraintParameters ) && array_key_exists( 'minimum_date', $constraintParameters ) && array_key_exists( 'maximum_date', $constraintParameters ) ) {
				$min = $constraintParameters['minimum_date'];
				$max = $constraintParameters['maximum_date'];
				$parameters[ 'minimum_date' ] = $this->constraintReportHelper->parseSingleParameter( $constraintParameters['minimum_date'] );
				$parameters[ 'maximum_date' ] = $this->constraintReportHelper->parseSingleParameter( $constraintParameters['maximum_date'] );
			} else {
				$message = 'Properties with values of type \'time\' with \'Range\' constraint need the parameters \'minimum date\' and \'maximum date\'.';
			}
		} else {
			$message = 'Properties with \'Range\' constraint need to have values of type \'quantity\' or \'time\'.';
		}
		if ( isset( $message ) ) {
			return new CheckResult( $statement, $constraint->getConstraintTypeQid(), $parameters, CheckResult::STATUS_VIOLATION, $message );
		}

		$comparativeValue = $this->rangeCheckerHelper->getComparativeValue( $dataValue );

		if ( $comparativeValue < $min || $comparativeValue > $max ) {
			$message = 'The property\'s value must neither be smaller than the minimum nor larger than the maximum defined in the parameters.';
			$status = CheckResult::STATUS_VIOLATION;
		} else {
			$message = '';
			$status = CheckResult::STATUS_COMPLIANCE;
		}

		return new CheckResult( $statement, $constraint->getConstraintTypeQid(), $parameters, $status, $message );
	}
}