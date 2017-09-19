<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Checker;

use Config;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\DataModel\Statement\StatementListProvider;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\Context;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterException;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\SparqlHelperException;
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
	private $constraintParameterParser;

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
	 * @param ConstraintParameterParser $constraintParameterParser
	 * @param TypeCheckerHelper $typeCheckerHelper
	 * @param Config $config
	 */
	public function __construct(
		EntityLookup $lookup,
		ConstraintParameterParser $constraintParameterParser,
		TypeCheckerHelper $typeCheckerHelper,
		Config $config
	) {
		$this->entityLookup = $lookup;
		$this->constraintParameterParser = $constraintParameterParser;
		$this->typeCheckerHelper = $typeCheckerHelper;
		$this->config = $config;
	}

	/**
	 * Checks 'Type' constraint.
	 *
	 * @param Context $context
	 * @param Constraint $constraint
	 *
	 * @return CheckResult
	 *
	 * @throws SparqlHelperException if the checker uses SPARQL and the query times out or some other error occurs
	 */
	public function checkConstraint( Context $context, Constraint $constraint ) {
		if ( $context->getSnakRank() === Statement::RANK_DEPRECATED ) {
			return new CheckResult( $context, $constraint, [], CheckResult::STATUS_DEPRECATED );
		}
		if ( $context->getType() !== Context::TYPE_STATEMENT ) {
			return new CheckResult( $context, $constraint, [], CheckResult::STATUS_NOT_MAIN_SNAK );
		}

		$parameters = [];
		$constraintParameters = $constraint->getConstraintParameters();

		$classes = $this->constraintParameterParser->parseClassParameter( $constraintParameters, $constraint->getConstraintTypeItemId() );
		$parameters['class'] = array_map(
			function( $id ) {
				return new ItemId( $id );
			},
			$classes
		);

		$relation = $this->constraintParameterParser->parseRelationParameter( $constraintParameters, $constraint->getConstraintTypeItemId() );
		if ( $relation === 'instance' ) {
			$relationId = $this->config->get( 'WBQualityConstraintsInstanceOfId' );
		} elseif ( $relation === 'subclass' ) {
			$relationId = $this->config->get( 'WBQualityConstraintsSubclassOfId' );
		}
		$parameters['relation'] = [ $relation ];

		if ( $this->typeCheckerHelper->hasClassInRelation( $context->getEntity()->getStatements(), $relationId, $classes ) ) {
			$message = '';
			$status = CheckResult::STATUS_COMPLIANCE;
		} else {
			$message = $this->typeCheckerHelper->getViolationMessage(
				$context->getSnak()->getPropertyId(),
				$context->getEntity()->getId(),
				$classes,
				'type',
				$relation
			);
			$status = CheckResult::STATUS_VIOLATION;
		}

		return new CheckResult( $context, $constraint, $parameters, $status, $message );
	}

	public function checkConstraintParameters( Constraint $constraint ) {
		$constraintParameters = $constraint->getConstraintParameters();
		$exceptions = [];
		try {
			$this->constraintParameterParser->parseClassParameter( $constraintParameters, $constraint->getConstraintTypeItemId() );
		} catch ( ConstraintParameterException $e ) {
			$exceptions[] = $e;
		}
		try {
			$this->constraintParameterParser->parseRelationParameter( $constraintParameters, $constraint->getConstraintTypeItemId() );
		} catch ( ConstraintParameterException $e ) {
			$exceptions[] = $e;
		}
		return $exceptions;
	}

}
