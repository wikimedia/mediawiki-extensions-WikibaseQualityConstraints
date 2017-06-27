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
use WikibaseQuality\ConstraintReport\ConstraintParameterRenderer;
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

		list( $min, $max ) = $this->constraintParameterParser->parseRangeParameter(
			$constraintParameters,
			$constraint->getConstraintTypeName(),
			$dataValue->getType()
		);
		$parameterKey = $dataValue->getType() === 'quantity' ? 'quantity' : 'date';
		if ( $min !== null ) {
			$parameters['minimum_' . $parameterKey] = [ $min ];
		}
		if ( $max !== null ) {
			$parameters['maximum_' . $parameterKey] = [ $max ];
		}

		if ( $this->rangeCheckerHelper->getComparison( $min, $dataValue ) > 0 ||
			 $this->rangeCheckerHelper->getComparison( $dataValue, $max ) > 0 ) {
			// at least one of $min, $max is set at this point, otherwise there could be no violation
			$type = $dataValue->getType();
			$openness = $min !== null ? ( $max !== null ? 'closed' : 'rightopen' ) : 'leftopen';
			$message = wfMessage( "wbqc-violation-message-range-$type-$openness" );
			$message->rawParams(
				$this->constraintParameterRenderer->formatEntityId( $statement->getPropertyId() ),
				$this->constraintParameterRenderer->formatDataValue( $dataValue )
			);
			if ( $min !== null ) {
				$message->rawParams( $this->constraintParameterRenderer->formatDataValue( $min ) );
			}
			if ( $max !== null ) {
				$message->rawParams( $this->constraintParameterRenderer->formatDataValue( $max ) );
			}
			$message = $message->escaped();
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
