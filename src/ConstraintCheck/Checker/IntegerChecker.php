<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Checker;

use DataValues\DecimalValue;
use DataValues\UnboundedQuantityValue;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\Context;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessage;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikibaseQuality\ConstraintReport\Role;

/**
 * @author Amir Sarabadani
 * @license GPL-2.0-or-later
 */
class IntegerChecker implements ConstraintChecker {

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

	public function checkConstraint( Context $context, Constraint $constraint ) {
		if ( $context->getSnak()->getType() !== 'value' ) {
			return new CheckResult( $context, $constraint, [], CheckResult::STATUS_COMPLIANCE );
		}

		/** @var PropertyValueSnak $snak */
		$snak = $context->getSnak();

		if ( $snak->getDataValue() instanceof DecimalValue ) {
			return $this->checkDecimalValue( $snak->getDataValue(), $context, $constraint );
		} elseif ( $snak->getDataValue() instanceof UnboundedQuantityValue ) {
			return $this->checkDecimalValue( $snak->getDataValue()->getAmount(), $context, $constraint );
		}

		return new CheckResult( $context, $constraint, [], CheckResult::STATUS_COMPLIANCE );
	}

	private function checkDecimalValue( DecimalValue $decimalValue, Context $context, Constraint $constraint ) {
		if ( $decimalValue->getTrimmed()->getFractionalPart() === '' ) {
			return new CheckResult( $context, $constraint, [], CheckResult::STATUS_COMPLIANCE );
		}

		$message = ( new ViolationMessage( 'wbqc-violation-message-integer' ) )
			->withEntityId( $context->getSnak()->getPropertyId(), Role::CONSTRAINT_PROPERTY )
			->withDataValue( $decimalValue );
		return new CheckResult(
			$context,
			$constraint,
			[],
			CheckResult::STATUS_VIOLATION,
			$message
		);
	}

	public function checkConstraintParameters( Constraint $constraint ) {
		// no parameters
		return [];
	}

}
