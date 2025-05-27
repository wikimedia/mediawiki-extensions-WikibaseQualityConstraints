<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Helper;

use MediaWiki\Config\Config;
use MWHttpRequest;
use Psr\Log\LoggerInterface;
use Wikibase\DataModel\Entity\EntityId;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\Context;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use Wikimedia\Stats\StatsFactory;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * Helper class for tracking and logging messages.
 *
 * @author Lucas Werkmeister
 * @license GPL-2.0-or-later
 */
class LoggingHelper {

	/**
	 * @var StatsFactory
	 */
	private $statsFactory;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @var float[]
	 */
	private $constraintCheckDurationLimits;

	/**
	 * @var float[]
	 */
	private $constraintCheckOnEntityDurationLimits;

	/**
	 * @param StatsFactory $statsFactory
	 * @param LoggerInterface $logger
	 * @param Config $config
	 */
	public function __construct(
		StatsFactory $statsFactory,
		LoggerInterface $logger,
		Config $config
	) {
		$this->statsFactory = $statsFactory;
		$this->logger = $logger;
		$this->constraintCheckDurationLimits = [
			'info' => $config->get( 'WBQualityConstraintsCheckDurationInfoSeconds' ),
			'warning' => $config->get( 'WBQualityConstraintsCheckDurationWarningSeconds' ),
		];
		$this->constraintCheckOnEntityDurationLimits = [
			'info' => $config->get( 'WBQualityConstraintsCheckOnEntityDurationInfoSeconds' ),
			'warning' => $config->get( 'WBQualityConstraintsCheckOnEntityDurationWarningSeconds' ),
		];
	}

	/**
	 * Find the longest limit in $limits which the $durationSeconds exceeds,
	 * and return it along with the associated log level.
	 *
	 * @param float[] $limits
	 * @param float $durationSeconds
	 * @return array [ $limitSeconds, $logLevel ]
	 */
	private function findLimit( $limits, $durationSeconds ) {
		$limitSeconds = null;
		$logLevel = null;

		foreach ( $limits as $level => $limit ) {
			if (
				// duration exceeds this limit
				$limit !== null && $durationSeconds > $limit &&
				// this limit is longer than previous longest limit
				( $limitSeconds === null || $limit > $limitSeconds )
			) {
				$limitSeconds = $limit;
				$logLevel = $level;
			}
		}

		return [ $limitSeconds, $logLevel ];
	}

	/**
	 * Log a single constraint check.
	 * The constraint check is tracked on the StatsFactory data factory,
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

		$baseCheckTimingKey = 'wikibase.quality.constraints.check.timing';
		$this->statsFactory
			->getTiming( 'check_constraint_duration_seconds' )
			->copyToStatsdAt( "$baseCheckTimingKey.$constraintTypeItemId-$constraintCheckerClassShortName" )
			->observe( $durationSeconds * 1000 );

		// find the longest limit (and associated log level) that the duration exceeds
		[ $limitSeconds, $logLevel ] = $this->findLimit(
			$this->constraintCheckDurationLimits,
			$durationSeconds
		);
		if ( $limitSeconds === null ) {
			return;
		}
		if ( $context->getType() !== Context::TYPE_STATEMENT ) {
			// TODO log less details but still log something
			return;
		}

		$resultMessage = $result->getMessage();
		if ( $resultMessage !== null ) {
			$resultMessageKey = $resultMessage->getMessageKey();
		} else {
			$resultMessageKey = null;
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
				'constraintParameters' => json_encode( $constraint->getConstraintParameters() ),
				'constraintCheckerClass' => $constraintCheckerClass,
				'constraintCheckerClassShortName' => $constraintCheckerClassShortName,
				'entityId' => $context->getEntity()->getId()->getSerialization(),
				'statementGuid' => $context->getSnakStatement()->getGuid(),
				'resultStatus' => $result->getStatus(),
				'resultMessage' => $resultMessageKey,
			]
		);
	}

	/**
	 * Log a full constraint check on an entity.
	 * The constraint check is tracked on the StatsFactory data factory,
	 * and also logged with the logger interface if it took longer than a certain time.
	 * Multiple limits corresponding to different log levels can be specified in the configuration;
	 * checks that exceed a higher limit are logged at a more severe level.
	 *
	 *
	 * @param EntityId $entityId
	 * @param CheckResult[] $checkResults
	 * @param float $durationSeconds
	 * @param string $method
	 */
	public function logConstraintCheckOnEntity(
		EntityId $entityId,
		array $checkResults,
		$durationSeconds,
		$method
	) {
		$checkEntityTimingKey = 'wikibase.quality.constraints.check.entity.timing';
		$this->statsFactory
			->getTiming( 'check_entity_constraint_duration_seconds' )
			->copyToStatsdAt( $checkEntityTimingKey )
			->observe( $durationSeconds * 1000 );

		// find the longest limit (and associated log level) that the duration exceeds
		[ $limitSeconds, $logLevel ] = $this->findLimit(
			$this->constraintCheckOnEntityDurationLimits,
			$durationSeconds
		);
		if ( $limitSeconds === null ) {
			return;
		}

		$this->logger->log(
			$logLevel,
			'Full constraint check on {entityId} ' .
			'took longer than {limitSeconds} second(s) ' .
			'(duration: {durationSeconds} seconds).',
			[
				'method' => $method,
				'loggingMethod' => __METHOD__,
				'durationSeconds' => $durationSeconds,
				'limitSeconds' => $limitSeconds,
				'entityId' => $entityId->getSerialization(),
				// $checkResults currently not logged
			]
		);
	}

