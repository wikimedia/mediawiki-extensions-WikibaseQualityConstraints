<?php

namespace WikidataQuality\ConstraintReport\ConstraintCheck;

use Wikibase\DataModel\Entity\Entity;
use Wikibase\DataModel\Statement\Statement;
use WikidataQuality\Result\CheckResult;


interface ConstraintChecker {

	/**
	 * @param Statement $statement
	 * @param array $constraintParameters
	 * @param Entity $entity
	 *
	 * @return CheckResult
	 */
	public function checkConstraint( Statement $statement, $constraintParameters, Entity $entity = null );

}