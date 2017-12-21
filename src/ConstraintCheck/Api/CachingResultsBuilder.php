<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Api;

use WANObjectCache;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\Lib\Store\EntityRevisionLookup;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachedCheckConstraintsResponse;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachingMetadata;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\DependencyMetadata;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\Metadata;

/**
 * A wrapper around another ResultsBuilder that caches results in a WANObjectCache.
 *
 * The format of the response returned by the wrapped ResultsBuilder mostly does not matter,
 * but the outermost level must be an array from entity ID serialization to results for that entity.
 * Results are cached independently per entity.
 *
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
	 * @var EntityIdParser
	 */
	private $entityIdParser;

	/**
	 * @var int
	 */
	private $ttlInSeconds;

	/**
	 * @var callable
	 */
	private $microtime = 'microtime';

	/**
	 * @param ResultsBuilder $resultsBuilder The ResultsBuilder that cache misses are delegated to.
	 * @param WANObjectCache $cache The cache where results can be stored.
	 * @param EntityRevisionLookup $entityRevisionLookup Used to get the latest revision ID.
	 * @param EntityIdParser $entityIdParser Used to parse entity IDs in cached objects.
	 * @param int $ttlInSeconds Time-to-live of the cached values, in seconds.
	 */
	public function __construct(
		ResultsBuilder $resultsBuilder,
		WANObjectCache $cache,
		EntityRevisionLookup $entityRevisionLookup,
		EntityIdParser $entityIdParser,
		$ttlInSeconds
	) {
		$this->resultsBuilder = $resultsBuilder;
		$this->cache = $cache;
		$this->entityRevisionLookup = $entityRevisionLookup;
		$this->entityIdParser = $entityIdParser;
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
		$results = [];
		$metadatas = [];
		if ( $this->canUseStoredResults( $entityIds, $claimIds, $constraintIds ) ) {
			$storedEntityIds = [];
			foreach ( $entityIds as $entityId ) {
				$storedResults = $this->getStoredResults( $entityId );
				if ( $storedResults !== null ) {
					$results += $storedResults->getArray();
					$metadatas[] = $storedResults->getMetadata();
					$storedEntityIds[] = $entityId;
				}
			}
			$entityIds = array_values( array_diff( $entityIds, $storedEntityIds ) );
		}
		if ( $entityIds !== [] || $claimIds !== [] ) {
			$response = $this->getAndStoreResults( $entityIds, $claimIds, $constraintIds );
			$results += $response->getArray();
			$metadatas[] = $response->getMetadata();
		}
		return new CachedCheckConstraintsResponse(
			$results,
			Metadata::merge( $metadatas )
		);
	}

	/**
	 * We can only use cached constraint results if full constraint check results were requested:
	 * constraint checks for the full entity (not just individual statements),
	 * and without restricting the set of constraints to check.
	 *
	 * @param EntityId[] $entityIds
	 * @param string[] $claimIds
	 * @param string[]|null $constraintIds
	 * @return bool
	 */
	private function canUseStoredResults(
		array $entityIds,
		array $claimIds,
		array $constraintIds = null
	) {
		return $claimIds === [] && $constraintIds === null;
	}

	/**
	 * @param EntityId $entityId
	 * @return string cache key
	 */
	public function makeKey( EntityId $entityId ) {
		return $this->cache->makeKey(
			'WikibaseQualityConstraints', // extension
			'checkConstraints', // action
			'v2', // API response format version
			$entityId->getSerialization()
		);
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

		if ( $this->canStoreResults( $entityIds, $claimIds, $constraintIds ) ) {
			foreach ( $entityIds as $entityId ) {
				$key = $this->makeKey( $entityId );
				$value = [
					'results' => $results->getArray()[$entityId->getSerialization()],
					'latestRevisionIds' => $this->getLatestRevisionIds(
						$results->getMetadata()->getDependencyMetadata()->getEntityIds()
					),
				];
				$this->cache->set( $key, $value, $this->ttlInSeconds );
			}
		}

		return $results;
	}

	/**
	 * We can only store constraint results if the set of constraints to check was not restricted.
	 * However, it doesn’t matter whether constraint checks on individual statements were requested:
	 * we only store results for the mentioned entity IDs,
	 * and those will be complete regardless of what’s in the statement IDs.
	 *
	 * @param EntityId[] $entityIds
	 * @param string[] $claimIds
	 * @param string[]|null $constraintIds
	 * @return bool
	 */
	private function canStoreResults(
		array $entityIds,
		array $claimIds,
		array $constraintIds = null
	) {
		return $constraintIds === null;
	}

	/**
	 * @param EntityId $entityId
	 * @return CachedCheckConstraintsResponse|null
	 */
	public function getStoredResults(
		EntityId $entityId
	) {
		$key = $this->makeKey( $entityId );
		$value = $this->cache->get( $key, $curTTL, [], $asOf );
		$now = call_user_func( $this->microtime, true );

		if ( $value === false ) {
			return null;
		}

		$ageInSeconds = (int)ceil( $now - $asOf );

		$dependedEntityIds = array_map(
			[ $this->entityIdParser, "parse" ],
			array_keys( $value['latestRevisionIds'] )
		);

		if ( $value['latestRevisionIds'] !== $this->getLatestRevisionIds( $dependedEntityIds ) ) {
			return null;
		}

		return new CachedCheckConstraintsResponse(
			[ $entityId->getSerialization() => $value['results'] ],
			array_reduce(
				$dependedEntityIds,
				function( Metadata $metadata, EntityId $entityId ) {
					return Metadata::merge( [
						$metadata,
						Metadata::ofDependencyMetadata(
							DependencyMetadata::ofEntityId( $entityId )
						)
					] );
				},
				Metadata::ofCachingMetadata(
					$ageInSeconds > 0 ?
						CachingMetadata::ofMaximumAgeInSeconds( $ageInSeconds ) :
						CachingMetadata::fresh()
				)
			)
		);
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

	/**
	 * Set a custom function to get the current time, instead of microtime().
	 *
	 * @param callable $microtime
	 */
	public function setMicrotimeFunction( callable $microtime ) {
		$this->microtime = $microtime;
	}

}
