<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Checker;

use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\Context;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\SparqlHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\SparqlHelperException;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikibaseQuality\ConstraintReport\ConstraintParameterRenderer;
use WikibaseQuality\ConstraintReport\Role;
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
	 * @param Context $context
	 * @param Constraint $constraint
	 *
	 * @return CheckResult
	 *
	 * @throws SparqlHelperException if the checker uses SPARQL and the query times out or some other error occurs
	 */
	public function checkConstraint( Context $context, Constraint $constraint ) {
		if ( $context->getSnakRank() === Statement::RANK_DEPRECATED ) {
			return new CheckResult( $context, $constraint, [], CheckResult::STATUS_DEPRECATED );
		}

		$parameters = [];

		if ( $this->sparqlHelper !== null ) {
			if ( $context->getType() === 'statement' ) {
				$otherEntities = $this->sparqlHelper->findEntitiesWithSameStatement(
					$context->getSnakStatement(),
					true // ignore deprecated statements
				);
			} else {
				if ( $context->getSnak()->getType() !== 'value' ) {
					return new CheckResult( $context, $constraint, [], CheckResult::STATUS_COMPLIANCE );
				}
				$otherEntities = $this->sparqlHelper->findEntitiesWithSameQualifierOrReference(
					$context->getEntity()->getId(),
					$context->getSnak(),
					$context->getType(),
					// ignore qualifiers of deprecated statements but still check their references
					$context->getType() === 'qualifier'
				);
			}

			if ( $otherEntities === [] ) {
				$status = CheckResult::STATUS_COMPLIANCE;
				$message = '';
			} else {
				$status = CheckResult::STATUS_VIOLATION;
				$message = wfMessage( 'wbqc-violation-message-unique-value' )
						 ->numParams( count( $otherEntities ) )
						 ->rawParams( $this->constraintParameterRenderer->formatEntityIdList( $otherEntities, Role::SUBJECT ) )
						 ->escaped();
			}
		} else {
			$status = CheckResult::STATUS_TODO;
			$message = wfMessage( "wbqc-violation-message-not-yet-implemented" )
					 ->rawParams( $this->constraintParameterRenderer->formatItemId( $constraint->getConstraintTypeItemId(), Role::CONSTRAINT_TYPE_ITEM ) )
					 ->escaped();
		}

		return new CheckResult( $context, $constraint, $parameters, $status, $message );
	}

	public function checkConstraintParameters( Constraint $constraint ) {
		// no parameters
		return [];
	}

}
