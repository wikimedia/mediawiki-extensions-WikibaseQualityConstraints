<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Cache;

use Wikibase\DataModel\Entity\EntityId;
use Wikimedia\Assert\Assert;

/**
 * Information about what other things a value depends on.
 *
 * @author Lucas Werkmeister
 * @license GNU GPL v2+
 */
class DependencyMetadata {

	/**
	 * @var EntityId[]
	 */
	private $entityIds = [];

	/**
	 * @return self Indication that a value does not depend on anything else.
	 */
	public static function blank() {
		return new self;
	}

	/**
	 * @param EntityId $entityId An entity ID from which the value was derived.
	 * @return self Indication that a value is was derived from the entity with the given ID.
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
			$ret->entityIds = array_merge( $ret->entityIds, $metadata->entityIds );
		}
		return $ret;
	}

	/**
	 * @return EntityId[] Entity IDs from whose entities the value was derived.
	 * Changes to those entity IDs should invalidate the value.
	 */
	public function getEntityIds() {
		return $this->entityIds;
	}

}
