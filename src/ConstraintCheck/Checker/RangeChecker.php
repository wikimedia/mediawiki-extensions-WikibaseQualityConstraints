<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Checker;

use DataValues\DataValue;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\Context;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterException;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\NowValue;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\RangeCheckerHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessage;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikibaseQuality\ConstraintReport\ConstraintParameterRenderer;
use WikibaseQuality\ConstraintReport\Role;
use Wikibase\DataModel\Statement\Statement;

/**
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class RangeChecker implements ConstraintChecker {

	/**
	 * @var PropertyDataTypeLookup
	 */
	private $propertyDataTypeLookup;

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

	public function __construct(
		PropertyDataTypeLookup $propertyDataTypeLookup,
		ConstraintParameterParser $constraintParameterParser,
		RangeCheckerHelper $rangeCheckerHelper,
		ConstraintParameterRenderer $constraintParameterRenderer
	) {
		$this->propertyDataTypeLookup = $propertyDataTypeLookup;
		$this->constraintParameterParser = $constraintParameterParser;
		$this->rangeCheckerHelper = $rangeCheckerHelper;
		$this->constraintParameterRenderer = $constraintParameterRenderer;
	}

	/**
	 * @codeCoverageIgnore This method is purely declarative.
	 */
	public function getSupportedContextTypes() {
		return [
			Context::TYPE_STATEMENT => CheckResult::STATUS_COMPLIANCE,
			Context::TYPE_QUALIFIER => CheckResult::STATUS_COMPLIANCE,
			Context::TYPE_REFERENCE => CheckResult::STATUS_COMPLIANCE,
		];
	}

	/**
	 * @codeCoverageIgnore This method is purely declarative.
	 */
	public function getDefaultContextTypes() {
		return [
			Context::TYPE_STATEMENT,
			Context::TYPE_QUALIFIER,
			Context::TYPE_REFERENCE,
		];
	}

	/**
	 * Checks 'Range' constraint.
	 *
	 * @param Context $context
	 * @param Constraint $constraint
	 *
	 * @throws ConstraintParameterException
	 * @return CheckResult
	 */
	public function checkConstraint( Context $context, Constraint $constraint ) {
		if ( $context->getSnakRank() === Statement::RANK_DEPRECATED ) {
			return new CheckResult( $context, $constraint, [], CheckResult::STATUS_DEPRECATED );
		}

		$parameters = [];
		$constraintParameters = $constraint->getConstraintParameters();

		$snak = $context->getSnak();

		if ( !$snak instanceof PropertyValueSnak ) {
			// nothing to check
			return new CheckResult( $context, $constraint, $parameters, CheckResult::STATUS_COMPLIANCE );
		}

		$dataValue = $snak->getDataValue();

		list( $min, $max ) = $this->constraintParameterParser->parseRangeParameter(
			$constraintParameters,
			$constraint->getConstraintTypeItemId(),
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
			 $this->rangeCheckerHelper->getComparison( $dataValue, $max ) > 0
		) {
			$message = $this->getViolationMessage(
				$context->getSnak()->getPropertyId(),
				$dataValue,
				$min,
				$max
			);
			$status = CheckResult::STATUS_VIOLATION;
		} else {
			$message = null;
			$status = CheckResult::STATUS_COMPLIANCE;
		}

		return new CheckResult( $context, $constraint, $parameters, $status, $message );
	}

	/**
	 * @param PropertyId $predicate
	 * @param DataValue $value
	 * @param DataValue|null $min
	 * @param DataValue|null $max
	 *
	 * @return ViolationMessage
	 */
	private function getViolationMessage( PropertyId $predicate, DataValue $value, $min, $max ) {
		// possible message keys:
		// wbqc-violation-message-range-quantity-closed
		// wbqc-violation-message-range-quantity-leftopen
		// wbqc-violation-message-range-quantity-rightopen
		// wbqc-violation-message-range-time-closed
		// wbqc-violation-message-range-time-closed-leftnow
		// wbqc-violation-message-range-time-closed-rightnow
		// wbqc-violation-message-range-time-leftopen
		// wbqc-violation-message-range-time-leftopen-rightnow
		// wbqc-violation-message-range-time-rightopen
		// wbqc-violation-message-range-time-rightopen-leftnow
		$messageKey = 'wbqc-violation-message-range';
		$messageKey .= '-' . $value->getType();
		// at least one of $min, $max is set, otherwise there could be no violation
		$messageKey .= '-' . ( $min !== null ? ( $max !== null ? 'closed' : 'rightopen' ) : 'leftopen' );
		if ( $min instanceof NowValue ) {
			$messageKey .= '-leftnow';
		} elseif ( $max instanceof  NowValue ) {
			$messageKey .= '-rightnow';
		}
		$message = ( new ViolationMessage( $messageKey ) )
			->withEntityId( $predicate, Role::PREDICATE )
			->withDataValue( $value, Role::OBJECT );
		if ( $min !== null && !( $min instanceof NowValue ) ) {
			$message = $message->withDataValue( $min, Role::OBJECT );
		}
		if ( $max !== null && !( $max instanceof  NowValue ) ) {
			$message = $message->withDataValue( $max, Role::OBJECT );
		}
		return $message;
	}

	public function checkConstraintParameters( Constraint $constraint ) {
		$constraintParameters = $constraint->getConstraintParameters();
		$exceptions = [];
		try {
			// we don’t have a data value here, so get the type from the property instead
			// (the distinction between data type and data value type is irrelevant for 'quantity' and 'time')
			$type = $this->propertyDataTypeLookup->getDataTypeIdForProperty( $constraint->getPropertyId() );
			$this->constraintParameterParser->parseRangeParameter(
				$constraintParameters,
				$constraint->getConstraintTypeItemId(),
				$type
			);
		} catch ( ConstraintParameterException $e ) {
			$exceptions[] = $e;
		}
		return $exceptions;
	}

}
