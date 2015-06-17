<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Checker;

use Wikibase\Lib\Store\EntityLookup;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\TypeCheckerHelper;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Entity\Entity;


/**
 * Class TypeChecker.
 * Checks 'Type' constraint.
 *
 * @package WikibaseQuality\ConstraintReport\ConstraintCheck\Checker
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class TypeChecker implements ConstraintChecker {

	/**
	 * Class for helper functions for constraint checkers.
	 *
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

	const instanceId = 'P31';
	const subclassId = 'P279';

	/**
	 * @param EntityLookup $lookup
	 * @param ConstraintParameterParser $helper
	 * @param TypeCheckerHelper $typeCheckerHelper
	 */
	public function __construct( EntityLookup $lookup, ConstraintParameterParser $helper, TypeCheckerHelper $typeCheckerHelper ) {
		$this->entityLookup = $lookup;
		$this->helper = $helper;
		$this->typeCheckerHelper = $typeCheckerHelper;
	}

	/**
	 * Checks 'Type' constraint.
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
			$message = 'Properties with \'Type\' constraint need the parameter \'class\'.';
			return new CheckResult( $statement, $constraint->getConstraintTypeQid(), $parameters, CheckResult::STATUS_VIOLATION, $message );
		}

		/*
		 * error handling:
		 *   parameter $constraintParameters['relation'] must be either 'instance' or 'subclass'
		 */
		if ( $relation === 'instance' ) {
			$relationId = self::instanceId;
		} elseif ( $relation === 'subclass' ) {
			$relationId = self::subclassId;
		} else {
			$message = 'Parameter \'relation\' must be either \'instance\' or \'subclass\'.';
			return new CheckResult( $statement, $constraint->getConstraintTypeQid(), $parameters, CheckResult::STATUS_VIOLATION, $message );
		}

		if ( $this->typeCheckerHelper->hasClassInRelation( $entity->getStatements(), $relationId, $classes ) ) {
			$message = '';
			$status = CheckResult::STATUS_COMPLIANCE;
		} else {
			$message = 'This property must only be used on items that are in the relation to the item (or a subclass of the item) defined in the parameters.';
			$status = CheckResult::STATUS_VIOLATION;
		}

		return new CheckResult( $statement, $constraint->getConstraintTypeQid(), $parameters, $status, $message );
	}
}