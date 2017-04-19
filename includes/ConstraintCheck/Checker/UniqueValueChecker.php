<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Checker;

use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Statement\StatementListProvider;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\SparqlHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\SparqlHelperException;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use Wikibase\DataModel\Statement\Statement;

/**
 * @package WikibaseQuality\ConstraintReport\ConstraintCheck\Checker
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class UniqueValueChecker implements ConstraintChecker {

	/**
	 * @var SparqlHelper
	 */
	private $sparqlHelper;

	public function __construct( SparqlHelper $sparqlHelper ) {
		$this->sparqlHelper = $sparqlHelper;
	}

	/**
	 * Checks 'Unique value' constraint.
	 *
	 * @param Statement $statement
	 * @param Constraint $constraint
	 * @param EntityDocument|StatementListProvider $entity
	 *
	 * @return CheckResult
	 */
	public function checkConstraint( Statement $statement, Constraint $constraint, EntityDocument $entity ) {
		$parameters = [];

		try {
			$otherEntities = $this->sparqlHelper->findEntitiesWithSameStatement( $statement );

			if ( $otherEntities === [] ) {
				$status = CheckResult::STATUS_COMPLIANCE;
				$message = '';
			} else {
				$status = CheckResult::STATUS_VIOLATION;
				// TODO include the other entities in the message
				$message = wfMessage( 'wbqc-violation-message-unique-value' )->escaped();
			}
		} catch ( SparqlHelperException $e ) {
			$status = CheckResult::STATUS_VIOLATION;
			$message = wfMessage( 'wbqc-violation-message-sparql-error' )->escaped();
		}

		return new CheckResult( $entity->getId(), $statement, $constraint->getConstraintTypeQid(), $constraint->getConstraintId(), $parameters, $status, $message );
	}

}
