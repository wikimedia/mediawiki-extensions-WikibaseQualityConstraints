<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Checker;

use InvalidArgumentException;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\EntityId\EntityIdFormatter;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\DataModel\Statement\StatementListProvider;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConnectionCheckerHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use Wikibase\DataModel\Statement\Statement;

/**
 * @package WikibaseQuality\ConstraintReport\ConstraintCheck\Checker
 * @author BP2014N1
 * @license GNU GPL v2+
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

	/**
	 * @var EntityIdFormatter
	 */
	private $entityIdFormatter;

	/**
	 * @param EntityLookup $lookup
	 * @param ConstraintParameterParser $helper
	 * @param ConnectionCheckerHelper $connectionCheckerHelper
	 * @param EntityIdFormatter $entityIdFormatter
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
	 * Checks 'Conflicts with' constraint.
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

		/*
		 * error handling:
		 *   parameter $property must not be null
		 */
		if ( !array_key_exists( 'property', $constraintParameters ) ) {
			$message = wfMessage( "wbqc-violation-message-parameter-needed" )->params( $constraint->getConstraintTypeName(), 'property' )->escaped();
			return new CheckResult( $entity->getId(), $statement, $constraint->getConstraintTypeQid(), $constraint->getConstraintId(),  $parameters, CheckResult::STATUS_VIOLATION, $message );
		}

		$parameters['property'] = $this->constraintParameterParser->parseSingleParameter( $constraintParameters['property'] );
		if ( array_key_exists( 'item', $constraintParameters ) ) {
			$parameters['item'] = $this->constraintParameterParser->parseParameterArray( explode( ',', $constraintParameters[ 'item' ] ) );
		};

		try {
			$conflictingProperty = $this->entityIdFormatter->formatEntityId( new PropertyId( $constraintParameters['property'] ) );
		} catch ( InvalidArgumentException $e ) {
			$conflictingProperty = htmlspecialchars( $constraintParameters['property'] );
		}

		/*
		 * 'Conflicts with' can be defined with
		 *   a) a property only
		 *   b) a property and a number of items (each combination of property and item forming an individual claim)
		 */
		if ( !array_key_exists( 'item', $constraintParameters ) ) {
			if ( $this->connectionCheckerHelper->hasProperty( $entity->getStatements(), $constraintParameters['property'] ) ) {
				$message = wfMessage( "wbqc-violation-message-conflicts-with-property" )
						 ->rawParams(
							 $this->entityIdFormatter->formatEntityId( $statement->getPropertyId() ),
							 $conflictingProperty
						 )
						 ->escaped();
				$status = CheckResult::STATUS_VIOLATION;
			} else {
				$message = '';
				$status = CheckResult::STATUS_COMPLIANCE;
			}
		} else {
			$result = $this->connectionCheckerHelper->findStatement( $entity->getStatements(), $constraintParameters['property'], explode( ',', $constraintParameters['item'] ) );
			if ( $result !== null ) {
				$message = wfMessage( "wbqc-violation-message-conflicts-with-claim" )
						 ->rawParams(
							 $this->entityIdFormatter->formatEntityId( $statement->getPropertyId() ),
							 $conflictingProperty,
							 $this->entityIdFormatter->formatEntityId( $result )
						 )
						 ->escaped();
				$status = CheckResult::STATUS_VIOLATION;
			} else {
				$message = '';
				$status = CheckResult::STATUS_COMPLIANCE;
			}
		}

		return new CheckResult( $entity->getId(), $statement, $constraint->getConstraintTypeQid(), $constraint->getConstraintId(),  $parameters, $status, $message );
	}

}
