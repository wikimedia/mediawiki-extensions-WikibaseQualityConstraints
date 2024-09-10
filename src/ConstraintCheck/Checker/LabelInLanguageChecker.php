<?php

declare( strict_types = 1 );

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Checker;

use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Term\LabelsProvider;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\Context;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterException;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessage;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikibaseQuality\ConstraintReport\Role;

/**
 * @license GPL-2.0-or-later
 */
class LabelInLanguageChecker implements ConstraintChecker {

	private ConstraintParameterParser $constraintParameterParser;

	public function __construct( ConstraintParameterParser $constraintParameterParser ) {
		$this->constraintParameterParser = $constraintParameterParser;
	}

	/**
	 * @codeCoverageIgnore This method is purely declarative.
	 */
	public function getSupportedContextTypes(): array {
		return [
			Context::TYPE_STATEMENT => CheckResult::STATUS_COMPLIANCE,
			Context::TYPE_QUALIFIER => CheckResult::STATUS_COMPLIANCE,
			Context::TYPE_REFERENCE => CheckResult::STATUS_COMPLIANCE,
		];
	}

	/**
	 * @codeCoverageIgnore This method is purely declarative.
	 */
	public function getDefaultContextTypes(): array {
		return [
			Context::TYPE_STATEMENT,
			Context::TYPE_QUALIFIER,
			Context::TYPE_REFERENCE,
		];
	}

	public function getSupportedEntityTypes(): array {
		return [
			'item' => CheckResult::STATUS_COMPLIANCE,
			'property' => CheckResult::STATUS_COMPLIANCE,
			'lexeme' => CheckResult::STATUS_NOT_IN_SCOPE,
			'form' => CheckResult::STATUS_NOT_IN_SCOPE,
			'sense' => CheckResult::STATUS_NOT_IN_SCOPE,
			'mediainfo' => CheckResult::STATUS_NOT_IN_SCOPE,
		];
	}

	/**
	 * Checks 'Language' constraint.
	 *
	 * @throws ConstraintParameterException
	 */
	public function checkConstraint( Context $context, Constraint $constraint ): CheckResult {
		if ( $context->getSnakRank() === Statement::RANK_DEPRECATED ) {
			return new CheckResult( $context, $constraint, CheckResult::STATUS_DEPRECATED );
		}

		$constraintParameters = $constraint->getConstraintParameters();

		$languages = $this->constraintParameterParser->parseLanguageParameter(
			$constraintParameters,
			$constraint->getConstraintTypeItemId()
		);

		$status = CheckResult::STATUS_VIOLATION;
		$message = ( new ViolationMessage( 'wbqc-violation-message-label-lacking' ) )
			->withEntityId( $context->getSnak()->getPropertyId(), Role::PREDICATE )
			->withLanguages( $languages );

		/** @var LabelsProvider $entity */
		$entity = $context->getEntity();
		'@phan-var LabelsProvider $entity';
		$labels = $entity->getLabels();

		if ( $labels->hasTermForLanguage( 'mul' ) ) {
			$message = null;
			$status = CheckResult::STATUS_COMPLIANCE;
		} else {
			foreach ( $languages as $language ) {
				if ( $labels->hasTermForLanguage( $language ) ) {
					$message = null;
					$status = CheckResult::STATUS_COMPLIANCE;
					break;
				}
			}
		}

		return new CheckResult( $context, $constraint, $status, $message );
	}

	public function checkConstraintParameters( Constraint $constraint ): array {
		$constraintParameters = $constraint->getConstraintParameters();
		$exceptions = [];
		try {
			$this->constraintParameterParser->parseLanguageParameter(
				$constraintParameters,
				$constraint->getConstraintTypeItemId()
			);
		} catch ( ConstraintParameterException $e ) {
			$exceptions[] = $e;
		}
		return $exceptions;
	}

}
