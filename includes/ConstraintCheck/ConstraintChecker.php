<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck;

use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Statement\StatementListProvider;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterException;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\SparqlHelperException;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;

interface ConstraintChecker {

	/**
	 * @param Statement $statement
	 * @param Constraint $constraint
	 * @param EntityDocument|StatementListProvider $entity
	 *
	 * @return CheckResult
	 *
	 * @throws ConstraintParameterException if the constraint parameters are invalid
	 * @throws SparqlHelperException if the checker uses SPARQL and the query times out or some other error occurs
	 */
	public function checkConstraint( Statement $statement, Constraint $constraint, EntityDocument $entity );

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
