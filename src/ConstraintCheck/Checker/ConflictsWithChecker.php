<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Checker;

use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\DataModel\Statement\StatementList;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\Context;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConnectionCheckerHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterException;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ItemIdSnakValue;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessage;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikibaseQuality\ConstraintReport\Role;
use Wikibase\DataModel\Statement\Statement;

/**
 * @author BP2014N1
 * @license GPL-2.0-or-later
 */
class ConflictsWithChecker implements ConstraintChecker {

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

	public function __construct(
		EntityLookup $lookup,
		ConstraintParameterParser $constraintParameterParser,
		ConnectionCheckerHelper $connectionCheckerHelper
	) {
		$this->entityLookup = $lookup;
		$this->constraintParameterParser = $constraintParameterParser;
		$this->connectionCheckerHelper = $connectionCheckerHelper;
	}

	/**
	 * @codeCoverageIgnore This method is purely declarative.
	 */
	public function getSupportedContextTypes() {
		return [
			Context::TYPE_STATEMENT => CheckResult::STATUS_COMPLIANCE,
			// TODO T175562
			Context::TYPE_QUALIFIER => CheckResult::STATUS_TODO,
			Context::TYPE_REFERENCE => CheckResult::STATUS_TODO,
		];
	}

	/**
	 * @codeCoverageIgnore This method is purely declarative.
	 */
	public function getDefaultContextTypes() {
		return [
			Context::TYPE_STATEMENT,
			// TODO T175562
			// Context::TYPE_QUALIFIER,
			// Context::TYPE_REFERENCE,
		];
	}

	/**
	 * Checks 'Conflicts with' constraint.
	 *
	 * @param Context $context
	 * @param Constraint $constraint
	 *
	 * @throws ConstraintParameterException
	 * @return CheckResult
	 */
	public function checkConstraint( Context $context, Constraint $constraint ) {
		if ( $context->getSnakRank() === Statement::RANK_DEPRECATED ) {
			return new CheckResult( $context, $constraint, [], CheckResult::STATUS_DEPRECATED );
		}

		$parameters = [];
		$constraintParameters = $constraint->getConstraintParameters();

		$propertyId = $this->constraintParameterParser->parsePropertyParameter( $constraintParameters, $constraint->getConstraintTypeItemId() );
		$parameters['property'] = [ $propertyId ];

		$items = $this->constraintParameterParser->parseItemsParameter( $constraintParameters, $constraint->getConstraintTypeItemId(), false );
		$parameters['items'] = $items;

		/** @var StatementList $statementList */
		$statementList = $context->getEntity()
			->getStatements()
			->getByRank( [ Statement::RANK_PREFERRED, Statement::RANK_NORMAL ] );

		/*
		 * 'Conflicts with' can be defined with
		 *   a) a property only
		 *   b) a property and a number of items (each combination of property and item forming an individual claim)
		 */
		if ( $items === [] ) {
			$offendingStatement = $this->connectionCheckerHelper->findStatementWithProperty(
				$statementList,
				$propertyId
			);
			if ( $offendingStatement !== null ) {
				$message = ( new ViolationMessage( 'wbqc-violation-message-conflicts-with-property' ) )
					->withEntityId( $context->getSnak()->getPropertyId(), Role::CONSTRAINT_PROPERTY )
					->withEntityId( $propertyId, Role::PREDICATE );
				$status = CheckResult::STATUS_VIOLATION;
			} else {
				$message = null;
				$status = CheckResult::STATUS_COMPLIANCE;
			}
		} else {
			$offendingStatement = $this->connectionCheckerHelper->findStatementWithPropertyAndItemIdSnakValues(
				$statementList,
				$propertyId,
				$items
			);
			if ( $offendingStatement !== null ) {
				$offendingValue = ItemIdSnakValue::fromSnak( $offendingStatement->getMainSnak() );
				$message = ( new ViolationMessage( 'wbqc-violation-message-conflicts-with-claim' ) )
					->withEntityId( $context->getSnak()->getPropertyId(), Role::CONSTRAINT_PROPERTY )
					->withEntityId( $propertyId, Role::PREDICATE )
					->withItemIdSnakValue( $offendingValue, Role::OBJECT );
				$status = CheckResult::STATUS_VIOLATION;
			} else {
				$message = null;
				$status = CheckResult::STATUS_COMPLIANCE;
			}
		}

		return new CheckResult( $context, $constraint, $parameters, $status, $message );
	}

	public function checkConstraintParameters( Constraint $constraint ) {
		$constraintParameters = $constraint->getConstraintParameters();
		$exceptions = [];
		try {
			$this->constraintParameterParser->parsePropertyParameter( $constraintParameters, $constraint->getConstraintTypeItemId() );
		} catch ( ConstraintParameterException $e ) {
			$exceptions[] = $e;
		}
		try {
			$this->constraintParameterParser->parseItemsParameter( $constraintParameters, $constraint->getConstraintTypeItemId(), false );
		} catch ( ConstraintParameterException $e ) {
			$exceptions[] = $e;
		}
		return $exceptions;
	}

}
