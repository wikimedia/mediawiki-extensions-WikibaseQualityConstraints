<?php

namespace WikibaseQuality\ConstraintReport\Api;

use Language;
use MediaWiki\MediaWikiServices;
use WANObjectCache;
use Wikibase\DataModel\Entity\EntityId;

/**
 * A thin wrapper around a WANObjectCache
 * that maps entity IDs to cache keys.
 *
 * @author Lucas Werkmeister
 * @license GPL-2.0-or-later
 */
class ResultsCache {

	/**
	 * @var WANObjectCache
	 */
	private $cache;

	/**
	 * @var string
	 */
	private $formatVersion;

	public static function getDefaultInstance() {
		return new self(
			MediaWikiServices::getInstance()->getMainWANObjectCache(),
			'v2.1' // .1: T188384
		);
	}

	/**
	 * @param WANObjectCache $cache
	 * @param string $formatVersion The version of the API response format.
	 */
	public function __construct( WANObjectCache $cache, $formatVersion ) {
		$this->cache = $cache;
		$this->formatVersion = $formatVersion;
	}

	/**
	 * @param EntityId $entityId
	 * @param string|null $languageCode defaults to user language
	 * @return string cache key
	 */
	public function makeKey( EntityId $entityId, $languageCode = null ) {
		global $wgLang, $wgContLang;
		if ( $languageCode === null ) {
			$languageCode = $wgLang->getCode();
		}
		if ( !Language::isKnownLanguageTag( $languageCode ) && $languageCode !== 'qqx' ) {
			$languageCode = $wgContLang->getCode();
		}
		return $this->cache->makeKey(
			'WikibaseQualityConstraints', // extension
			'checkConstraints', // action
			$this->formatVersion, // API response format version
			$entityId->getSerialization(),
			$languageCode
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
		$ok = true;
		$languageCodes = array_keys( Language::fetchLanguageNames( null, 'all' ) );
		$languageCodes[] = 'qqx';
		foreach ( $languageCodes as $languageCode ) {
			$ok = $this->cache->delete( $this->makeKey( $key, $languageCode ) ) && $ok;
		}
		return $ok;
	}

}
