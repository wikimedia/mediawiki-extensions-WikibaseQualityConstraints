<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Checker;

use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ValueCountCheckerHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Entity\Entity;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper;


/**
 * Class ValueCountChecker.
 * Checks 'Unique value' constraint.
 *
 * @package WikibaseQuality\ConstraintReport\ConstraintCheck\Checker
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class UniqueValueChecker implements ConstraintChecker {

	/**
	 * @var ValueCountCheckerHelper
	 */
	private $valueCountCheckerHelper;

	/**
	 * @var ConstraintReportHelper
	 */
	private $constraintReportHelper;

	public function __construct( $helper ) {
		$this->constraintReportHelper = $helper;
		$this->valueCountCheckerHelper = new ValueCountCheckerHelper();
	}

	// todo: implement when index exists that makes it possible in reasonable time
	/**
	 * Checks 'Unique value' constraint.
	 *
	 * @param Statement $statement
	 * @param Constraint $constraint
	 * @param Entity $entity
	 *
	 * @return CheckResult
	 */
	public function checkConstraint( Statement $statement, Constraint $constraint, Entity $entity = null ) {
		$parameters = array ();

		$constraintParameters = $constraint->getConstraintParameters();
		if ( array_key_exists( 'constraint_status', $constraintParameters ) ) {
			$parameters[ 'constraint_status' ] = $this->constraintReportHelper->parseSingleParameter( $constraintParameters['constraint_status'], true );
		}

		$message = 'For technical reasons, the check for this constraint has not yet been implemented.';
		return new CheckResult( $statement, $constraint->getConstraintTypeQid(), $parameters, CheckResult::STATUS_TODO, $message );
	}
}