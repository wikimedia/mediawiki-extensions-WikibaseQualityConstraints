<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Checker;

use Wikibase\DataModel\Snak\Snak;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\Context;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterException;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessage;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikibaseQuality\ConstraintReport\ConstraintParameterRenderer;
use WikibaseQuality\ConstraintReport\Role;
use Wikibase\DataModel\Statement\Statement;

/**
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class QualifiersChecker implements ConstraintChecker {

	/**
	 * @var ConstraintParameterParser
	 */
	private $constraintParameterParser;

	/**
	 * @var ConstraintParameterRenderer
	 */
	private $constraintParameterRenderer;

	/**
	 * @param ConstraintParameterParser $constraintParameterParser
	 * @param ConstraintParameterRenderer $constraintParameterRenderer should return HTML
	 */
	public function __construct(
		ConstraintParameterParser $constraintParameterParser,
		ConstraintParameterRenderer $constraintParameterRenderer
	) {
		$this->constraintParameterParser = $constraintParameterParser;
		$this->constraintParameterRenderer = $constraintParameterRenderer;
	}

	/**
	 * @codeCoverageIgnore This method is purely declarative.
	 */
	public function getSupportedContextTypes() {
		return [
			Context::TYPE_STATEMENT => CheckResult::STATUS_COMPLIANCE,
			Context::TYPE_QUALIFIER => CheckResult::STATUS_NOT_IN_SCOPE,
			Context::TYPE_REFERENCE => CheckResult::STATUS_NOT_IN_SCOPE,
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
	 * Checks 'Qualifiers' constraint.
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

		$properties = $this->constraintParameterParser->parsePropertiesParameter( $constraintParameters, $constraint->getConstraintTypeItemId() );
		$parameters['property'] = $properties;

		$message = null;
		$status = CheckResult::STATUS_COMPLIANCE;

		/** @var Snak $qualifier */
		foreach ( $context->getSnakStatement()->getQualifiers() as $qualifier ) {
			$allowedQualifier = false;
			foreach ( $properties as $property ) {
				if ( $qualifier->getPropertyId()->equals( $property ) ) {
					$allowedQualifier = true;
					break;
				}
			}
			if ( !$allowedQualifier ) {
				if ( empty( $properties ) || $properties === [ '' ] ) {
					$message = ( new ViolationMessage( 'wbqc-violation-message-no-qualifiers' ) )
						->withEntityId( $context->getSnak()->getPropertyId(), Role::CONSTRAINT_PROPERTY );
				} else {
					$message = wfMessage( "wbqc-violation-message-qualifiers" );
					$message->rawParams(
						$this->constraintParameterRenderer->formatEntityId( $context->getSnak()->getPropertyId(), Role::CONSTRAINT_PROPERTY ),
						$this->constraintParameterRenderer->formatEntityId( $qualifier->getPropertyId(), Role::QUALIFIER_PREDICATE )
					);
					$message->numParams( count( $properties ) );
					$message->rawParams( $this->constraintParameterRenderer->formatPropertyIdList( $properties, Role::QUALIFIER_PREDICATE ) );
					$message = $message->escaped();
				}
				$status = CheckResult::STATUS_VIOLATION;
				break;
			}
		}

		return new CheckResult( $context, $constraint, $parameters, $status, $message );
	}

	public function checkConstraintParameters( Constraint $constraint ) {
		$constraintParameters = $constraint->getConstraintParameters();
		$exceptions = [];
		try {
			$this->constraintParameterParser->parsePropertiesParameter( $constraintParameters, $constraint->getConstraintTypeItemId() );
		} catch ( ConstraintParameterException $e ) {
			$exceptions[] = $e;
		}
		return $exceptions;
	}

}
