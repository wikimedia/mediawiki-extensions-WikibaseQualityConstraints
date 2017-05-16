<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Checker;

use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\StatementListProvider;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\RangeCheckerHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use Wikibase\DataModel\Statement\Statement;

/**
 * @package WikibaseQuality\ConstraintReport\ConstraintCheck\Checker
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class RangeChecker implements ConstraintChecker {

	/**
	 * @var ConstraintParameterParser
	 */
	private $constraintParameterParser;

	/**
	 * @var RangeCheckerHelper
	 */
	private $rangeCheckerHelper;

	/**
	 * @param ConstraintParameterParser $helper
	 * @param RangeCheckerHelper $rangeCheckerHelper
	 */
	public function __construct( ConstraintParameterParser $helper, RangeCheckerHelper $rangeCheckerHelper ) {
		$this->constraintParameterParser = $helper;
		$this->rangeCheckerHelper = $rangeCheckerHelper;
	}

	/**
	 * Checks 'Range' constraint.
	 *
	 * @param Statement $statement
	 * @param Constraint $constraint
	 * @param EntityDocument|StatementListProvider $entity
	 *
	 * @return CheckResult
	 */
	public function checkConstraint( Statement $statement, Constraint $constraint, EntityDocument $entity ) {
		$parameters = [];
		$constraintParameters = $constraint->getConstraintParameters();

		if ( array_key_exists( 'constraint_status', $constraintParameters ) ) {
			$parameters['constraint_status'] = $this->constraintParameterParser->parseSingleParameter( $constraintParameters['constraint_status'], true );
		}

		$mainSnak = $statement->getMainSnak();

		/*
		 * error handling:
		 *   $mainSnak must be PropertyValueSnak, neither PropertySomeValueSnak nor PropertyNoValueSnak is allowed
		 */
		if ( !$mainSnak instanceof PropertyValueSnak ) {
			$message = wfMessage( "wbqc-violation-message-value-needed" )->params( $constraint->getConstraintTypeName() )->escaped();
			return new CheckResult( $entity->getId(), $statement, $constraint->getConstraintTypeQid(), $constraint->getConstraintId(), $parameters, CheckResult::STATUS_VIOLATION, $message );
		}

		$dataValue = $mainSnak->getDataValue();

		/*
		 * error handling:
		 *   type of $dataValue for properties with 'Range' constraint has to be 'quantity' or 'time' (also 'number' and 'decimal' could work)
		 *   parameters (minimum_quantity and maximum_quantity) or (minimum_date and maximum_date) must not be null
		 */
		if ( $dataValue->getType() === 'quantity' ) {
			if ( array_key_exists( 'minimum_quantity', $constraintParameters )
				&& array_key_exists( 'maximum_quantity', $constraintParameters )
				&& !array_key_exists( 'minimum_date', $constraintParameters )
				&& !array_key_exists( 'maximum_date', $constraintParameters )
			) {
				$min = $constraintParameters['minimum_quantity'];
				$max = $constraintParameters['maximum_quantity'];
				$parameters['minimum_quantity'] = $this->constraintParameterParser->parseSingleParameter( $min, true );
				$parameters['maximum_quantity'] = $this->constraintParameterParser->parseSingleParameter( $max, true );
				$min = $this->rangeCheckerHelper->parseQuantity( $min );
				$max = $this->rangeCheckerHelper->parseQuantity( $max );
			} else {
				$message = wfMessage( 'wbqc-violation-message-range-parameters-needed' )
					->params( 'quantity', 'minimum_quantity', 'maximum_quantity' )->escaped();
			}
		} elseif ( $dataValue->getType() === 'time' ) {
			if ( !array_key_exists( 'minimum_quantity', $constraintParameters )
				&& !array_key_exists( 'maximum_quantity', $constraintParameters )
				&& array_key_exists( 'minimum_date', $constraintParameters )
				&& array_key_exists( 'maximum_date', $constraintParameters )
			) {
				$min = $constraintParameters['minimum_date'];
				$max = $constraintParameters['maximum_date'];
				$parameters['minimum_date'] = $this->constraintParameterParser->parseSingleParameter( $min, true );
				$parameters['maximum_date'] = $this->constraintParameterParser->parseSingleParameter( $max, true );
			} elseif ( array_key_exists( 'minimum_quantity', $constraintParameters )
				&& array_key_exists( 'maximum_quantity', $constraintParameters )
				&& !array_key_exists( 'minimum_date', $constraintParameters )
				&& !array_key_exists( 'maximum_date', $constraintParameters )
			) {
				// Temporary workaround for T164087: ConstraintsFromTemplates always calls the
				// parameters …_quantity, even for time properties, so fall back to that.
				// TODO: Remove this `elseif` once we only import constraints from statements and
				// the other ones have been removed from the constraints table in the database.
				$min = $constraintParameters['minimum_quantity'];
				$max = $constraintParameters['maximum_quantity'];
				$parameters['minimum_date'] = $this->constraintParameterParser->parseSingleParameter( $min, true );
				$parameters['maximum_date'] = $this->constraintParameterParser->parseSingleParameter( $max, true );
			} else {
				$message = wfMessage( 'wbqc-violation-message-range-parameters-needed' )
					->params( 'time', 'minimum_date', 'maximum_date' )->escaped();
			}
			if ( isset( $min ) && isset( $max ) ) {
				$now = gmdate( '+Y-m-d\T00:00:00\Z' );
				if ( $min === 'now' ) {
					$min = $now;
				}
				if ( $max === 'now' ) {
					$max = $now;
				}
				$min = $this->rangeCheckerHelper->parseTime( $min );
				$max = $this->rangeCheckerHelper->parseTime( $max );
			}
		} else {
			$message = wfMessage( 'wbqc-violation-message-value-needed-of-types-2' )
				->params( $constraint->getConstraintTypeName(), 'quantity', 'time' )->escaped();
		}

		if ( isset( $message ) ) {
			return new CheckResult(
				$entity->getId(),
				$statement,
				$constraint->getConstraintTypeQid(),
				$constraint->getConstraintId(),
				$parameters,
				CheckResult::STATUS_VIOLATION,
				$message
			);
		}

		if ( $this->rangeCheckerHelper->getComparison( $min, $dataValue ) > 0 ||
			 $this->rangeCheckerHelper->getComparison( $dataValue, $max ) > 0 ) {
			$message = wfMessage( "wbqc-violation-message-range" )->escaped();
			$status = CheckResult::STATUS_VIOLATION;
		} else {
			$message = '';
			$status = CheckResult::STATUS_COMPLIANCE;
		}

		return new CheckResult(
			$entity->getId(),
			$statement,
			$constraint->getConstraintTypeQid(),
			$constraint->getConstraintId(),
			$parameters,
			$status,
			$message
		);
	}

}
