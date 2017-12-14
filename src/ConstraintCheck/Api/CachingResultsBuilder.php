<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Api;

use WANObjectCache;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\Lib\Store\EntityRevisionLookup;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachedCheckConstraintsResponse;

/**
 * @author Lucas Werkmeister
 * @license GNU GPL v2+
 */
class CachingResultsBuilder implements ResultsBuilder {

	/**
	 * @var ResultsBuilder
	 */
	private $resultsBuilder;

	/**
	 * @var WANObjectCache
	 */
	private $cache;

	/**
	 * @var EntityRevisionLookup
	 */
	private $entityRevisionLookup;

	/**
	 * @var int
	 */
	private $ttlInSeconds;

	/**
	 * @param ResultsBuilder $resultsBuilder The ResultsBuilder that cache misses are delegated to.
	 * @param WANObjectCache $cache The cache where results can be stored.
	 * @param EntityRevisionLookup $entityRevisionLookup Used to get the latest revision ID.
	 * @param int $ttlInSeconds Time-to-live of the cached values, in seconds.
	 */
	public function __construct(
		ResultsBuilder $resultsBuilder,
		WANObjectCache $cache,
		EntityRevisionLookup $entityRevisionLookup,
		$ttlInSeconds
	) {
		$this->resultsBuilder = $resultsBuilder;
		$this->cache = $cache;
		$this->entityRevisionLookup = $entityRevisionLookup;
		$this->ttlInSeconds = $ttlInSeconds;
	}

	/**
	 * @param EntityId[] $entityIds
	 * @param string[] $claimIds
	 * @param string[]|null $constraintIds
	 * @return CachedCheckConstraintsResponse
	 */
	public function getResults(
		array $entityIds,
		array $claimIds,
		array $constraintIds = null
	) {
		return $this->getAndStoreResults( $entityIds, $claimIds, $constraintIds );
	}

	/**
	 * @param EntityId[] $entityIds
	 * @param string[] $claimIds
	 * @param string[]|null $constraintIds
	 * @return CachedCheckConstraintsResponse
	 */
	public function getAndStoreResults(
		array $entityIds,
		array $claimIds,
		array $constraintIds = null
	) {
		$results = $this->resultsBuilder->getResults( $entityIds, $claimIds, $constraintIds );

		if ( $constraintIds === null ) {
			foreach ( $entityIds as $entityId ) {
				$key = $this->cache->makeKey(
					'WikibaseQualityConstraints', // extension
					'checkConstraints', // action
					'v2', // API response format version
					$entityId->getSerialization()
				);
				$value = [
					'results' => $results->getArray()[$entityId->getSerialization()],
					'latestRevisionIds' => $this->getLatestRevisionIds(
						$results->getCachingMetadata()->getDependedEntityIds()
					),
				];
				$this->cache->set( $key, $value, $this->ttlInSeconds );
			}
		}

		return $results;
	}

	/**
	 * @param EntityId[] $entityIds
	 * @return int[]
	 */
	private function getLatestRevisionIds( array $entityIds ) {
		$latestRevisionIds = [];
		foreach ( $entityIds as $entityId ) {
			$serialization = $entityId->getSerialization();
			$latestRevisionId = $this->entityRevisionLookup->getLatestRevisionId( $entityId );
			$latestRevisionIds[$serialization] = $latestRevisionId;
		}
		return $latestRevisionIds;
	}

}
