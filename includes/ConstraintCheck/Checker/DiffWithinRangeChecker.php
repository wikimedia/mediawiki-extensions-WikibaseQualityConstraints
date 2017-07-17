<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Checker;

use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\StatementListProvider;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterException;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\RangeCheckerHelper;
use WikibaseQuality\ConstraintReport\ConstraintParameterRenderer;
use WikibaseQuality\ConstraintReport\Role;
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
	 * @param ConstraintParameterParser $constraintParameterParser
	 * @param RangeCheckerHelper $rangeCheckerHelper
	 * @param ConstraintParameterRenderer $constraintParameterRenderer
	 */
	public function __construct(
		ConstraintParameterParser $constraintParameterParser,
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
		if ( $statement->getRank() === Statement::RANK_DEPRECATED ) {
			return new CheckResult( $entity->getId(), $statement, $constraint, [], CheckResult::STATUS_DEPRECATED );
		}

		$parameters = [];
		$constraintParameters = $constraint->getConstraintParameters();

		$mainSnak = $statement->getMainSnak();

		/*
		 * error handling:
		 *   $mainSnak must be PropertyValueSnak, neither PropertySomeValueSnak nor PropertyNoValueSnak is allowed
		 */
		if ( !$mainSnak instanceof PropertyValueSnak ) {
			$message = wfMessage( "wbqc-violation-message-value-needed" )
					 ->rawParams( $this->constraintParameterRenderer->formatItemId( $constraint->getConstraintTypeItemId(), Role::CONSTRAINT_TYPE_ITEM ) )
					 ->escaped();
			return new CheckResult( $entity->getId(), $statement, $constraint,  $parameters, CheckResult::STATUS_VIOLATION, $message );
		}

		$dataValue = $mainSnak->getDataValue();

		list( $min, $max ) = $this->constraintParameterParser->parseRangeParameter(
			$constraintParameters,
			$constraint->getConstraintTypeItemId(),
			'quantity'
		);
		$property = $this->constraintParameterParser->parsePropertyParameter( $constraintParameters, $constraint->getConstraintTypeItemId() );

		$parameterKey = $dataValue->getType() === 'quantity' ? 'quantity' : 'date';
		if ( $min !== null ) {
			$parameters['minimum_' . $parameterKey] = [ $min ];
		}
		if ( $max !== null ) {
			$parameters['maximum_' . $parameterKey] = [ $max ];
		}
		$parameters['property'] = [ $property ];

		// checks only the first occurrence of the referenced property (this constraint implies a single value constraint on that property)
		/** @var Statement $otherStatement */
		foreach ( $entity->getStatements() as $otherStatement ) {
			if ( $property->equals( $otherStatement->getPropertyId() ) ) {
				// ignore deprecated statements of the referenced property
				if ( $otherStatement->getRank() === Statement::RANK_DEPRECATED ) {
					continue;
				}

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
						$constraint,
						$parameters,
						CheckResult::STATUS_VIOLATION,
						$message
					);
				}

				if ( $otherMainSnak->getDataValue()->getType() === $dataValue->getType() && $otherMainSnak->getType() === 'value' ) {
					$diff = $this->rangeCheckerHelper->getDifference( $dataValue, $otherMainSnak->getDataValue() );

					if ( $this->rangeCheckerHelper->getComparison( $min, $diff ) > 0 ||
						$this->rangeCheckerHelper->getComparison( $diff, $max ) > 0 ) {
						// at least one of $min, $max is set at this point, otherwise there could be no violation
						$openness = $min !== null ? ( $max !== null ? '' : '-rightopen' ) : '-leftopen';
						$message = wfMessage( "wbqc-violation-message-diff-within-range$openness" );
						$message->rawParams(
							$this->constraintParameterRenderer->formatEntityId( $statement->getPropertyId(), Role::PREDICATE ),
							$this->constraintParameterRenderer->formatDataValue( $mainSnak->getDataValue(), Role::OBJECT ),
							$this->constraintParameterRenderer->formatEntityId( $otherStatement->getPropertyId(), Role::PREDICATE ),
							$this->constraintParameterRenderer->formatDataValue( $otherMainSnak->getDataValue(), Role::OBJECT )
						);
						if ( $min !== null ) {
							$message->rawParams( $this->constraintParameterRenderer->formatDataValue( $min, Role::OBJECT ) );
						}
						if ( $max !== null ) {
							$message->rawParams( $this->constraintParameterRenderer->formatDataValue( $max, Role::OBJECT ) );
						}
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

				return new CheckResult( $entity->getId(), $statement, $constraint,  $parameters, $status, $message );
			}
		}

		$message = wfMessage( "wbqc-violation-message-diff-within-range-property-must-exist" )->escaped();
		$status = CheckResult::STATUS_VIOLATION;
		return new CheckResult( $entity->getId(), $statement, $constraint,  $parameters, $status, $message );
	}

	public function checkConstraintParameters( Constraint $constraint ) {
		$constraintParameters = $constraint->getConstraintParameters();
		$exceptions = [];
		try {
			$this->constraintParameterParser->parseRangeParameter(
				$constraintParameters,
				$constraint->getConstraintTypeItemId(),
				'quantity'
			);
		} catch ( ConstraintParameterException $e ) {
			$exceptions[] = $e;
		}
		try {
			$this->constraintParameterParser->parsePropertyParameter( $constraintParameters, $constraint->getConstraintTypeItemId() );
		} catch ( ConstraintParameterException $e ) {
			$exceptions[] = $e;
		}
		return $exceptions;
	}

}
