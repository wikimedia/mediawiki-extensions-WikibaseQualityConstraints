<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Checker;

use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ValueCountCheckerHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Entity\Entity;


/**
 * Checks 'Single value' constraint.
 *
 * @package WikibaseQuality\ConstraintReport\ConstraintCheck\Checker
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class SingleValueChecker implements ConstraintChecker {

	/**
	 * @var ValueCountCheckerHelper
	 */
	private $valueCountCheckerHelper;

	public function __construct() {
		$this->valueCountCheckerHelper = new ValueCountCheckerHelper();
	}

	/**
	 * Checks 'Single value' constraint.
	 *
	 * @param Statement $statement
	 * @param Constraint $constraint
	 * @param Entity $entity
	 *
	 * @return CheckResult
	 */
	public function checkConstraint( Statement $statement, Constraint $constraint, Entity $entity = null ) {
		$propertyId = $statement->getClaim()->getPropertyId();

		$parameters = array ();

		$constraintParameters = $constraint->getConstraintParameters();
		if ( array_key_exists( 'constraint_status', $constraintParameters ) ) {
			$parameters[ 'constraint_status' ] = $this->helper->parseSingleParameter( $constraintParameters['constraint_status'], true );
		}

		$propertyCountArray = $this->valueCountCheckerHelper->getPropertyCount( $entity->getStatements() );

		if ( $propertyCountArray[ $propertyId->getNumericId() ] > 1 ) {
			$message = 'This property must only have a single value, that is there must only be one claim using this property.';
			$status = CheckResult::STATUS_VIOLATION;
		} else {
			$message = '';
			$status = CheckResult::STATUS_COMPLIANCE;
		}

		return new CheckResult( $statement, $constraint->getConstraintTypeQid(), $parameters, $status, $message );
	}
}