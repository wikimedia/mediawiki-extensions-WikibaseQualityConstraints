<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Checker;

use Wikibase\DataModel\Statement\Statement;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\Context;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterException;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessage;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikibaseQuality\ConstraintReport\Role;

/**
 * @author Amir Sarabadani
 * @license GPL-2.0-or-later
 */
class EntityTypeChecker implements ConstraintChecker {

	/**
	 * @var ConstraintParameterParser
	 */
	private $constraintParameterParser;

	public function __construct(
		ConstraintParameterParser $constraintParameterParser
	) {
		$this->constraintParameterParser = $constraintParameterParser;
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
			Context::TYPE_QUALIFIER,
			Context::TYPE_REFERENCE,
		];
	}

	public function checkConstraint( Context $context, Constraint $constraint ) {
		if ( $context->getSnakRank() === Statement::RANK_DEPRECATED ) {
			return new CheckResult( $context, $constraint, [], CheckResult::STATUS_DEPRECATED );
		}

		$constraintParameters = $constraint->getConstraintParameters();
		$entityTypes = $this->constraintParameterParser->parseEntityTypesParameter(
			$constraintParameters,
			$constraint->getConstraintTypeItemId()
		);
		$parameters = [
			'item' => $entityTypes->getEntityTypes(),
		];

		if ( !in_array( $context->getEntity()->getType(), $entityTypes->getEntityTypes() ) ) {
			$message = ( new ViolationMessage( 'wbqc-violation-message-entityType' ) )
				->withEntityId( $context->getSnak()->getPropertyId(), Role::CONSTRAINT_PROPERTY )
				->withEntityIdList( $entityTypes->getEntityTypeItemIds(), Role::CONSTRAINT_PARAMETER_VALUE );

			return new CheckResult(
				$context,
				$constraint,
				[],
				CheckResult::STATUS_VIOLATION,
				$message
			);
		}

		return new CheckResult( $context, $constraint, $parameters, CheckResult::STATUS_COMPLIANCE );
	}

	public function checkConstraintParameters( Constraint $constraint ) {
		$constraintParameters = $constraint->getConstraintParameters();
		$exceptions = [];
		try {
			$this->constraintParameterParser->parseEntityTypesParameter(
				$constraintParameters,
				$constraint->getConstraintTypeItemId()
			);
		} catch ( ConstraintParameterException $e ) {
			$exceptions[] = $e;
		}
		return $exceptions;
	}

}
