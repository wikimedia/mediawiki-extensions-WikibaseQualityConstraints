<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Checker;

use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Entity\Entity;


/**
 * @package WikibaseQuality\ConstraintReport\ConstraintCheck\Checker
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class QualifierChecker implements ConstraintChecker {

	/**
	 * @var ConstraintParameterParser
	 */
	private $helper;

	/**
	 * @param ConstraintParameterParser $helper
	 */
	public function __construct( ConstraintParameterParser $helper ) {
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
		$message = wfMessage( "wbqc-violation-message-qualifier" )->escaped();
		return new CheckResult( $statement, $constraint->getConstraintTypeQid(), array (), CheckResult::STATUS_VIOLATION, $message );
	}

}