<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Checker;

use InvalidArgumentException;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\EntityId\EntityIdFormatter;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\StatementListProvider;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConnectionCheckerHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use Wikibase\DataModel\Statement\Statement;

/**
 * @package WikibaseQuality\ConstraintReport\ConstraintCheck\Checker
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class InverseChecker implements ConstraintChecker {

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
	 * @var EntityIdFormatter
	 */
	private $entityIdFormatter;

	/**
	 * @param EntityLookup $lookup
	 * @param ConstraintParameterParser $helper
	 * @param ConnectionCheckerHelper $connectionCheckerHelper
	 * @param EntityIdFormatter $entityIdFormatter should return HTML
	 */
	public function __construct(
		EntityLookup $lookup,
		ConstraintParameterParser $helper,
		ConnectionCheckerHelper $connectionCheckerHelper,
		EntityIdFormatter $entityIdFormatter
	) {
		$this->entityLookup = $lookup;
		$this->constraintParameterParser = $helper;
		$this->connectionCheckerHelper = $connectionCheckerHelper;
		$this->entityIdFormatter = $entityIdFormatter;
	}

	/**
	 * Checks 'Inverse' constraint.
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

		if ( array_key_exists( 'property', $constraintParameters ) ) {
			$parameters['property'] = $this->constraintParameterParser->parseSingleParameter( $constraintParameters['property'] );
		};

		if ( array_key_exists( 'constraint_status', $constraintParameters ) ) {
			$parameters['constraint_status'] = $this->constraintParameterParser->parseSingleParameter( $constraintParameters['constraint_status'], true );
		}

		$mainSnak = $statement->getMainSnak();

		/*
		 * error handling:
		 *   $mainSnak must be PropertyValueSnak, neither PropertySomeValueSnak nor PropertyNoValueSnak is allowed
		 */
		if ( !$mainSnak instanceof PropertyValueSnak ) {
			$message = wfMessage( "wbqc-violation-message-value-needed" )->escaped();
			return new CheckResult( $entity->getId(), $statement, $constraint->getConstraintTypeQid(), $constraint->getConstraintId(), $parameters, CheckResult::STATUS_VIOLATION, $message );
		}

		$dataValue = $mainSnak->getDataValue();

		/*
		 * error handling:
		 *   type of $dataValue for properties with 'Inverse' constraint has to be 'wikibase-entityid'
		 *   parameter $property must not be null
		 */
		if ( $dataValue->getType() !== 'wikibase-entityid' ) {
			$message = wfMessage( "wbqc-violation-message-value-needed-of-type" )->params( $constraint->getConstraintTypeName(), 'wikibase-entityid' )->escaped();
			return new CheckResult( $entity->getId(), $statement, $constraint->getConstraintTypeQid(), $constraint->getConstraintId(),  $parameters, CheckResult::STATUS_VIOLATION, $message );
		}
		/** @var EntityIdValue $dataValue */

		if ( !array_key_exists( 'property', $constraintParameters ) ) {
			$message = wfMessage( "wbqc-violation-message-property-needed" )->params( $constraint->getConstraintTypeName(), 'property' )->escaped();
			return new CheckResult( $entity->getId(), $statement, $constraint->getConstraintTypeQid(), $constraint->getConstraintId(), $parameters, CheckResult::STATUS_VIOLATION, $message );
		}

		$property = $constraintParameters['property'];
		$targetItem = $this->entityLookup->getEntity( $dataValue->getEntityId() );
		if ( $targetItem === null ) {
			$message = wfMessage( "wbqc-violation-message-target-entity-must-exist" )->escaped();
			return new CheckResult( $entity->getId(), $statement, $constraint->getConstraintTypeQid(), $constraint->getConstraintId(), $parameters, CheckResult::STATUS_VIOLATION, $message );
		}
		$targetItemStatementList = $targetItem->getStatements();

		if ( $this->connectionCheckerHelper->findStatement( $targetItemStatementList, $property, $entity->getId()->getSerialization() ) !== null ) {
			$message = '';
			$status = CheckResult::STATUS_COMPLIANCE;
		} else {
			try {
				$inverseProperty = $this->entityIdFormatter->formatEntityId( new PropertyId( $property ) );
			} catch ( InvalidArgumentException $e ) {
				$inverseProperty = htmlspecialchars( $property );
			}
			$message = wfMessage( 'wbqc-violation-message-inverse' )
					 ->rawParams(
						 $this->entityIdFormatter->formatEntityId( $targetItem->getId() ),
						 $inverseProperty,
						 $this->entityIdFormatter->formatEntityId( $entity->getId() )
					 )
					 ->escaped();
			$status = CheckResult::STATUS_VIOLATION;
		}

		return new CheckResult( $entity->getId(), $statement, $constraint->getConstraintTypeQid(), $constraint->getConstraintId(), $parameters, $status, $message );
	}

}
