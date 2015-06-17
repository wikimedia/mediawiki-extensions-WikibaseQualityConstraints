<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Checker;

use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\Lib\Store\EntityLookup;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConnectionCheckerHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Entity\Entity;


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
	 * @param EntityLookup $lookup
	 * @param ConstraintParameterParser $helper
	 * @param ConnectionCheckerHelper $connectionCheckerHelper
	 */
	public function __construct( EntityLookup $lookup, ConstraintParameterParser $helper, ConnectionCheckerHelper $connectionCheckerHelper ) {
		$this->entityLookup = $lookup;
		$this->constraintParameterParser = $helper;
		$this->connectionCheckerHelper = $connectionCheckerHelper;
	}

	/**
	 * Checks 'Target required claim' constraint.
	 *
	 * @param Statement $statement
	 * @param Constraint $constraint
	 * @param Entity $entity
	 *
	 * @return CheckResult
	 */
	public function checkConstraint( Statement $statement, Constraint $constraint, Entity $entity = null ) {
		$constraintName = 'Target required claim';
		$parameters = array ();
		$constraintParameters = $constraint->getConstraintParameters();

		$property = false;
		if ( array_key_exists( 'property', $constraintParameters ) ) {
			$property = $constraintParameters['property'];
			$parameters['property'] = $this->constraintParameterParser->parseSingleParameter( $property );
		}

		$items = false;
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
			$message = wfMessage( "wbqc-violation-message-value-needed" )->params( $constraintName )->escaped();
			return new CheckResult( $statement, $constraint->getConstraintTypeQid(), $parameters, CheckResult::STATUS_VIOLATION, $message );
		}

		$dataValue = $mainSnak->getDataValue();

		/*
		 * error handling:
		 *   type of $dataValue for properties with 'Target required claim' constraint has to be 'wikibase-entityid'
		 *   parameter $property must not be null
		 */
		if ( $dataValue->getType() !== 'wikibase-entityid' ) {
			$message = wfMessage( "wbqc-violation-message-value-needed-of-type" )->params( $constraintName, 'wikibase-entityid' )->escaped();
			return new CheckResult( $statement, $constraint->getConstraintTypeQid(), $parameters, CheckResult::STATUS_VIOLATION, $message );
		}
		if ( !$property ) {
			$message = wfMessage( "wbqc-violation-message-property-needed" )->params( $constraintName, 'property' )->escaped();
			return new CheckResult( $statement, $constraint->getConstraintTypeQid(), $parameters, CheckResult::STATUS_VIOLATION, $message );
		}

		$targetEntity = $this->entityLookup->getEntity( $dataValue->getEntityId() );
		if ( $targetEntity === null ) {
			$message = wfMessage( "wbqc-violation-message-target-entity-must-exist" )->escaped();
			return new CheckResult( $statement, $constraint->getConstraintTypeQid(), $parameters, CheckResult::STATUS_VIOLATION, $message );
		}
		$targetEntityStatementList = $targetEntity->getStatements();

		/*
		 * 'Target required claim' can be defined with
		 *   a) a property only
		 *   b) a property and a number of items (each combination forming an individual claim)
		 */
		if ( !$items ) {
			if ( $this->connectionCheckerHelper->hasProperty( $targetEntityStatementList, $property ) ) {
				$message = '';
				$status = CheckResult::STATUS_COMPLIANCE;
			} else {
				$message = wfMessage( "wbqc-violation-message-target-required-claim-property" )->escaped();
				$status = CheckResult::STATUS_VIOLATION;
			}
		} else {
			if ( $this->connectionCheckerHelper->hasClaim( $targetEntityStatementList, $property, $items ) ) {
				$message = '';
				$status = CheckResult::STATUS_COMPLIANCE;
			} else {
				$message = wfMessage( "wbqc-violation-message-target-required-claim-claim" )->escaped();
				$status = CheckResult::STATUS_VIOLATION;
			}
		}

		return new CheckResult( $statement, $constraint->getConstraintTypeQid(), $parameters, $status, $message );
	}

}