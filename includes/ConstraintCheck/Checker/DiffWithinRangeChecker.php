<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Checker;

use Wikibase\DataModel\Snak\PropertyValueSnak;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\RangeCheckerHelper;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Entity\Entity;


/**
 * @package WikibaseQuality\ConstraintReport\ConstraintCheck\Checker
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class DiffWithinRangeChecker implements ConstraintChecker {

	/**
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
	 * Checks 'Diff within range' constraint.
	 *
	 * @param Statement $statement
	 * @param Constraint $constraint
	 * @param Entity $entity
	 *
	 * @return CheckResult
	 */
	public function checkConstraint( Statement $statement, Constraint $constraint, Entity $entity = null ) {
		$constraintName = 'Diff within range';
		$parameters = array ();
		$constraintParameters = $constraint->getConstraintParameters();
		$property = false;
		if ( array_key_exists( 'property', $constraintParameters ) ) {
			$property = $constraintParameters['property'];
			$parameters['property'] = $this->constraintReportHelper->parseSingleParameter( $constraintParameters['property'], 'PropertyId' );
		}

		if ( array_key_exists( 'constraint_status', $constraintParameters ) ) {
			$parameters[ 'constraint_status' ] = $this->helper->parseSingleParameter( $constraintParameters['constraint_status'], true );
		}

		$mainSnak = $statement->getMainSnak();

		/*
		 * error handling:
		 *   $mainSnak must be PropertyValueSnak, neither PropertySomeValueSnak nor PropertyNoValueSnak is allowed
		 */
		if ( !$mainSnak instanceof PropertyValueSnak ) {
			$message = wfMessage( "wbqc-violation-message-value-needed" )->params( $constraintName )->escaped();
			return new CheckResult( $statement, $constraint->getConstraintTypeQid(), $parameters, CheckResult::STATUS_VIOLATION, $message );
		}

		$dataValue = $mainSnak->getDataValue();

		/*
		 * error handling:
		 *   type of $dataValue for properties with 'Diff within range' constraint has to be 'quantity' or 'time' (also 'number' and 'decimal' could work)
		 *   parameters $property, $minimum_quantity and $maximum_quantity must not be null
		 */
		if ( $dataValue->getType() === 'quantity' || $dataValue->getType() === 'time' ) {
			if ( $property && array_key_exists( 'minimum_quantity', $constraintParameters ) && array_key_exists( 'maximum_quantity', $constraintParameters ) ) {
				$min = $constraintParameters['minimum_quantity'];
				$max = $constraintParameters['maximum_quantity'];
				$parameters['minimum_quantity'] = $this->constraintReportHelper->parseSingleParameter( $constraintParameters['minimum_quantity'] );
				$parameters['maximum_quantity'] = $this->constraintReportHelper->parseSingleParameter( $constraintParameters['maximum_quantity'] );
			} else {
				$message = wfMessage( "wbqc-violation-message-parameter-needed" )->params( $constraintName, 'property", "minimum_quantity" and "maximum_quantity' )->escaped();
			}
		} else {
			$message = wfMessage( "wbqc-violation-message-value-needed-of-type" )->params( $constraintName, 'quantity" or "time' )->escaped();
		}
		if ( isset( $message ) ) {
			return new CheckResult( $statement, $constraint->getConstraintTypeQid(), $parameters, CheckResult::STATUS_VIOLATION, $message );
		}

		$thisValue = $this->rangeCheckerHelper->getComparativeValue( $dataValue );

		// checks only the first occurrence of the referenced property (this constraint implies a single value constraint on that property)
		foreach ( $entity->getStatements() as $statement ) {
			if ( $property === $statement->getPropertyId()->getSerialization() ) {
				$mainSnak = $statement->getMainSnak();

				/*
				 * error handling:
				 *   types of this and the other value have to be equal, both must contain actual values
				 */
				if ( !$mainSnak instanceof PropertyValueSnak ) {
					$message = 'Referenced property needs to have a value.';
					return new CheckResult( $statement, $constraint->getConstraintTypeQid(), $parameters, CheckResult::STATUS_VIOLATION, $message );
				}
				if ( $mainSnak->getDataValue()->getType() === $dataValue->getType() && $mainSnak->getType() === 'value' ) {

					$thatValue = $this->rangeCheckerHelper->getComparativeValue( $mainSnak->getDataValue() );

					// negative difference is possible
					$diff = $thisValue - $thatValue;

					if ( $diff < $min || $diff > $max ) {
						$message = wfMessage( "wbqc-violation-message-diff-within-range" )->escaped();
						$status = CheckResult::STATUS_VIOLATION;
					} else {
						$message = '';
						$status = CheckResult::STATUS_COMPLIANCE;
					}
				} else {
					$message = wfMessage( "wbqc-violation-message-diff-within-range-must-have-equal-types" )->escaped();
					$status = CheckResult::STATUS_VIOLATION;
				}

				return new CheckResult( $statement, $constraint->getConstraintTypeQid(), $parameters, $status, $message );
			}
		}
		$message = wfMessage( "wbqc-violation-message-diff-within-range-property-must-exist" )->escaped();
		$status = CheckResult::STATUS_VIOLATION;
		return new CheckResult( $statement, $constraint->getConstraintTypeQid(), $parameters, $status, $message );
	}

}