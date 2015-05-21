<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Checker;

use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\Lib\Store\EntityLookup;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConnectionCheckerHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Entity\Entity;


/**
 * Class ConnectionChecker.
 * Checks 'Target required claim' constraints.
 *
 * @package WikibaseQuality\ConstraintReport\ConstraintCheck\Checker
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class TargetRequiredClaimChecker implements ConstraintChecker {


	/**
	 * Wikibase entity lookup.
	 *
	 * @var EntityLookup
	 */
	private $entityLookup;

	/**
	 * Class for helper functions for constraint checkers.
	 *
	 * @var ConstraintReportHelper
	 */
	private $constraintReportHelper;

	/**
	 * @var ConnectionCheckerHelper
	 */
	private $connectionCheckerHelper;

	/**
	 * @param EntityLookup $lookup
	 * @param ConstraintReportHelper $helper
	 * @param ConnectionCheckerHelper $connectionCheckerHelper
	 */
	public function __construct( EntityLookup $lookup, ConstraintReportHelper $helper, ConnectionCheckerHelper $connectionCheckerHelper ) {
		$this->entityLookup = $lookup;
		$this->constraintReportHelper = $helper;
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
		$parameters = array ();
		$constraintParameters = $constraint->getConstraintParameters();

		$property = false;
		if ( array_key_exists( 'property', $constraintParameters ) ) {
			$property = $constraintParameters['property'];
			$parameters['property'] = $this->constraintReportHelper->parseSingleParameter( $property );
		}

		$items = false;
		if ( array_key_exists( 'item', $constraintParameters ) ) {
			$items = explode( ',', $constraintParameters['item'] );
			$parameters['item'] = $this->constraintReportHelper->parseParameterArray( $items );
		}

		if ( array_key_exists( 'constraint_status', $constraintParameters ) ) {
			$parameters[ 'constraint_status' ] = $this->helper->parseSingleParameter( $constraintParameters['constraint_status'], true );
		}

		$mainSnak = $statement->getClaim()->getMainSnak();

		/*
		 * error handling:
		 *   $mainSnak must be PropertyValueSnak, neither PropertySomeValueSnak nor PropertyNoValueSnak is allowed
		 */
		if ( !$mainSnak instanceof PropertyValueSnak ) {
			$message = 'Properties with \'Target required claim\' constraint need to have a value.';
			return new CheckResult( $statement, $constraint->getConstraintTypeQid(), $parameters, CheckResult::STATUS_VIOLATION, $message );
		}

		$dataValue = $mainSnak->getDataValue();

		/*
		 * error handling:
		 *   type of $dataValue for properties with 'Target required claim' constraint has to be 'wikibase-entityid'
		 *   parameter $property must not be null
		 */
		if ( $dataValue->getType() !== 'wikibase-entityid' ) {
			$message = 'Properties with \'Target required claim\' constraint need to have values of type \'wikibase-entityid\'.';
			return new CheckResult( $statement, $constraint->getConstraintTypeQid(), $parameters, CheckResult::STATUS_VIOLATION, $message );
		}
		if ( !$property ) {
			$message = 'Properties with \'Target required claim\' constraint need a parameter \'property\'.';
			return new CheckResult( $statement, $constraint->getConstraintTypeQid(), $parameters, CheckResult::STATUS_VIOLATION, $message );
		}

		$targetEntity = $this->entityLookup->getEntity( $dataValue->getEntityId() );
		if ( $targetEntity === null ) {
			$message = 'Target entity does not exist.';
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
				$message = 'This property must only be used when there is a statement on its value entity using the property defined in the parameters.';
				$status = CheckResult::STATUS_VIOLATION;
			}
		} else {
			if ( $this->connectionCheckerHelper->hasClaim( $targetEntityStatementList, $property, $items ) ) {
				$message = '';
				$status = CheckResult::STATUS_COMPLIANCE;
			} else {
				$message = 'This property must only be used when there is a statement on its value entity using the property with one of the values defined in the parameters.';
				$status = CheckResult::STATUS_VIOLATION;
			}
		}

		return new CheckResult( $statement, $constraint->getConstraintTypeQid(), $parameters, $status, $message );
	}
}