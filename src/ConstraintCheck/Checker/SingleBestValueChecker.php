<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Checker;

use Wikibase\DataModel\Statement\Statement;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\Context;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ValueCountCheckerHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessage;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;

/**
 * @author Lucas Werkmeister
 * @license GPL-2.0-or-later
 */
class SingleBestValueChecker implements ConstraintChecker {

	/**
	 * @var ValueCountCheckerHelper
	 */
	private $valueCountCheckerHelper;

	public function __construct() {
		$this->valueCountCheckerHelper = new ValueCountCheckerHelper();
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
	 * Checks 'Single best value' constraint.
	 *
	 * @param Context $context
	 * @param Constraint $constraint
	 *
	 * @return CheckResult
	 */
	public function checkConstraint( Context $context, Constraint $constraint ) {
		if ( $context->getSnakRank() === Statement::RANK_DEPRECATED ) {
			return new CheckResult( $context, $constraint, [], CheckResult::STATUS_DEPRECATED );
		}

		$propertyId = $context->getSnak()->getPropertyId();

		$parameters = [];

		$bestRankCount = $this->valueCountCheckerHelper->getPropertyCount(
			$context->getSnakGroup( Context::GROUP_BEST_RANK ),
			$propertyId
		);

		if ( $bestRankCount > 1 ) {
			$nonDeprecatedCount = $this->valueCountCheckerHelper->getPropertyCount(
				$context->getSnakGroup( Context::GROUP_NON_DEPRECATED ),
				$propertyId
			);
			if ( $bestRankCount === $nonDeprecatedCount ) {
				$messageKey = 'wbqc-violation-message-single-best-value-no-preferred';
			} else {
				$messageKey = 'wbqc-violation-message-single-best-value-multi-preferred';
			}
			$message = new ViolationMessage( $messageKey );
			$status = CheckResult::STATUS_VIOLATION;
		} else {
			$message = null;
			$status = CheckResult::STATUS_COMPLIANCE;
		}

		return new CheckResult( $context, $constraint, $parameters, $status, $message );
	}

	public function checkConstraintParameters( Constraint $constraint ) {
		// no parameters
		return [];
	}

}
