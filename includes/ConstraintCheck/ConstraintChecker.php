<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck;

use Wikibase\DataModel\Entity\Entity;
use Wikibase\DataModel\Statement\Statement;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;


interface ConstraintChecker {

	/**
	 * @param Statement $statement
	 * @param Constraint $constraint
	 * @param Entity $entity
	 *
	 * @return CheckResult
	 */
	public function checkConstraint( Statement $statement, Constraint $constraint, Entity $entity = null );

}