<?php

namespace WikidataQuality\ConstraintReport\ConstraintCheck\Checker;

use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\Lib\Store\EntityLookup;
use WikidataQuality\ConstraintReport\Constraint;
use WikidataQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikidataQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Statement\StatementList;
use WikidataQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikidataQuality\ConstraintReport\ConstraintCheck\Helper\TypeCheckerHelper;
use Wikibase\DataModel\Entity\Entity;


/**
 * Class TypeChecker.
 * Checks 'Type' constraint.
 *
 * @package WikidataQuality\ConstraintReport\ConstraintCheck\Checker
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class TypeChecker implements ConstraintChecker {

	/**
	 * Class for helper functions for constraint checkers.
	 *
	 * @var ConstraintReportHelper
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

	const instanceId = 31;
	const subclassId = 279;

	/**
	 * @param EntityLookup $lookup
	 * @param ConstraintReportHelper $helper
	 * @param TypeCheckerHelper $typeCheckerHelper
	 */
	public function __construct( EntityLookup $lookup, ConstraintReportHelper $helper, TypeCheckerHelper $typeCheckerHelper ) {
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
		$constraintParameters = $constraint->getConstraintParameter();

		$parameters[ 'class' ] = $this->helper->parseParameterArray( $constraintParameters['class'] );
		$parameters[ 'relation' ] = $this->helper->parseSingleParameter( $constraintParameters['relation'][0] );

		/*
		 * error handling:
		 *   parameter $constraintParameters['class'] must not be null
		 */
		if ( $constraintParameters['class'][0] === '' ) {
			$message = 'Properties with \'Type\' constraint need the parameter \'class\'.';
			return new CheckResult( $statement, $constraint->getConstraintTypeQid(), $parameters, CheckResult::STATUS_VIOLATION, $message );
		}

		/*
		 * error handling:
		 *   parameter $constraintParameters['relation'] must be either 'instance' or 'subclass'
		 */
		if ( $constraintParameters['relation'][0] === 'instance' ) {
			$relationId = self::instanceId;
		} elseif ( $constraintParameters['relation'][0] === 'subclass' ) {
			$relationId = self::subclassId;
		} else {
			$message = 'Parameter \'relation\' must be either \'instance\' or \'subclass\'.';
			return new CheckResult( $statement, $constraint->getConstraintTypeQid(), $parameters, CheckResult::STATUS_VIOLATION, $message );
		}

		if ( $this->typeCheckerHelper->hasClassInRelation( $entity->getStatements(), $relationId, $constraintParameters['class']  ) ) {
			$message = '';
			$status = CheckResult::STATUS_COMPLIANCE;
		} else {
			$message = 'This property must only be used on items that are in the relation to the item (or a subclass of the item) defined in the parameters.';
			$status = CheckResult::STATUS_VIOLATION;
		}

		return new CheckResult( $statement, $constraint->getConstraintTypeQid(), $parameters, $status, $message );
	}
}