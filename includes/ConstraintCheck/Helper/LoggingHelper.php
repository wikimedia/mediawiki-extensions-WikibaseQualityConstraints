<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Helper;

use Config;
use Psr\Log\LoggerInterface;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Statement\Statement;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;

/**
 * Helper class for logging messages if necessary.
 *
 * @package WikibaseQuality\ConstraintReport\ConstraintCheck\Helper
 * @author Lucas Werkmeister
 * @license GNU GPL v2+
 */
class LoggingHelper {

	/**
	 * @type LoggerInterface
	 */
	private $logger;

	/**
	 * @type float[]
	 */
	private $constraintCheckDurationLimits;

	/**
	 * @param LoggerInterface $logger
	 * @param Config $config
	 */
	public function __construct(
		LoggerInterface $logger,
		Config $config
	) {
		$this->logger = $logger;
		$this->constraintCheckDurationLimits = [
			'info' => $config->get( 'WBQualityConstraintsCheckDurationInfoSeconds' ),
			'warning' => $config->get( 'WBQualityConstraintsCheckDurationWarningSeconds' ),
		];
	}

	/**
	 * Log a constraint check if it took longer than a certain time.
	 * Multiple limits corresponding to different log levels can be specified in the configuration;
	 * checks that exceed a higher limit are logged at a more severe level.
	 *
	 * @param Statement $statement
	 * @param Constraint $constraint
	 * @param EntityDocument $entity
	 * @param CheckResult $result
	 * @param string $constraintCheckerClass
	 * @param float $durationSeconds
	 * @param string $method Use __METHOD__.
	 */
	public function logConstraintCheck(
		Statement $statement,
		Constraint $constraint,
		EntityDocument $entity,
		CheckResult $result,
		$constraintCheckerClass,
		$durationSeconds,
		$method
	) {
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

		$constraintCheckerClassShortName = substr( strrchr( $constraintCheckerClass, '\\' ), 1 );

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
				'constraintTypeItemId' => $constraint->getConstraintTypeItemId(),
				'constraintParameters' => $constraint->getConstraintParameters(),
				'constraintCheckerClass' => $constraintCheckerClass,
				'constraintCheckerClassShortName' => $constraintCheckerClassShortName,
				'entityId' => $entity->getId()->getSerialization(),
				'statementGuid' => $statement->getGuid(),
				'resultStatus' => $result->getStatus(),
				'resultParameters' => $result->getParameters(),
				'resultMessage' => $result->getMessage(),
			]
		);
	}

}
