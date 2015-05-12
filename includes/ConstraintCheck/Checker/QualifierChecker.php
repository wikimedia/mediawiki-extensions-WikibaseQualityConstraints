<?php

namespace WikidataQuality\ConstraintReport\ConstraintCheck\Checker;

use WikidataQuality\ConstraintReport\Constraint;
use WikidataQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikidataQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper;
use Wikibase\DataModel\Statement\Statement;
use WikidataQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use Wikibase\DataModel\Entity\Entity;


/**
 * Checks 'Qualifier' constraint.
 *
 * @package WikidataQuality\ConstraintReport\ConstraintCheck\Checker
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class QualifierChecker implements ConstraintChecker {

	/**
	 * Class for helper functions for constraint checkers.
	 *
	 * @var ConstraintReportHelper
	 */
	private $helper;

	/**
	 * @param ConstraintReportHelper $helper
	 */
	public function __construct( ConstraintReportHelper $helper ) {
		$this->helper = $helper;
	}

	/**
	 * If this method gets invoked, it is automatically a violation since this method only gets invoked
	 * for properties used in statements.
	 *
	 * @param Statement $statement
	 * @param Constraint $constraint
	 * @param Entity $entity
	 *
	 * @return CheckResult
	 */
	public function checkConstraint( Statement $statement, Constraint $constraint, Entity $entity = null ) {
		$message = 'The property must only be used as a qualifier.';
		return new CheckResult( $statement, $constraint->getConstraintTypeQid(), array (), CheckResult::STATUS_VIOLATION, $message );
	}
}