<?php

namespace WikibaseQuality\ConstraintReport\Api;

use MediaWiki\MediaWikiServices;
use WANObjectCache;
use Wikibase\DataModel\Entity\EntityId;

/**
 * A thin wrapper around a WANObjectCache
 * that maps entity IDs to cache keys.
 *
 * @author Lucas Werkmeister
 * @license GNU GPL v2+
 */
class ResultsCache {

	private $cache;

	public static function getDefaultInstance() {
		return new self( MediaWikiServices::getInstance()->getMainWANObjectCache() );
	}

	public function __construct( WANObjectCache $cache ) {
		$this->cache = $cache;
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
	 * @param EntityId $key
	 * @param mixed &$curTTL
	 * @param array $checkKeys
	 * @param float &$asOf
	 * @return mixed
	 */
	public function get( EntityId $key, &$curTTL = null, array $checkKeys = [], &$asOf = null ) {
		return $this->cache->get( $this->makeKey( $key ), $curTTL, $checkKeys, $asOf );
	}

	/**
	 * @param EntityId $key
	 * @param $value
	 * @param int $ttl
	 * @param array $opts
	 * @return bool
	 */
	public function set( EntityId $key, $value, $ttl = 0, array $opts = [] ) {
		return $this->cache->set( $this->makeKey( $key ), $value, $ttl, $opts );
	}

	/**
	 * @param EntityId $key
	 * @return bool
	 */
	public function delete( EntityId $key ) {
		return $this->cache->delete( $this->makeKey( $key ) );
	}

}
