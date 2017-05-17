<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Checker;

use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\StatementListProvider;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConnectionCheckerHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikibaseQuality\ConstraintReport\ConstraintParameterRenderer;
use Wikibase\DataModel\Statement\Statement;

/**
 * @package WikibaseQuality\ConstraintReport\ConstraintCheck\Checker
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class TargetRequiredClaimChecker implements ConstraintChecker {

	/**
	 * @var EntityLookup
	 */
	private $entityLookup;

	/**
	 * @var ConstraintParameterParser
	 */
	private $constraintParameterParser;

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
	 * @param ConstraintParameterParser $helper
	 * @param ConnectionCheckerHelper $connectionCheckerHelper
	 * @param ConstraintParameterRenderer $constraintParameterRenderer
	 */
	public function __construct(
		EntityLookup $lookup,
		ConstraintParameterParser $helper,
		ConnectionCheckerHelper $connectionCheckerHelper,
		ConstraintParameterRenderer $constraintParameterRenderer
	) {
		$this->entityLookup = $lookup;
		$this->constraintParameterParser = $helper;
		$this->connectionCheckerHelper = $connectionCheckerHelper;
		$this->constraintParameterRenderer = $constraintParameterRenderer;
	}

	/**
	 * Checks 'Target required claim' constraint.
	 *
	 * @param Statement $statement
	 * @param Constraint $constraint
	 * @param EntityDocument|StatementListProvider $entity
	 *
	 * @return CheckResult
	 */
	public function checkConstraint( Statement $statement, Constraint $constraint, EntityDocument $entity ) {
		$parameters = [];
		$constraintParameters = $constraint->getConstraintParameters();

		$property = false;
		if ( array_key_exists( 'property', $constraintParameters ) ) {
			$property = $constraintParameters['property'];
			$parameters['property'] = $this->constraintParameterParser->parseSingleParameter( $property );
		}

		$items = [];
		if ( array_key_exists( 'item', $constraintParameters ) ) {
			$items = explode( ',', $constraintParameters['item'] );
			$parameters['item'] = $this->constraintParameterParser->parseParameterArray( $items );
		}

		if ( array_key_exists( 'constraint_status', $constraintParameters ) ) {
			$parameters['constraint_status'] = $this->constraintParameterParser->parseSingleParameter( $constraintParameters['constraint_status'], true );
		}

		$mainSnak = $statement->getMainSnak();

		/*
		 * error handling:
		 *   $mainSnak must be PropertyValueSnak, neither PropertySomeValueSnak nor PropertyNoValueSnak is allowed
		 */
		if ( !$mainSnak instanceof PropertyValueSnak ) {
			$message = wfMessage( "wbqc-violation-message-value-needed" )->params( $constraint->getConstraintTypeName() )->escaped();
			return new CheckResult( $entity->getId(), $statement, $constraint->getConstraintTypeQid(), $constraint->getConstraintId(), $parameters, CheckResult::STATUS_VIOLATION, $message );
		}

		$dataValue = $mainSnak->getDataValue();

		/*
		 * error handling:
		 *   type of $dataValue for properties with 'Target required claim' constraint has to be 'wikibase-entityid'
		 *   parameter $property must not be null
		 */
		if ( $dataValue->getType() !== 'wikibase-entityid' ) {
			$message = wfMessage( "wbqc-violation-message-value-needed-of-type" )->params( $constraint->getConstraintTypeName(), 'wikibase-entityid' )->escaped();
			return new CheckResult( $entity->getId(), $statement, $constraint->getConstraintTypeQid(), $constraint->getConstraintId(), $parameters, CheckResult::STATUS_VIOLATION, $message );
		}
		/** @var EntityIdValue $dataValue */

		if ( !$property ) {
			$message = wfMessage( "wbqc-violation-message-property-needed" )->params( $constraint->getConstraintTypeName(), 'property' )->escaped();
			return new CheckResult( $entity->getId(), $statement, $constraint->getConstraintTypeQid(), $constraint->getConstraintId(), $parameters, CheckResult::STATUS_VIOLATION, $message );
		}

		$targetEntityId = $dataValue->getEntityId();
		$targetEntity = $this->entityLookup->getEntity( $targetEntityId );
		if ( $targetEntity === null ) {
			$message = wfMessage( "wbqc-violation-message-target-entity-must-exist" )->escaped();
			return new CheckResult( $entity->getId(), $statement, $constraint->getConstraintTypeQid(), $constraint->getConstraintId(), $parameters, CheckResult::STATUS_VIOLATION, $message );
		}
		$targetEntityStatementList = $targetEntity->getStatements();

		/*
		 * 'Target required claim' can be defined with
		 *   a) a property only
		 *   b) a property and a number of items (each combination forming an individual claim)
		 */
		if ( !$items ) {
			if ( $this->connectionCheckerHelper->hasProperty( $targetEntityStatementList, $property ) ) {
				$status = CheckResult::STATUS_COMPLIANCE;
			} else {
				$status = CheckResult::STATUS_VIOLATION;
			}
		} else {
			if ( $this->connectionCheckerHelper->findStatement( $targetEntityStatementList, $property, $items ) !== null ) {
				$status = CheckResult::STATUS_COMPLIANCE;
			} else {
				$status = CheckResult::STATUS_VIOLATION;
			}
		}

		if ( $status == CheckResult::STATUS_COMPLIANCE ) {
			$message = '';
		} else {
			$message = wfMessage( 'wbqc-violation-message-target-required-claim' );
			$message->rawParams(
				$this->constraintParameterRenderer->formatItemId( $targetEntityId ),
				$this->constraintParameterRenderer->formatPropertyId( $property )
			);
			$message->numParams( count( $items ) );
			$message->rawParams( $this->constraintParameterRenderer->formatItemIdList( $items ) );
			$message = $message->escaped();
		}

		return new CheckResult( $entity->getId(), $statement, $constraint->getConstraintTypeQid(), $constraint->getConstraintId(), $parameters, $status, $message );
	}

}
