<?php

namespace WikidataQuality\ConstraintReport\ConstraintCheck\Checker;

use Wikibase\DataModel\Statement\StatementList;
use WikidataQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper;
use Wikibase\DataModel\Statement\Statement;
use WikidataQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;


/**
 * Class RangeChecker.
 * Checks 'Range' and 'Diff within range' constraints.
 *
 * @package WikidataQuality\ConstraintReport\ConstraintCheck\Checker
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class RangeChecker {

	/**
	 * List of all statements of given entity.
	 *
	 * @var StatementList
	 */
	private $statements;

	/**
	 * Class for helper functions for constraint checkers.
	 *
	 * @var ConstraintReportHelper
	 */
	private $helper;

	/**
	 * @param StatementList $statements
	 * @param ConstraintReportHelper $helper
	 */
	public function __construct( StatementList $statements, ConstraintReportHelper $helper ) {
		$this->statements = $statements;
		$this->helper = $helper;
	}

	/**
	 * Checks 'Range' constraint.
	 *
	 * @param Statement $statement
	 * @param string $minimum_quantity
	 * @param string $maximum_quantity
	 * @param string $minimum_date
	 * @param string $maximum_date
	 *
	 * @return CheckResult
	 */
	public function checkRangeConstraint( Statement $statement, $minimum_quantity, $maximum_quantity, $minimum_date, $maximum_date ) {
		$dataValue = $statement->getClaim()->getMainSnak()->getDataValue();

		$parameters = array ();

		/*
		 * error handling:
		 *   type of $dataValue for properties with 'Range' constraint has to be 'quantity' or 'time' (also 'number' and 'decimal' could work)
		 *   parameters ($minimum_quantity and $maximum_quantity) or ($minimum_date and $maximum_date) must not be null
		 */
		if ( $dataValue->getType() === 'quantity' ) {
			if ( $minimum_quantity !== null && $maximum_quantity !== null && $minimum_date === null && $maximum_date === null ) {
				$min = $minimum_quantity;
				$max = $maximum_quantity;
				$parameters[ 'minimum_quantity' ] = $this->helper->parseSingleParameter( $minimum_quantity );
				$parameters[ 'maximum_quantity' ] = $this->helper->parseSingleParameter( $maximum_quantity );
			} else {
				$message = 'Properties with values of type \'quantity\' with \'Range\' constraint need the parameters \'minimum quantity\' and \'maximum quantity\'.';
			}
		} elseif ( $dataValue->getType() === 'time' ) {
			if ( $minimum_quantity === null && $maximum_quantity === null && $minimum_date !== null && $maximum_date !== null ) {
				$min = $minimum_date->getTime();
				$max = $maximum_date->getTime();
				$parameters[ 'minimum_date' ] = $this->helper->parseSingleParameter( $minimum_date );
				$parameters[ 'maximum_date' ] = $this->helper->parseSingleParameter( $maximum_date );
			} else {
				$message = 'Properties with values of type \'time\' with \'Range\' constraint need the parameters \'minimum date\' and \'maximum date\'.';
			}
		} else {
			$message = 'Properties with \'Range\' constraint need to have values of type \'quantity\' or \'time\'.';
		}
		if ( isset( $message ) ) {
			return new CheckResult( $statement, 'Range', $parameters, CheckResult::STATUS_VIOLATION, $message );
		}

		$comparativeValue = $this->getComparativeValue( $dataValue );

		if ( $comparativeValue < $min || $comparativeValue > $max ) {
			$message = 'The property\'s value must neither be smaller than the minimum nor larger than the maximum defined in the parameters.';
			$status = CheckResult::STATUS_VIOLATION;
		} else {
			$message = '';
			$status = CheckResult::STATUS_COMPLIANCE;
		}

		return new CheckResult( $statement, 'Range', $parameters, $status, $message );
	}

	/**
	 * @param Statement $statement
	 * @param string $property
	 * @param string $minimum_quantity
	 * @param string $maximum_quantity
	 *
	 * @return CheckResult
	 */
	public function checkDiffWithinRangeConstraint( Statement $statement, $property, $minimum_quantity, $maximum_quantity ) {
		$dataValue = $statement->getClaim()->getMainSnak()->getDataValue();

		$parameters = array ();

		$parameters[ 'property' ] = $this->helper->parseSingleParameter( $property, 'PropertyId' );

		/*
		 * error handling:
		 *   type of $dataValue for properties with 'Diff within range' constraint has to be 'quantity' or 'time' (also 'number' and 'decimal' could work)
		 *   parameters $property, $minimum_quantity and $maximum_quantity must not be null
		 */
		if ( $dataValue->getType() === 'quantity' || $dataValue->getType() === 'time' ) {
			if ( $property !== null && $minimum_quantity !== null && $maximum_quantity !== null ) {
				$min = $minimum_quantity;
				$max = $maximum_quantity;
				$parameters[ 'minimum_quantity' ] = $this->helper->parseSingleParameter( $minimum_quantity );
				$parameters[ 'maximum_quantity' ] = $this->helper->parseSingleParameter( $maximum_quantity );
			} else {
				$message = 'Properties with \'Diff within range\' constraint need the parameters \'property\', \'minimum quantity\' and \'maximum quantity\'.';
			}
		} else {
			$message = 'Properties with \'Diff within range\' constraint need to have values of type \'quantity\' or \'time\'.';
		}
		if ( isset( $message ) ) {
			return new CheckResult( $statement, 'Diff within range', $parameters, CheckResult::STATUS_VIOLATION, $message );
		}

		$thisValue = $this->getComparativeValue( $dataValue );

		// checks only the first occurrence of the referenced property (this constraint implies a single value constraint on that property)
		foreach ( $this->statements as $statement ) {
			if ( $property === $statement->getClaim()->getPropertyId()->getSerialization() ) {
				$mainSnak = $statement->getClaim()->getMainSnak();

				/*
				 * error handling:
				 *   types of this and the other value have to be equal, both must contain actual values
				 */
				if ( $mainSnak->getDataValue()->getType() === $dataValue->getType() && $mainSnak->getType() === 'value' ) {

					$thatValue = $this->getComparativeValue( $mainSnak->getDataValue() );

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

				return new CheckResult( $statement, 'Diff within range', $parameters, $status, $message );
			}
		}
		$message = 'The property defined in the parameters must exist.';
		$status = CheckResult::STATUS_VIOLATION;
		return new CheckResult( $statement, 'Diff within range', $parameters, $status, $message );
	}

	private function getComparativeValue( $dataValue ) {
		if ( $dataValue->getType() === 'time' ) {
			return $dataValue->getTime();
		} else {
			// 'quantity'
			return $dataValue->getAmount()->getValue();
		}
	}

}