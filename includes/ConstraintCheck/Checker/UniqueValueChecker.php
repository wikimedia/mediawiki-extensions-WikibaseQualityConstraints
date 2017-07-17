<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Checker;

use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Statement\StatementListProvider;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\SparqlHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\SparqlHelperException;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikibaseQuality\ConstraintReport\ConstraintParameterRenderer;
use Wikibase\DataModel\Statement\Statement;

/**
 * @package WikibaseQuality\ConstraintReport\ConstraintCheck\Checker
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class UniqueValueChecker implements ConstraintChecker {

	/**
	 * @var ConstraintParameterRenderer
	 */
	private $constraintParameterRenderer;

	/**
	 * @var SparqlHelper|null
	 */
	private $sparqlHelper;

	/**
	 * @param ConstraintParameterRenderer $constraintParameterRenderer used in error messages
	 * @param SparqlHelper|null $sparqlHelper Helper to run SPARQL queries, or null if SPARQL is not available.
	 */
	public function __construct(
		ConstraintParameterRenderer $constraintParameterRenderer,
		SparqlHelper $sparqlHelper = null
	) {
		$this->constraintParameterRenderer = $constraintParameterRenderer;
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
	 *
	 * @throws SparqlHelperException if the checker uses SPARQL and the query times out or some other error occurs
	 */
	public function checkConstraint( Statement $statement, Constraint $constraint, EntityDocument $entity ) {
		if ( $statement->getRank() === Statement::RANK_DEPRECATED ) {
			return new CheckResult( $entity->getId(), $statement, $constraint, [], CheckResult::STATUS_DEPRECATED );
		}

		$parameters = [];

		if ( $this->sparqlHelper !== null ) {
			$otherEntities = $this->sparqlHelper->findEntitiesWithSameStatement( $statement );

			if ( $otherEntities === [] ) {
				$status = CheckResult::STATUS_COMPLIANCE;
				$message = '';
			} else {
				$status = CheckResult::STATUS_VIOLATION;
				$message = wfMessage( 'wbqc-violation-message-unique-value' )
						 ->numParams( count( $otherEntities ) )
						 ->rawParams( $this->constraintParameterRenderer->formatItemIdList( $otherEntities, ConstraintParameterRenderer::ROLE_SUBJECT ) )
						 ->escaped();
			}
		} else {
			$status = CheckResult::STATUS_TODO;
			$message = wfMessage( "wbqc-violation-message-not-yet-implemented" )
					 ->rawParams( $this->constraintParameterRenderer->formatItemId( $constraint->getConstraintTypeItemId(), ConstraintParameterRenderer::ROLE_CONSTRAINT_TYPE_ITEM ) )
					 ->escaped();
		}

		return new CheckResult( $entity->getId(), $statement, $constraint, $parameters, $status, $message );
	}

	public function checkConstraintParameters( Constraint $constraint ) {
		// no parameters
		return [];
	}

}
