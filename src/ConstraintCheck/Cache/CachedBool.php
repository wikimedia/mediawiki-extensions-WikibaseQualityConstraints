<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Cache;

/**
 * A bool along with information whether and how it was cached.
 *
 * @author Lucas Werkmeister
 * @license GPL-2.0-or-later
 */
class CachedBool {

	public function __construct(
		private readonly bool $bool,
		private readonly Metadata $metadata,
	) {
	}

	/**
	 * @return bool
	 */
	public function getBool() {
		return $this->bool;
	}

	/**
	 * @return Metadata
	 */
	public function getMetadata() {
		return $this->metadata;
	}

}
