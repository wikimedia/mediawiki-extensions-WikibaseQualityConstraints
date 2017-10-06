<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Checker;

use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\Context;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;

/**
 * @package WikibaseQuality\ConstraintReport\ConstraintCheck\Checker
 * @author Lucas Werkmeister
 * @license GNU GPL v2+
 */
class ReferenceChecker implements ConstraintChecker {

	public function checkConstraint( Context $context, Constraint $constraint ) {
		if ( $context->getType() === Context::TYPE_REFERENCE ) {
			return new CheckResult( $context, $constraint, [], CheckResult::STATUS_COMPLIANCE, '' );
		} else {
			$message = wfMessage( 'wbqc-violation-message-reference' )->escaped();
			return new CheckResult( $context, $constraint, [], CheckResult::STATUS_VIOLATION, $message );
		}
	}

	public function checkConstraintParameters( Constraint $constraint ) {
		// no parameters
		return [];
	}

}
