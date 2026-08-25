<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Cache;

/**
 * An array (of unspecified nature) along with information whether and how it was cached.
 *
 * @author Lucas Werkmeister
 * @license GPL-2.0-or-later
 */
class CachedArray {

	public function __construct(
		private readonly array $array,
		private readonly Metadata $metadata,
	) {
	}

	/**
	 * @return array
	 */
	public function getArray() {
		return $this->array;
	}

	/**
	 * @return Metadata
	 */
	public function getMetadata() {
		return $this->metadata;
	}

}
