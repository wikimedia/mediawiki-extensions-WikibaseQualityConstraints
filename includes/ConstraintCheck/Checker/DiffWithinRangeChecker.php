<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Checker;

use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\StatementListProvider;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintStatementParameterParser;
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
	 * @var ConstraintStatementParameterParser
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
	 * @param ConstraintStatementParameterParser $constraintParameterParser
	 * @param RangeCheckerHelper $rangeCheckerHelper
	 * @param ConstraintParameterRenderer $constraintParameterRenderer
	 */
	public function __construct(
		ConstraintStatementParameterParser $constraintParameterParser,
		RangeCheckerHelper $rangeCheckerHelper,
		ConstraintParameterRenderer $constraintParameterRenderer
	) {
		$this->constraintParameterParser = $constraintParameterParser;
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

		list( $min, $max ) = $this->constraintParameterParser->parseRangeParameter(
			$constraintParameters,
			$constraint->getConstraintTypeName(),
			'quantity'
		);
		$property = $this->constraintParameterParser->parsePropertyParameter( $constraintParameters, $constraint->getConstraintTypeName() );

		$parameterKey = $dataValue->getType() === 'quantity' ? 'quantity' : 'date';
		if ( $min !== null ) {
			$parameters['minimum_' . $parameterKey] = [ $min ];
		}
		if ( $max !== null ) {
			$parameters['maximum_' . $parameterKey] = [ $max ];
		}
		$parameter['property'] = [ $property ];

		// checks only the first occurrence of the referenced property (this constraint implies a single value constraint on that property)
		/** @var Statement $otherStatement */
		foreach ( $entity->getStatements() as $otherStatement ) {
			if ( $property->equals( $otherStatement->getPropertyId() ) ) {
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

					if ( $this->rangeCheckerHelper->getComparison( $min, $diff ) > 0 ||
						$this->rangeCheckerHelper->getComparison( $diff, $max ) > 0 ) {
						$message = wfMessage( 'wbqc-violation-message-diff-within-range' )
							->rawParams(
								$this->constraintParameterRenderer->formatEntityId( $statement->getPropertyId() ),
								$this->constraintParameterRenderer->formatDataValue( $mainSnak->getDataValue() ),
								$this->constraintParameterRenderer->formatEntityId( $otherStatement->getPropertyId() ),
								$this->constraintParameterRenderer->formatDataValue( $otherMainSnak->getDataValue() ),
								$min !== null ? $this->constraintParameterRenderer->formatDataValue( $min ) : '−∞',
								$max !== null ? $this->constraintParameterRenderer->formatDataValue( $max ) : '∞'
							)
							->escaped();
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
