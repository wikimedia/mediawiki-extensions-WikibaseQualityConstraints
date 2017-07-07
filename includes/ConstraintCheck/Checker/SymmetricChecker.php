<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Checker;

use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\StatementListProvider;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConnectionCheckerHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikibaseQuality\ConstraintReport\ConstraintParameterRenderer;
use Wikibase\DataModel\Statement\Statement;

/**
 * @package WikibaseQuality\ConstraintReport\ConstraintCheck\Checker
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
	 * @param Statement $statement
	 * @param Constraint $constraint
	 * @param EntityDocument|StatementListProvider $entity
	 *
	 * @return CheckResult
	 */
	public function checkConstraint( Statement $statement, Constraint $constraint, EntityDocument $entity ) {
		$parameters = [];

		$mainSnak = $statement->getMainSnak();
		$propertyId = $statement->getPropertyId();

		/*
		 * error handling:
		 *   $mainSnak must be PropertyValueSnak, neither PropertySomeValueSnak nor PropertyNoValueSnak is allowed
		 */
		if ( !$mainSnak instanceof PropertyValueSnak ) {
			$message = wfMessage( "wbqc-violation-message-value-needed" )
					 ->rawParams( $this->constraintParameterRenderer->formatItemId( $constraint->getConstraintTypeItemId(), ConstraintParameterRenderer::ROLE_CONSTRAINT_TYPE_ITEM ) )
					 ->escaped();
			return new CheckResult( $entity->getId(), $statement, $constraint, $parameters, CheckResult::STATUS_VIOLATION, $message );
		}

		$dataValue = $mainSnak->getDataValue();

		/*
		 * error handling:
		 *   type of $dataValue for properties with 'Symmetric' constraint has to be 'wikibase-entityid'
		 */
		if ( $dataValue->getType() !== 'wikibase-entityid' ) {
			$message = wfMessage( "wbqc-violation-message-value-needed-of-type" )
					 ->rawParams(
						 $this->constraintParameterRenderer->formatItemId( $constraint->getConstraintTypeItemId(), ConstraintParameterRenderer::ROLE_CONSTRAINT_TYPE_ITEM ),
						 'wikibase-entityid' // TODO is there a message for this type so we can localize it?
					 )
					 ->escaped();
			return new CheckResult( $entity->getId(), $statement, $constraint, $parameters, CheckResult::STATUS_VIOLATION, $message );
		}
		/** @var EntityIdValue $dataValue */

		$targetItem = $this->entityLookup->getEntity( $dataValue->getEntityId() );
		if ( $targetItem === null ) {
			$message = wfMessage( "wbqc-violation-message-target-entity-must-exist" )->escaped();
			return new CheckResult( $entity->getId(), $statement, $constraint, $parameters, CheckResult::STATUS_VIOLATION, $message );
		}
		$targetItemStatementList = $targetItem->getStatements();

		if ( $this->connectionCheckerHelper->findStatement( $targetItemStatementList, $propertyId->getSerialization(), $entity->getId()->getSerialization() ) !== null ) {
			$message = '';
			$status = CheckResult::STATUS_COMPLIANCE;
		} else {
			$message = wfMessage( 'wbqc-violation-message-symmetric' )
					 ->rawParams(
						 $this->constraintParameterRenderer->formatEntityId( $targetItem->getId(), ConstraintParameterRenderer::ROLE_SUBJECT ),
						 $this->constraintParameterRenderer->formatEntityId( $propertyId, ConstraintParameterRenderer::ROLE_PREDICATE ),
						 $this->constraintParameterRenderer->formatEntityId( $entity->getId(), ConstraintParameterRenderer::ROLE_OBJECT )
					 )
					 ->escaped();
			$status = CheckResult::STATUS_VIOLATION;
		}

		return new CheckResult( $entity->getId(), $statement, $constraint, $parameters, $status, $message );
	}

	public function checkConstraintParameters( Constraint $constraint ) {
		// no parameters
		return [];
	}

}
