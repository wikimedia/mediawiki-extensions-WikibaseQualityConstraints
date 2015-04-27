<?php

namespace WikidataQuality\ConstraintReport\ConstraintCheck\Checker;

use Wikibase\DataModel\Statement\StatementList;
use WikidataQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikidataQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper;
use Wikibase\DataModel\Statement\Statement;
use WikidataQuality\ConstraintReport\ConstraintCheck\Helper\ValueCountCheckerHelper;
use WikidataQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use Wikibase\DataModel\Entity\Entity;


/**
 * Checks 'Single value' constraint.
 *
 * @package WikidataQuality\ConstraintReport\ConstraintCheck\Checker
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
	 * @param array $constraintParameters
	 * @param Entity $entity
	 *
	 * @return CheckResult
	 */
	public function checkConstraint( Statement $statement, $constraintParameters, Entity $entity = null ) {
		$propertyId = $statement->getClaim()->getPropertyId();

		$parameters = array ();

		$propertyCountArray = $this->valueCountCheckerHelper->getPropertyCount( $entity->getStatements() );

		if ( $propertyCountArray[ $propertyId->getNumericId() ] > 1 ) {
			$message = 'This property must only have a single value, that is there must only be one claim using this property.';
			$status = CheckResult::STATUS_VIOLATION;
		} else {
			$message = '';
			$status = CheckResult::STATUS_COMPLIANCE;
		}

		return new CheckResult( $statement, 'Single value', $parameters, $status, $message );
	}
}