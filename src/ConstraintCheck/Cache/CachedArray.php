<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Cache;

/**
 * An array (of unspecified nature) along with information whether and how it was cached.
 *
 * @author Lucas Werkmeister
 * @license GNU GPL v2+
 */
class CachedArray {

	/**
	 * @var array
	 */
	private $array;

	/**
	 * @var CachingMetadata
	 */
	private $cachingMetadata;

	public function __construct( array $array, CachingMetadata $cachingMetadata ) {
		$this->array = $array;
		$this->cachingMetadata = $cachingMetadata;
	}

	/**
	 * @return array
	 */
	public function getArray() {
		return $this->array;
	}

	/**
	 * @return CachingMetadata
	 */
	public function getCachingMetadata() {
		return $this->cachingMetadata;
	}

}
