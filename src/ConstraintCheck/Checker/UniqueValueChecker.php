<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Checker;

use Wikibase\DataModel\Entity\ItemId;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\Metadata;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\Context;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\SparqlHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\SparqlHelperException;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessage;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikibaseQuality\ConstraintReport\ConstraintParameterRenderer;
use WikibaseQuality\ConstraintReport\Role;
use Wikibase\DataModel\Statement\Statement;

/**
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
	 * @codeCoverageIgnore This method is purely declarative.
	 */
	public function getSupportedContextTypes() {
		return [
			Context::TYPE_STATEMENT => CheckResult::STATUS_COMPLIANCE,
			Context::TYPE_QUALIFIER => CheckResult::STATUS_COMPLIANCE,
			Context::TYPE_REFERENCE => CheckResult::STATUS_COMPLIANCE,
		];
	}

	/**
	 * @codeCoverageIgnore This method is purely declarative.
	 */
	public function getDefaultContextTypes() {
		return [
			Context::TYPE_STATEMENT,
		];
	}

	/**
	 * Checks 'Unique value' constraint.
	 *
	 * @param Context $context
	 * @param Constraint $constraint
	 *
	 * @throws SparqlHelperException if the checker uses SPARQL and the query times out or some other error occurs
	 * @return CheckResult
	 */
	public function checkConstraint( Context $context, Constraint $constraint ) {
		if ( $context->getSnakRank() === Statement::RANK_DEPRECATED ) {
			return new CheckResult( $context, $constraint, [], CheckResult::STATUS_DEPRECATED );
		}

		$parameters = [];

		if ( $this->sparqlHelper !== null ) {
			if ( $context->getType() === 'statement' ) {
				$result = $this->sparqlHelper->findEntitiesWithSameStatement(
					$context->getSnakStatement(),
					true // ignore deprecated statements
				);
			} else {
				if ( $context->getSnak()->getType() !== 'value' ) {
					return new CheckResult( $context, $constraint, [], CheckResult::STATUS_COMPLIANCE );
				}
				$result = $this->sparqlHelper->findEntitiesWithSameQualifierOrReference(
					$context->getEntity()->getId(),
					$context->getSnak(),
					$context->getType(),
					// ignore qualifiers of deprecated statements but still check their references
					$context->getType() === 'qualifier'
				);
			}
			$otherEntities = $result->getArray();
			$metadata = $result->getMetadata();

			if ( $otherEntities === [] ) {
				$status = CheckResult::STATUS_COMPLIANCE;
				$message = null;
			} else {
				$otherEntities = array_values( array_filter( $otherEntities ) ); // remove nulls
				$status = CheckResult::STATUS_VIOLATION;
				$message = ( new ViolationMessage( 'wbqc-violation-message-unique-value' ) )
					->withEntityIdList( $otherEntities, Role::SUBJECT );
			}
		} else {
			$status = CheckResult::STATUS_TODO;
			$message = ( new ViolationMessage( 'wbqc-violation-message-not-yet-implemented' ) )
				->withEntityId( new ItemId( $constraint->getConstraintTypeItemId() ), Role::CONSTRAINT_TYPE_ITEM );
			$metadata = Metadata::blank();
		}

		return ( new CheckResult( $context, $constraint, $parameters, $status, $message ) )
			->withMetadata( $metadata );
	}

	public function checkConstraintParameters( Constraint $constraint ) {
		// no parameters
		return [];
	}

}
