<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Checker;

use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\DependencyMetadata;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\Metadata;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\Context;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConnectionCheckerHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikibaseQuality\ConstraintReport\ConstraintParameterRenderer;
use WikibaseQuality\ConstraintReport\Role;
use Wikibase\DataModel\Statement\Statement;

/**
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class SymmetricChecker implements ConstraintChecker {

	/**
	 * @var EntityLookup
	 */
	private $entityLookup;

	/**
	 * @var ConnectionCheckerHelper
	 */
	private $connectionCheckerHelper;

	/**
	 * @var ConstraintParameterRenderer
	 */
	private $constraintParameterRenderer;

	/**
	 * @param EntityLookup $lookup
	 * @param ConnectionCheckerHelper $connectionCheckerHelper
	 * @param ConstraintParameterRenderer $constraintParameterRenderer
	 */
	public function __construct(
		EntityLookup $lookup,
		ConnectionCheckerHelper $connectionCheckerHelper,
		ConstraintParameterRenderer $constraintParameterRenderer
	) {
		$this->entityLookup = $lookup;
		$this->connectionCheckerHelper = $connectionCheckerHelper;
		$this->constraintParameterRenderer = $constraintParameterRenderer;
	}

	/**
	 * Checks 'Symmetric' constraint.
	 *
	 * @param Context $context
	 * @param Constraint $constraint
	 *
	 * @return CheckResult
	 */
	public function checkConstraint( Context $context, Constraint $constraint ) {
		if ( $context->getSnakRank() === Statement::RANK_DEPRECATED ) {
			return new CheckResult( $context, $constraint, [], CheckResult::STATUS_DEPRECATED );
		}
		if ( $context->getType() !== Context::TYPE_STATEMENT ) {
			return new CheckResult( $context, $constraint, [], CheckResult::STATUS_NOT_MAIN_SNAK );
		}

		$parameters = [];

		$snak = $context->getSnak();
		$propertyId = $context->getSnak()->getPropertyId();

		if ( !$snak instanceof PropertyValueSnak ) {
			// nothing to check
			return new CheckResult( $context, $constraint, $parameters, CheckResult::STATUS_COMPLIANCE, '' );
		}

		$dataValue = $snak->getDataValue();

		/*
		 * error handling:
		 *   type of $dataValue for properties with 'Symmetric' constraint has to be 'wikibase-entityid'
		 */
		if ( $dataValue->getType() !== 'wikibase-entityid' ) {
			$message = wfMessage( "wbqc-violation-message-value-needed-of-type" )
					 ->rawParams(
						 $this->constraintParameterRenderer->formatItemId( $constraint->getConstraintTypeItemId(), Role::CONSTRAINT_TYPE_ITEM ),
						 'wikibase-entityid' // TODO is there a message for this type so we can localize it?
					 )
					 ->escaped();
			return new CheckResult( $context, $constraint, $parameters, CheckResult::STATUS_VIOLATION, $message );
		}
		/** @var EntityIdValue $dataValue */

		$targetEntityId = $dataValue->getEntityId();
		$targetEntity = $this->entityLookup->getEntity( $targetEntityId );
		if ( $targetEntity === null ) {
			$message = wfMessage( "wbqc-violation-message-target-entity-must-exist" )->escaped();
			return new CheckResult( $context, $constraint, $parameters, CheckResult::STATUS_VIOLATION, $message );
		}

		$symmetricStatement = $this->connectionCheckerHelper->findStatementWithPropertyAndEntityIdValue(
			$targetEntity->getStatements(),
			$propertyId,
			$context->getEntity()->getId()
		);
		if ( $symmetricStatement !== null ) {
			$message = '';
			$status = CheckResult::STATUS_COMPLIANCE;
		} else {
			$message = wfMessage( 'wbqc-violation-message-symmetric' )
					 ->rawParams(
						 $this->constraintParameterRenderer->formatEntityId( $targetEntityId, Role::SUBJECT ),
						 $this->constraintParameterRenderer->formatEntityId( $propertyId, Role::PREDICATE ),
						 $this->constraintParameterRenderer->formatEntityId( $context->getEntity()->getId(), Role::OBJECT )
					 )
					 ->escaped();
			$status = CheckResult::STATUS_VIOLATION;
		}

		return ( new CheckResult( $context, $constraint, $parameters, $status, $message ) )
			->withMetadata( Metadata::ofDependencyMetadata(
				DependencyMetadata::ofEntityId( $targetEntityId ) ) );
	}

	public function checkConstraintParameters( Constraint $constraint ) {
		// no parameters
		return [];
	}

}
