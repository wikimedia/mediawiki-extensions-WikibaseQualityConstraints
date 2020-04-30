<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Checker;

use Config;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Statement\Statement;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\Context;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterException;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\SparqlHelperException;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\TypeCheckerHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;

/**
 * @author BP2014N1
 * @license GPL-2.0-or-later
 */
class TypeChecker implements ConstraintChecker {

	/**
	 * @var ConstraintParameterParser
	 */
	private $constraintParameterParser;

	/**
	 * @var TypeCheckerHelper
	 */
	private $typeCheckerHelper;

	/**
	 * @var Config
	 */
	private $config;

	public function __construct(
		ConstraintParameterParser $constraintParameterParser,
		TypeCheckerHelper $typeCheckerHelper,
		Config $config
	) {
		$this->constraintParameterParser = $constraintParameterParser;
		$this->typeCheckerHelper = $typeCheckerHelper;
		$this->config = $config;
	}

	/**
	 * @codeCoverageIgnore This method is purely declarative.
	 */
	public function getSupportedContextTypes() {
		return [
			Context::TYPE_STATEMENT => CheckResult::STATUS_COMPLIANCE,
			Context::TYPE_QUALIFIER => CheckResult::STATUS_COMPLIANCE,
			Context::TYPE_REFERENCE => CheckResult::STATUS_COMPLIANCE,
		];
	}

	/**
	 * @codeCoverageIgnore This method is purely declarative.
	 */
	public function getDefaultContextTypes() {
		return [
			Context::TYPE_STATEMENT,
		];
	}

	/**
	 * Checks 'Type' constraint.
	 *
	 * @param Context $context
	 * @param Constraint $constraint
	 *
	 * @throws ConstraintParameterException
	 * @throws SparqlHelperException if the checker uses SPARQL and the query times out or some other error occurs
	 * @return CheckResult
	 */
	public function checkConstraint( Context $context, Constraint $constraint ) {
		if ( $context->getSnakRank() === Statement::RANK_DEPRECATED ) {
			return new CheckResult( $context, $constraint, [], CheckResult::STATUS_DEPRECATED );
		}
		if ( $context->getType() === Context::TYPE_REFERENCE ) {
			return new CheckResult( $context, $constraint, [], CheckResult::STATUS_NOT_IN_SCOPE );
		}

		$parameters = [];
		$constraintParameters = $constraint->getConstraintParameters();
		$constraintTypeItemId = $constraint->getConstraintTypeItemId();

		$classes = $this->constraintParameterParser->parseClassParameter(
			$constraintParameters,
			$constraintTypeItemId
		);
		$parameters['class'] = array_map(
			function( $id ) {
				return new ItemId( $id );
			},
			$classes
		);

		$relation = $this->constraintParameterParser->parseRelationParameter(
			$constraintParameters,
			$constraintTypeItemId
		);
		$relationIds = [];
		if ( $relation === 'instance' || $relation === 'instanceOrSubclass' ) {
			$relationIds[] = $this->config->get( 'WBQualityConstraintsInstanceOfId' );
		}
		if ( $relation === 'subclass' || $relation === 'instanceOrSubclass' ) {
			$relationIds[] = $this->config->get( 'WBQualityConstraintsSubclassOfId' );
		}
		$parameters['relation'] = [ $relation ];

		$result = $this->typeCheckerHelper->hasClassInRelation(
			$context->getEntity()->getStatements(),
			$relationIds,
			$classes
		);

		if ( $result->getBool() ) {
			$message = null;
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

		return ( new CheckResult( $context, $constraint, $parameters, $status, $message ) )
			->withMetadata( $result->getMetadata() );
	}

	public function checkConstraintParameters( Constraint $constraint ) {
		$constraintParameters = $constraint->getConstraintParameters();
		$constraintTypeItemId = $constraint->getConstraintTypeItemId();
		$exceptions = [];
		try {
			$this->constraintParameterParser->parseClassParameter(
				$constraintParameters,
				$constraintTypeItemId
			);
		} catch ( ConstraintParameterException $e ) {
			$exceptions[] = $e;
		}
		try {
			$this->constraintParameterParser->parseRelationParameter(
				$constraintParameters,
				$constraintTypeItemId
			);
		} catch ( ConstraintParameterException $e ) {
			$exceptions[] = $e;
		}
		return $exceptions;
	}

}
