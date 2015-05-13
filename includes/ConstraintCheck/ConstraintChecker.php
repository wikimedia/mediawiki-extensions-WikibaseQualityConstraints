<?php

namespace WikidataQuality\ConstraintReport\ConstraintCheck;

use Wikibase\DataModel\Entity\Entity;
use Wikibase\DataModel\Statement\Statement;
use WikidataQuality\ConstraintReport\Constraint;
use WikidataQuality\Result\CheckResult;


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