<?php

declare( strict_types = 1 );

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Checker;

use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\Statement;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\Metadata;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\Context;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterException;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\DummySparqlHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\SparqlHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\SparqlHelperException;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessage;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikibaseQuality\ConstraintReport\Role;

/**
 * @author BP2014N1
 * @license GPL-2.0-or-later
 */
class UniqueValueChecker implements ConstraintChecker {

	private SparqlHelper $sparqlHelper;

	private ConstraintParameterParser $constraintParameterParser;

	public function __construct(
		SparqlHelper $sparqlHelper,
		ConstraintParameterParser $constraintParameterParser
	) {
		$this->sparqlHelper = $sparqlHelper;
		$this->constraintParameterParser = $constraintParameterParser;
	}

	/**
	 * @codeCoverageIgnore This method is purely declarative.
	 */
	public function getSupportedContextTypes(): array {
		return self::ALL_CONTEXT_TYPES_SUPPORTED;
	}

	/**
	 * @codeCoverageIgnore This method is purely declarative.
	 */
	public function getDefaultContextTypes(): array {
		return [
			Context::TYPE_STATEMENT,
		];
	}

	/** @codeCoverageIgnore This method is purely declarative. */
	public function getSupportedEntityTypes(): array {
		return self::ALL_ENTITY_TYPES_SUPPORTED;
	}

	/**
	 * Checks 'Unique value' constraint.
	 *
	 * @throws SparqlHelperException if the checker uses SPARQL and the query times out or some other error occurs
	 */
	public function checkConstraint( Context $context, Constraint $constraint ): CheckResult {
		if ( $context->getSnakRank() === Statement::RANK_DEPRECATED ) {
			return new CheckResult( $context, $constraint, CheckResult::STATUS_DEPRECATED );
		}

		if ( !( $this->sparqlHelper instanceof DummySparqlHelper ) ) {

			$separators = $this->constraintParameterParser->parseSeparatorsParameter(
				$constraint->getConstraintParameters()
			);

			if ( $context->getType() === 'statement' ) {
				$statement = $context->getSnakStatement();
				'@phan-var Statement $statement';
				$result = $this->sparqlHelper->findEntitiesWithSameStatement(
					$context->getEntity()->getId(),
					$statement,
					$separators
				);
			} else {
				$snak = $context->getSnak();
				if ( !$snak instanceof PropertyValueSnak ) {
					// nothing to check
					return new CheckResult( $context, $constraint, CheckResult::STATUS_COMPLIANCE );
				}
				$result = $this->sparqlHelper->findEntitiesWithSameQualifierOrReference(
					$context->getEntity()->getId(),
					$snak,
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

		return ( new CheckResult( $context, $constraint, $status, $message ) )
			->withMetadata( $metadata );
	}

	public function checkConstraintParameters( Constraint $constraint ): array {
		$constraintParameters = $constraint->getConstraintParameters();
		$exceptions = [];
		try {
			$this->constraintParameterParser->parseSeparatorsParameter( $constraintParameters );
		} catch ( ConstraintParameterException $e ) {
			$exceptions[] = $e;
		}
		return $exceptions;
	}

}
