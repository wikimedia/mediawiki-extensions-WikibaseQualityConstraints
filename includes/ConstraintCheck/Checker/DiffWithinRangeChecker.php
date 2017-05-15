<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Checker;

use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\StatementListProvider;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\RangeCheckerHelper;
use WikibaseQuality\ConstraintReport\ConstraintParameterRenderer;
use Wikibase\DataModel\Statement\Statement;

/**
 * @package WikibaseQuality\ConstraintReport\ConstraintCheck\Checker
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class DiffWithinRangeChecker implements ConstraintChecker {

	/**
	 * @var ConstraintParameterParser
	 */
	private $constraintParameterParser;

	/**
	 * @var RangeCheckerHelper
	 */
	private $rangeCheckerHelper;

	/**
	 * @var ConstraintParameterRenderer
	 */
	private $constraintParameterRenderer;

	/**
	 * @param ConstraintParameterParser $helper
	 * @param RangeCheckerHelper $rangeCheckerHelper
	 * @param ConstraintParameterRenderer $constraintParameterRenderer
	 */
	public function __construct(
		ConstraintParameterParser $helper,
		RangeCheckerHelper $rangeCheckerHelper,
		ConstraintParameterRenderer $constraintParameterRenderer
	) {
		$this->constraintParameterParser = $helper;
		$this->rangeCheckerHelper = $rangeCheckerHelper;
		$this->constraintParameterRenderer = $constraintParameterRenderer;
	}

	/**
	 * Checks 'Diff within range' constraint.
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
		$property = false;
		if ( array_key_exists( 'property', $constraintParameters ) ) {
			$property = strtoupper( $constraintParameters['property'] ); // FIXME strtoupper should not be necessary, remove once constraints are imported from statements
			$parameters['property'] = $this->constraintParameterParser->parseSingleParameter( $property );
		}

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
			return new CheckResult( $entity->getId(), $statement, $constraint->getConstraintTypeQid(), $constraint->getConstraintId(),  $parameters, CheckResult::STATUS_VIOLATION, $message );
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
				$parameters['minimum_quantity'] = $this->constraintParameterParser->parseSingleParameter( $constraintParameters['minimum_quantity'], true );
				$parameters['maximum_quantity'] = $this->constraintParameterParser->parseSingleParameter( $constraintParameters['maximum_quantity'], true );
			} else {
				$message = wfMessage( 'wbqc-violation-message-parameters-needed-3' )
					->params( $constraint->getConstraintTypeName(), 'property', 'minimum_quantity', 'maximum_quantity' )
					->escaped();
			}
		} else {
			$message = wfMessage( "wbqc-violation-message-value-needed-of-types-2" )->params( $constraint->getConstraintTypeName(), 'quantity', 'time' )->escaped();
		}
		if ( isset( $message ) ) {
			return new CheckResult( $entity->getId(), $statement, $constraint->getConstraintTypeQid(), $constraint->getConstraintId(),  $parameters, CheckResult::STATUS_VIOLATION, $message );
		}

		// checks only the first occurrence of the referenced property (this constraint implies a single value constraint on that property)
		/** @var Statement $otherStatement */
		foreach ( $entity->getStatements() as $otherStatement ) {
			if ( $property === $otherStatement->getPropertyId()->getSerialization() ) {
				$otherMainSnak = $otherStatement->getMainSnak();

				/*
				 * error handling:
				 *   types of this and the other value have to be equal, both must contain actual values
				 */
				if ( !$otherMainSnak instanceof PropertyValueSnak ) {
					$message = wfMessage( "wbqc-violation-message-diff-within-range-property-needs value" )->escaped();
					return new CheckResult(
						$entity->getId(),
						$otherStatement,
						$constraint->getConstraintTypeQid(),
						$constraint->getConstraintId(),
						$parameters,
						CheckResult::STATUS_VIOLATION,
						$message
					);
				}

				if ( $otherMainSnak->getDataValue()->getType() === $dataValue->getType() && $otherMainSnak->getType() === 'value' ) {
					$diff = $this->rangeCheckerHelper->getDifference( $dataValue, $otherMainSnak->getDataValue() );

					if ( $diff < $min || $diff > $max ) {
						$message = wfMessage( 'wbqc-violation-message-diff-within-range' );
						$message->rawParams(
							$this->constraintParameterRenderer->formatEntityId( $statement->getPropertyId() ),
							$this->constraintParameterRenderer->formatDataValue( $mainSnak->getDataValue() ),
							$this->constraintParameterRenderer->formatEntityId( $otherStatement->getPropertyId() ),
							$this->constraintParameterRenderer->formatDataValue( $otherMainSnak->getDataValue() )
						);
						// TODO once we import constraints from statements, $min and $max here will also be DataValues
						$message->numParams( $min, $max );
						$message = $message->escaped();
						$status = CheckResult::STATUS_VIOLATION;
					} else {
						$message = '';
						$status = CheckResult::STATUS_COMPLIANCE;
					}
				} else {
					$message = wfMessage( "wbqc-violation-message-diff-within-range-must-have-equal-types" )->escaped();
					$status = CheckResult::STATUS_VIOLATION;
				}

				return new CheckResult( $entity->getId(), $statement, $constraint->getConstraintTypeQid(), $constraint->getConstraintId(),  $parameters, $status, $message );
			}
		}

		$message = wfMessage( "wbqc-violation-message-diff-within-range-property-must-exist" )->escaped();
		$status = CheckResult::STATUS_VIOLATION;
		return new CheckResult( $entity->getId(), $statement, $constraint->getConstraintTypeQid(), $constraint->getConstraintId(),  $parameters, $status, $message );
	}

}
