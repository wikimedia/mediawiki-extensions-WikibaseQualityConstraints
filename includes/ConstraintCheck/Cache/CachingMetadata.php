<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Cache;

use Wikibase\DataModel\Entity\EntityId;
use Wikimedia\Assert\Assert;

/**
 * Information about whether and how a value was cached.
 *
 * @author Lucas Werkmeister
 * @license GNU GPL v2+
 */
class CachingMetadata {

	/**
	 * @var int|bool The maximum age in seconds,
	 * or false to indicate that the value wasn’t cached.
	 */
	private $maxAge = false;

	/**
	 * @var EntityId[] The tracked entity IDs.
	 */
	private $entityIds = [];

	/**
	 * @return self Indication that a value is fresh, i. e. not cached.
	 */
	public static function fresh() {
		return new self;
	}

	/**
	 * @param int $maxAge The maximum age of the cached value (in seconds).
	 * @return self Indication that a value is possibly outdated by up to this many seconds.
	 */
	public static function ofMaximumAgeInSeconds( $maxAge ) {
		Assert::parameterType( 'integer', $maxAge, '$maxAge' );
		Assert::parameter( $maxAge > 0, '$maxAge', '$maxage > 0' );
		$ret = new self;
		$ret->maxAge = $maxAge;
		return $ret;
	}

	/**
	 * @param EntityId $entityId An entity ID from which the value was derived.
	 * @return self Indication that a value is fresh,
	 * but was derived from the entity with the given ID.
	 */
	public static function ofEntityId( EntityId $entityId ) {
		$ret = new self;
		$ret->entityIds[] = $entityId;
		return $ret;
	}

	/**
	 * @param self[] $metadatas
	 * @return self
	 */
	public static function merge( array $metadatas ) {
		Assert::parameterElementType( self::class, $metadatas, '$metadatas' );
		$ret = new self;
		foreach ( $metadatas as $metadata ) {
			$ret->maxAge = max( $ret->maxAge, $metadata->maxAge );
			$ret->entityIds = array_merge( $ret->entityIds, $metadata->entityIds );
		}
		return $ret;
	}

	/**
	 * @return bool Whether the value is cached or not (fresh).
	 */
	public function isCached() {
		return $this->maxAge !== false;
	}

	/**
	 * @return int The maximum age of the cached value (in seconds), in other words:
	 * the value might be outdated by up to this many seconds.
	 * For a fresh value, returns 0.
	 */
	public function getMaximumAgeInSeconds() {
		if ( is_int( $this->maxAge ) ) {
			return $this->maxAge;
		} else {
			return 0;
		}
	}

	/**
	 * @return EntityId[] Entity IDs from whose entities the value was derived.
	 * Changes to those entity IDs should invalidate the value.
	 */
	public function getDependedEntityIds() {
		return $this->entityIds;
	}

}
