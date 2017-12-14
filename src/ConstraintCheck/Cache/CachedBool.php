<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Cache;

/**
 * A bool along with information whether and how it was cached.
 *
 * @author Lucas Werkmeister
 * @license GNU GPL v2+
 */
class CachedBool {

	/**
	 * @var bool
	 */
	private $bool;

	/**
	 * @var CachingMetadata
	 */
	private $cachingMetadata;

	/**
	 * @param bool $bool
	 * @param CachingMetadata $cachingMetadata
	 */
	public function __construct( $bool, CachingMetadata $cachingMetadata ) {
		$this->bool = $bool;
		$this->cachingMetadata = $cachingMetadata;
	}

	/**
	 * @return bool
	 */
	public function getBool() {
		return $this->bool;
	}

	/**
	 * @return CachingMetadata
	 */
	public function getCachingMetadata() {
		return $this->cachingMetadata;
	}

}
