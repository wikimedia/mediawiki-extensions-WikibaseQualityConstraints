<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Checker;

use Config;
use DataValues\QuantityValue;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\Context;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterException;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\RangeCheckerHelper;
use WikibaseQuality\ConstraintReport\ConstraintParameterRenderer;
use WikibaseQuality\ConstraintReport\Role;
use Wikibase\DataModel\Statement\Statement;

/**
 * @package WikibaseQuality\ConstraintReport\ConstraintCheck\Checker
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class DiffWithinRangeChecker implements ConstraintChecker {

	/**
	 * @var ConstraintParameterParser
	 */
	private $constraintParameterParser;

	/**
	 * @var RangeCheckerHelper
	 */
	private $rangeCheckerHelper;

	/**
	 * @var ConstraintParameterRenderer
	 */
	private $constraintParameterRenderer;

	/**
	 * @var Config
	 */
	private $config;

	/**
	 * @param ConstraintParameterParser $constraintParameterParser
	 * @param RangeCheckerHelper $rangeCheckerHelper
	 * @param ConstraintParameterRenderer $constraintParameterRenderer
	 * @param Config $config
	 */
	public function __construct(
		ConstraintParameterParser $constraintParameterParser,
		RangeCheckerHelper $rangeCheckerHelper,
		ConstraintParameterRenderer $constraintParameterRenderer,
		Config $config
	) {
		$this->constraintParameterParser = $constraintParameterParser;
		$this->rangeCheckerHelper = $rangeCheckerHelper;
		$this->constraintParameterRenderer = $constraintParameterRenderer;
		$this->config = $config;
	}

	private function parseConstraintParameters( Constraint $constraint ) {
		list( $min, $max ) = $this->constraintParameterParser->parseRangeParameter(
			$constraint->getConstraintParameters(),
			$constraint->getConstraintTypeItemId(),
			'quantity'
		);
		$property = $this->constraintParameterParser->parsePropertyParameter(
			$constraint->getConstraintParameters(),
			$constraint->getConstraintTypeItemId()
		);

		if ( $min !== null ) {
			$parameters['minimum_quantity'] = [ $min ];
		}
		if ( $max !== null ) {
			$parameters['maximum_quantity'] = [ $max ];
		}
		$parameters['property'] = [ $property ];

		return [ $min, $max, $property, $parameters ];
	}

	/**
	 * Check whether the endpoints of a range are in years or not.
	 * @param QuantityValue|null $min
	 * @param QuantityValue|null $max
	 * @return bool
	 */
	private function rangeInYears( $min, $max ) {
		$yearUnit = $this->config->get( 'WBQualityConstraintsYearUnit' );

		if ( $min !== null && $min->getUnit() === $yearUnit ) {
			return true;
		}
		if ( $max !== null && $max->getUnit() === $yearUnit ) {
			return true;
		}

		return false;
	}

	/**
	 * Checks 'Diff within range' constraint.
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
		if ( $context->getType() !== Context::TYPE_STATEMENT ) {
			// TODO T175565
			return new CheckResult( $context, $constraint, [], CheckResult::STATUS_NOT_MAIN_SNAK );
		}

		$parameters = [];
		$constraintParameters = $constraint->getConstraintParameters();

		$snak = $context->getSnak();

		if ( !$snak instanceof PropertyValueSnak ) {
			// nothing to check
			return new CheckResult( $context, $constraint, $parameters, CheckResult::STATUS_COMPLIANCE, '' );
		}

		$minuend = $snak->getDataValue();

		list ( $min, $max, $property, $parameters ) = $this->parseConstraintParameters( $constraint );

		// checks only the first occurrence of the referenced property (this constraint implies a single value constraint on that property)
		/** @var Statement $otherStatement */
		foreach ( $context->getEntity()->getStatements() as $otherStatement ) {
			$otherMainSnak = $otherStatement->getMainSnak();

			if (
				!$property->equals( $otherStatement->getPropertyId() ) ||
				$otherStatement->getRank() === Statement::RANK_DEPRECATED ||
				!$otherMainSnak instanceof PropertyValueSnak
			) {
				continue;
			}

			$subtrahend = $otherMainSnak->getDataValue();
			if ( $subtrahend->getType() === $minuend->getType() ) {
				$diff = $this->rangeInYears( $min, $max ) ?
					$this->rangeCheckerHelper->getDifferenceInYears( $minuend, $subtrahend ) :
					$this->rangeCheckerHelper->getDifference( $minuend, $subtrahend );

				if ( $this->rangeCheckerHelper->getComparison( $min, $diff ) > 0 ||
					$this->rangeCheckerHelper->getComparison( $diff, $max ) > 0
				) {
					// at least one of $min, $max is set at this point, otherwise there could be no violation
					$openness = $min !== null ? ( $max !== null ? '' : '-rightopen' ) : '-leftopen';
					$message = wfMessage( "wbqc-violation-message-diff-within-range$openness" );
					$message->rawParams(
						$this->constraintParameterRenderer->formatEntityId( $context->getSnak()->getPropertyId(), Role::PREDICATE ),
						$this->constraintParameterRenderer->formatDataValue( $minuend, Role::OBJECT ),
						$this->constraintParameterRenderer->formatEntityId( $otherStatement->getPropertyId(), Role::PREDICATE ),
						$this->constraintParameterRenderer->formatDataValue( $subtrahend, Role::OBJECT )
					);
					if ( $min !== null ) {
						$message->rawParams( $this->constraintParameterRenderer->formatDataValue( $min, Role::OBJECT ) );
					}
					if ( $max !== null ) {
						$message->rawParams( $this->constraintParameterRenderer->formatDataValue( $max, Role::OBJECT ) );
					}
					$message = $message->escaped();
					$status = CheckResult::STATUS_VIOLATION;
				} else {
					$message = '';
					$status = CheckResult::STATUS_COMPLIANCE;
				}
			} else {
				$message = wfMessage( "wbqc-violation-message-diff-within-range-must-have-equal-types" )->escaped();
				$status = CheckResult::STATUS_VIOLATION;
			}

			return new CheckResult( $context, $constraint, $parameters, $status, $message );
		}

		$message = wfMessage( "wbqc-violation-message-diff-within-range-property-must-exist" )->escaped();
		$status = CheckResult::STATUS_VIOLATION;
		return new CheckResult( $context, $constraint, $parameters, $status, $message );
	}

	public function checkConstraintParameters( Constraint $constraint ) {
		$constraintParameters = $constraint->getConstraintParameters();
		$exceptions = [];
		try {
			$this->constraintParameterParser->parseRangeParameter(
				$constraintParameters,
				$constraint->getConstraintTypeItemId(),
				'quantity'
			);
		} catch ( ConstraintParameterException $e ) {
			$exceptions[] = $e;
		}
		try {
			$this->constraintParameterParser->parsePropertyParameter( $constraintParameters, $constraint->getConstraintTypeItemId() );
		} catch ( ConstraintParameterException $e ) {
			$exceptions[] = $e;
		}
		return $exceptions;
	}

}
