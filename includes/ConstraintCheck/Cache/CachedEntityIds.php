<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Cache;

use Wikibase\DataModel\Entity\EntityId;

/**
 * A list of entity IDs, along with information whether and how they were cached.
 *
 * (Note that list entries may also be null,
 * in case it was not possible to parse an entity ID from the SPARQL response.)
 */
class CachedEntityIds extends CachedArray {

	/**
	 * @return (EntityId|null)[] The entity IDs.
	 */
	public function getArray() {
		return parent::getArray();
	}

}