	/**
	 * Log a cache hit for a complete constraint check result for the given entity ID.
	 */
	public function logCheckConstraintsCacheHit( EntityId $entityId ) {
		$cacheEntityHitKey = 'wikibase.quality.constraints.cache.entity.hit';
		$metric = $this->statsFactory->getCounter( 'cache_entity_hit_total' );
		$metric->copyToStatsdAt(
				$cacheEntityHitKey,
			)->increment();
	}

	/**
	 * Log cache misses for a complete constraint check result for the given entity IDs.
	 *
	 * @param EntityId[] $entityIds
	 */
	public function logCheckConstraintsCacheMisses( array $entityIds ) {
		$cacheEntityMissKey = 'wikibase.quality.constraints.cache.entity.miss';
		$metric = $this->statsFactory->getCounter( 'cache_entity_miss_total' );
		$metric->copyToStatsdAt(
				$cacheEntityMissKey,
			)->incrementBy( count( $entityIds ) );
	}

	/**
	 * Log that the dependency metadata for a check result had an empty set of entity IDs.
	 * This should never happen – at least the entity being checked should always be contained.
	 */
	public function logEmptyDependencyMetadata() {
		$this->logger->log(
			'warning',
			'Dependency metadata for constraint check result had empty set of entity IDs.',
			[
				'loggingMethod' => __METHOD__,
				// callers of this method don’t have much information to pass to us,
				// so for now we don’t log any other structured data
				// and hope that the request URL provides enough information
			]
		);
	}

	/**
	 * Log that the dependency metadata for a check result has a very large set of entity IDs.
	 *
	 * @param EntityId[] $entityIds
	 * @param int $maxRevisionIds
	 */
	public function logHugeDependencyMetadata( array $entityIds, $maxRevisionIds ) {
		$this->logger->log(
			'warning',
			'Dependency metadata for constraint check result has huge set of entity IDs ' .
			'(count ' . count( $entityIds ) . ', limit ' . $maxRevisionIds . '); ' .
			'caching disabled for this check result.',
			[
				'loggingMethod' => __METHOD__,
				'entityIds' => json_encode(
					array_map(
						static function ( EntityId $entityId ) {
							return $entityId->getSerialization();
						},
						$entityIds
					)
				),
				'maxRevisionIds' => $maxRevisionIds,
			]
		);
	}

	public function logSparqlHelperTooManyRequestsRetryAfterPresent(
		ConvertibleTimestamp $retryAfterTime,
		MWHttpRequest $request
	) {
		$this->logger->notice(
			'Sparql API replied with status 429 and a retry-after header. Requesting to retry after {retryAfterTime}',
			[
				'retryAfterTime' => $retryAfterTime,
				'responseHeaders' => json_encode( $request->getResponseHeaders() ),
				'responseContent' => $request->getContent(),
			]
		);
	}

	public function logSparqlHelperTooManyRequestsRetryAfterInvalid( MWHttpRequest $request ) {
		$this->logger->warning(
			'Sparql API replied with status 429 and no valid retry-after header.',
			[
				'responseHeaders' => json_encode( $request->getResponseHeaders() ),
				'responseContent' => $request->getContent(),
			]
		);
	}

}
