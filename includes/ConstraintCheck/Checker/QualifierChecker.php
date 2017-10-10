<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Checker;

use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\Context;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;

/**
 * @package WikibaseQuality\ConstraintReport\ConstraintCheck\Checker
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class QualifierChecker implements ConstraintChecker {

	public function __construct() {
	}

	/**
	 * Checks 'Qualifier' constraint.
	 *
	 * @param Context $context
	 * @param Constraint $constraint
	 *
	 * @return CheckResult
	 */
	public function checkConstraint( Context $context, Constraint $constraint ) {
		if ( $context->getType() === Context::TYPE_QUALIFIER ) {
			return new CheckResult( $context, $constraint, [], CheckResult::STATUS_COMPLIANCE, '' );
		} else {
			$message = wfMessage( 'wbqc-violation-message-qualifier' )->escaped();
			return new CheckResult( $context, $constraint, [], CheckResult::STATUS_VIOLATION, $message );
		}
	}

	public function checkConstraintParameters( Constraint $constraint ) {
		// no parameters
		return [];
	}

}
