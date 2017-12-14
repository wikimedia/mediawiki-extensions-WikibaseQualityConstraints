<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Helper;

use Config;
use IBufferingStatsdDataFactory;
use Psr\Log\LoggerInterface;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\Context;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;

/**
 * Helper class for tracking and logging messages.
 *
 * @author Lucas Werkmeister
 * @license GNU GPL v2+
 */
class LoggingHelper {

	/**
	 * @var IBufferingStatsdDataFactory
	 */
	private $dataFactory;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @var float[]
	 */
	private $constraintCheckDurationLimits;

	/**
	 * @param IBufferingStatsdDataFactory $dataFactory,
	 * @param LoggerInterface $logger
	 * @param Config $config
	 */
	public function __construct(
		IBufferingStatsdDataFactory $dataFactory,
		LoggerInterface $logger,
		Config $config
	) {
		$this->dataFactory = $dataFactory;
		$this->logger = $logger;
		$this->constraintCheckDurationLimits = [
			'info' => $config->get( 'WBQualityConstraintsCheckDurationInfoSeconds' ),
			'warning' => $config->get( 'WBQualityConstraintsCheckDurationWarningSeconds' ),
		];
	}

	/**
	 * Log a constraint check.
	 * The constraint check is tracked on the statsd data factory,
	 * and also logged with the logger interface if it took longer than a certain time.
	 * Multiple limits corresponding to different log levels can be specified in the configuration;
	 * checks that exceed a higher limit are logged at a more severe level.
	 *
	 * @param Context $context
	 * @param Constraint $constraint
	 * @param CheckResult $result
	 * @param string $constraintCheckerClass
	 * @param float $durationSeconds
	 * @param string $method Use __METHOD__.
	 */
	public function logConstraintCheck(
		Context $context,
		Constraint $constraint,
		CheckResult $result,
		$constraintCheckerClass,
		$durationSeconds,
		$method
	) {
		$constraintCheckerClassShortName = substr( strrchr( $constraintCheckerClass, '\\' ), 1 );
		$constraintTypeItemId = $constraint->getConstraintTypeItemId();

		$this->dataFactory->timing(
			'wikibase.quality.constraints.check.timing.' .
				$constraintTypeItemId . '-' .
				$constraintCheckerClassShortName,
			$durationSeconds * 1000
		);

		// find the longest limit (and associated log level) that the duration exceeds
		foreach ( $this->constraintCheckDurationLimits as $level => $limit ) {
			if (
				// duration exceeds this limit
				isset( $limit ) && $durationSeconds > $limit &&
				// this limit is longer than previous longest limit
				( !isset( $limitSeconds ) || $limit > $limitSeconds )
			) {
				$limitSeconds = $limit;
				$logLevel = $level;
			}
		}

		if ( !isset( $limitSeconds ) ) {
			return;
		}
		if ( $context->getType() !== Context::TYPE_STATEMENT ) {
			// TODO log less details but still log something
			return;
		}

		$this->logger->log(
			$logLevel,
			'Constraint check with {constraintCheckerClassShortName} ' .
			'took longer than {limitSeconds} second(s) ' .
			'(duration: {durationSeconds} seconds).',
			[
				'method' => $method,
				'loggingMethod' => __METHOD__,
				'durationSeconds' => $durationSeconds,
				'limitSeconds' => $limitSeconds,
				'constraintId' => $constraint->getConstraintId(),
				'constraintPropertyId' => $constraint->getPropertyId()->getSerialization(),
				'constraintTypeItemId' => $constraintTypeItemId,
				'constraintParameters' => $constraint->getConstraintParameters(),
				'constraintCheckerClass' => $constraintCheckerClass,
				'constraintCheckerClassShortName' => $constraintCheckerClassShortName,
				'entityId' => $context->getEntity()->getId()->getSerialization(),
				'statementGuid' => $context->getSnakStatement()->getGuid(),
				'resultStatus' => $result->getStatus(),
				'resultParameters' => $result->getParameters(),
				'resultMessage' => $result->getMessage(),
			]
		);
	}

}
