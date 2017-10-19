<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck;

use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\Context;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterException;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\SparqlHelperException;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;

/**
 * Checks a constraint on some constraint checking context.
 * Most implementations only support one constraint type.
 *
 * @license GNU GPL v2+
 */
interface ConstraintChecker {

	/**
	 * @param Context $context
	 * @param Constraint $constraint
	 *
	 * @return CheckResult
	 *
	 * @throws ConstraintParameterException if the constraint parameters are invalid
	 * @throws SparqlHelperException if the checker uses SPARQL and the query times out or some other error occurs
	 */
	public function checkConstraint( Context $context, Constraint $constraint );

	/**
	 * Check if the constraint parameters of $constraint are valid.
	 * Returns a list of ConstraintParameterExceptions, one for each problematic parameter;
	 * if the list is empty, all constraint parameters are okay.
	 *
	 * @param Constraint $constraint
	 *
	 * @return ConstraintParameterException[]
	 */
	public function checkConstraintParameters( Constraint $constraint );

}
