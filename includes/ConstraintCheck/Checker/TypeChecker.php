<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Checker;

use Config;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\DataModel\Statement\StatementListProvider;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\TypeCheckerHelper;
use Wikibase\DataModel\Statement\Statement;

/**
 * @package WikibaseQuality\ConstraintReport\ConstraintCheck\Checker
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class TypeChecker implements ConstraintChecker {

	/**
	 * @var ConstraintParameterParser
	 */
	private $helper;

	/**
	 * @var EntityLookup
	 */
	private $entityLookup;

	/**
	 * @var TypeCheckerHelper
	 */
	private $typeCheckerHelper;

	/**
	 * @var Config
	 */
	private $config;

	/**
	 * @param EntityLookup $lookup
	 * @param ConstraintParameterParser $helper
	 * @param TypeCheckerHelper $typeCheckerHelper
	 * @param Config $config
	 */
	public function __construct( EntityLookup $lookup, ConstraintParameterParser $helper, TypeCheckerHelper $typeCheckerHelper, Config $config ) {
		$this->entityLookup = $lookup;
		$this->helper = $helper;
		$this->typeCheckerHelper = $typeCheckerHelper;
		$this->config = $config;
	}

	/**
	 * Checks 'Type' constraint.
	 *
	 * @param Statement $statement
	 * @param Constraint $constraint
	 * @param EntityDocument|StatementListProvider $entity
	 *
	 * @return CheckResult
	 */
	public function checkConstraint( Statement $statement, Constraint $constraint, EntityDocument $entity ) {
		$parameters = array ();
		$constraintParameters = $constraint->getConstraintParameters();

		$classes = false;
		if ( array_key_exists( 'class', $constraintParameters ) ) {
			$classes = explode( ',', $constraintParameters['class'] );
			$parameters['class'] = $this->helper->parseParameterArray( $classes );
		}

		$relation = false;
		if ( array_key_exists( 'relation', $constraintParameters ) ) {
			$relation = $constraintParameters['relation'];
			$parameters['relation'] = $this->helper->parseSingleParameter( $relation, true );
		}

		/*
		 * error handling:
		 *   parameter $constraintParameters['class'] must not be null
		 */
		if ( !$classes ) {
			$message = wfMessage( "wbqc-violation-message-parameter-needed" )->params( $constraint->getConstraintTypeName(), 'class' )->escaped();
			return new CheckResult( $entity->getId(), $statement, $constraint->getConstraintTypeQid(), $constraint->getConstraintId(), $parameters, CheckResult::STATUS_VIOLATION, $message );
		}

		/*
		 * error handling:
		 *   parameter $constraintParameters['relation'] must be either 'instance' or 'subclass'
		 */
		if ( $relation === 'instance' ) {
			$relationId = $this->config->get( 'WBQualityConstraintsInstanceOfId' );
		} elseif ( $relation === 'subclass' ) {
			$relationId = $this->config->get( 'WBQualityConstraintsSubclassOfId' );
		} else {
			$message = wfMessage( "wbqc-violation-message-type-relation-instance-or-subclass" )->escaped();
			return new CheckResult( $entity->getId(), $statement, $constraint->getConstraintTypeQid(), $constraint->getConstraintId(), $parameters, CheckResult::STATUS_VIOLATION, $message );
		}

		if ( $this->typeCheckerHelper->hasClassInRelation( $entity->getStatements(), $relationId, $classes ) ) {
			$message = '';
			$status = CheckResult::STATUS_COMPLIANCE;
		} else {
			$message = $this->typeCheckerHelper->getViolationMessage(
				$statement->getPropertyId(),
				$entity->getId(),
				$classes,
				'type',
				$relation
			);
			$status = CheckResult::STATUS_VIOLATION;
		}

		return new CheckResult( $entity->getId(), $statement, $constraint->getConstraintTypeQid(), $constraint->getConstraintId(), $parameters, $status, $message );
	}

}
