<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\Lexeme;

use ExtensionRegistry;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\Lexeme\Domain\Model\Lexeme;
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
class LanguageChecker implements ConstraintChecker {

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

	/**
	 * Checks 'Language' constraint.
	 *
	 * @param Context $context
	 * @param Constraint $constraint
	 *
	 * @throws ConstraintParameterException
	 * @return CheckResult
	 */
	public function checkConstraint( Context $context, Constraint $constraint ) {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'WikibaseLexeme' ) ) {
			return new CheckResult( $context, $constraint, [], CheckResult::STATUS_NOT_IN_SCOPE );
		}
		$entityType = $context->getEntity()->getType();
		if ( $entityType !== Lexeme::ENTITY_TYPE ) {
			return new CheckResult( $context, $constraint, [], CheckResult::STATUS_NOT_IN_SCOPE );
		}
		if ( $context->getSnakRank() === Statement::RANK_DEPRECATED ) {
			return new CheckResult( $context, $constraint, [], CheckResult::STATUS_DEPRECATED );
		}

		$parameters = [];
		$constraintParameters = $constraint->getConstraintParameters();
		$constraintTypeItemId = $constraint->getConstraintTypeItemId();

		$languages = $this->constraintParameterParser->parseItemsParameter(
			$constraintParameters,
			$constraintTypeItemId,
			true
		);
		$parameters['languages'] = $languages;

		$message = ( new ViolationMessage( 'wbqc-violation-message-language' ) )
			->withEntityId( $context->getSnak()->getPropertyId(), Role::PREDICATE )
			->withItemIdSnakValueList( $languages, Role::OBJECT );
		$status = CheckResult::STATUS_VIOLATION;
		/** @var Lexeme $lexeme */
		$lexeme = $context->getEntity();
		'@phan-var Lexeme $lexeme';

		foreach ( $languages as $language ) {
			if ( $language->isNoValue() || $language->isSomeValue() ) {
				continue;
			}
			if ( $lexeme->getLanguage()->equals( $language->getItemId() ) ) {
				$message = null;
				$status = CheckResult::STATUS_COMPLIANCE;
				break;
			}
		}

		return new CheckResult( $context, $constraint, $parameters, $status, $message );
	}

	public function checkConstraintParameters( Constraint $constraint ): array {
		$constraintParameters = $constraint->getConstraintParameters();
		$constraintTypeItemId = $constraint->getConstraintTypeItemId();
		$exceptions = [];
		try {
			$this->constraintParameterParser->parseItemsParameter(
				$constraintParameters,
				$constraintTypeItemId,
				true
			);
		} catch ( ConstraintParameterException $e ) {
			$exceptions[] = $e;
		}
		return $exceptions;
	}

}
