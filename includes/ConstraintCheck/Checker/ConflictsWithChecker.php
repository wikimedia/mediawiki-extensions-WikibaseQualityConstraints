<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Checker;

use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\DataModel\Statement\StatementListProvider;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConnectionCheckerHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikibaseQuality\ConstraintReport\ConstraintParameterRenderer;
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
	 * @var ConstraintParameterRenderer
	 */
	private $constraintParameterRenderer;

	/**
	 * @param EntityLookup $lookup
	 * @param ConstraintParameterParser $constraintParameterParser
	 * @param ConnectionCheckerHelper $connectionCheckerHelper
	 * @param ConstraintParameterRenderer $constraintParameterRenderer
	 */
	public function __construct(
		EntityLookup $lookup,
		ConstraintParameterParser $constraintParameterParser,
		ConnectionCheckerHelper $connectionCheckerHelper,
		ConstraintParameterRenderer $constraintParameterRenderer
	) {
		$this->entityLookup = $lookup;
		$this->constraintParameterParser = $constraintParameterParser;
		$this->connectionCheckerHelper = $connectionCheckerHelper;
		$this->constraintParameterRenderer = $constraintParameterRenderer;
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

		$propertyId = $this->constraintParameterParser->parsePropertyParameter( $constraintParameters, $constraint->getConstraintTypeItemId() );
		$parameters['property'] = [ $propertyId ];

		$items = $this->constraintParameterParser->parseItemsParameter( $constraintParameters, $constraint->getConstraintTypeItemId(), false );
		$parameters['items'] = $items;

		/*
		 * 'Conflicts with' can be defined with
		 *   a) a property only
		 *   b) a property and a number of items (each combination of property and item forming an individual claim)
		 */
		if ( $items === [] ) {
			if ( $this->connectionCheckerHelper->hasProperty( $entity->getStatements(), $propertyId->getSerialization() ) ) {
				$message = wfMessage( "wbqc-violation-message-conflicts-with-property" )
						 ->rawParams(
							 $this->constraintParameterRenderer->formatEntityId( $statement->getPropertyId(), ConstraintParameterRenderer::ROLE_CONSTRAINT_PROPERTY ),
							 $this->constraintParameterRenderer->formatEntityId( $propertyId, ConstraintParameterRenderer::ROLE_PREDICATE )
						 )
						 ->escaped();
				$status = CheckResult::STATUS_VIOLATION;
			} else {
				$message = '';
				$status = CheckResult::STATUS_COMPLIANCE;
			}
		} else {
			$result = $this->connectionCheckerHelper->findStatement( $entity->getStatements(), $propertyId->getSerialization(), $items );
			if ( $result !== null ) {
				$message = wfMessage( "wbqc-violation-message-conflicts-with-claim" )
						 ->rawParams(
							 $this->constraintParameterRenderer->formatEntityId( $statement->getPropertyId(), ConstraintParameterRenderer::ROLE_CONSTRAINT_PROPERTY ),
							 $this->constraintParameterRenderer->formatEntityId( $propertyId, ConstraintParameterRenderer::ROLE_PREDICATE ),
							 $this->constraintParameterRenderer->formatItemIdSnakValue( $result, ConstraintParameterRenderer::ROLE_OBJECT )
						 )
						 ->escaped();
				$status = CheckResult::STATUS_VIOLATION;
			} else {
				$message = '';
				$status = CheckResult::STATUS_COMPLIANCE;
			}
		}

		return new CheckResult( $entity->getId(), $statement, $constraint,  $parameters, $status, $message );
	}

}
