<?php

namespace WikidataQuality\ConstraintReport\ConstraintCheck\Checker;

use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\StatementList;
use WikidataQuality\ConstraintReport\Constraint;
use WikidataQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikidataQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper;
use Wikibase\DataModel\Statement\Statement;
use WikidataQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikidataQuality\ConstraintReport\ConstraintCheck\Helper\RangeCheckerHelper;
use Wikibase\DataModel\Entity\Entity;

/**
 * Checks 'Diff within range' constraints.
 *
 * @package WikidataQuality\ConstraintReport\ConstraintCheck\Checker
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class DiffWithinRangeChecker implements ConstraintChecker {

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
	 * Checks 'Diff within range' constraint.
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
		$property = false;
		if ( array_key_exists( 'property', $constraintParameters ) ) {
			$property = $constraintParameters['property'];
			$parameters['property'] = $this->constraintReportHelper->parseSingleParameter( $constraintParameters['property'], 'PropertyId' );
		}

		$mainSnak = $statement->getClaim()->getMainSnak();

		/*
		 * error handling:
		 *   $mainSnak must be PropertyValueSnak, neither PropertySomeValueSnak nor PropertyNoValueSnak is allowed
		 */
		if ( !$mainSnak instanceof PropertyValueSnak ) {
			$message = 'Properties with \'Diff within range\' constraint need to have a value.';
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
				$parameters[ 'minimum_quantity' ] = $this->constraintReportHelper->parseSingleParameter( $constraintParameters['minimum_quantity'] );
				$parameters[ 'maximum_quantity' ] = $this->constraintReportHelper->parseSingleParameter( $constraintParameters['maximum_quantity'] );
			} else {
				$message = 'Properties with \'Diff within range\' constraint need the parameters \'property\', \'minimum quantity\' and \'maximum quantity\'.';
			}
		} else {
			$message = 'Properties with \'Diff within range\' constraint need to have values of type \'quantity\' or \'time\'.';
		}
		if ( isset( $message ) ) {
			return new CheckResult( $statement, $constraint->getConstraintTypeQid(), $parameters, CheckResult::STATUS_VIOLATION, $message );
		}

		$thisValue = $this->rangeCheckerHelper->getComparativeValue( $dataValue );

		// checks only the first occurrence of the referenced property (this constraint implies a single value constraint on that property)
		foreach ( $entity->getStatements() as $statement ) {
			if ( $property === $statement->getClaim()->getPropertyId()->getSerialization() ) {
				$mainSnak = $statement->getClaim()->getMainSnak();

				/*
				 * error handling:
				 *   types of this and the other value have to be equal, both must contain actual values
				 */
				if ( $mainSnak->getDataValue()->getType() === $dataValue->getType() && $mainSnak->getType() === 'value' ) {

					$thatValue = $this->rangeCheckerHelper->getComparativeValue( $mainSnak->getDataValue() );

					// negative difference is possible
					$diff = $thisValue - $thatValue;

					if ( $diff < $min || $diff > $max ) {
						$message = 'The difference between this property\'s value and the value of the property defined in the parameters must neither be smaller than the minimum nor larger than the maximum defined in the parameters.';
						$status = CheckResult::STATUS_VIOLATION;
					} else {
						$message = '';
						$status = CheckResult::STATUS_COMPLIANCE;
					}
				} else {
					$message = 'The property defined in the parameters must have a value of the same type as this property.';
					$status = CheckResult::STATUS_VIOLATION;
				}

				return new CheckResult( $statement, $constraint->getConstraintTypeQid(), $parameters, $status, $message );
			}
		}
		$message = 'The property defined in the parameters must exist.';
		$status = CheckResult::STATUS_VIOLATION;
		return new CheckResult( $statement, $constraint->getConstraintTypeQid(), $parameters, $status, $message );
	}
}