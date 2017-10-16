<?php

namespace WikibaseQuality\ConstraintReport\Tests\Fake;

use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\Context;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;

/**
 * Constraint checker implementation that always returns results with a static status.
 *
 * @package WikibaseQuality\ConstraintReport\Tests\Fake
 * @license GNU GPL v2+
 */
class FakeChecker implements ConstraintChecker {

	/**
	 * @var string
	 */
	private $status;

	/**
	 * @param string $status
	 */
	public function __construct( $status = CheckResult::STATUS_TODO ) {
		$this->status = $status;
	}

	/**
	 * @see ConstraintChecker::checkConstraint
	 */
	public function checkConstraint( Context $context, Constraint $constraint ) {
		return new CheckResult( $context, $constraint, [], $this->status );
	}

	public function checkConstraintParameters( Constraint $constraint ) {
	}

}
