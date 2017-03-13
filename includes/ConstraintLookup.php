<?php

namespace WikibaseQuality\ConstraintReport;

use Wikibase\DataModel\Entity\PropertyId;

/**
 * @license GNU GPL v2+
 */
interface ConstraintLookup {
	/**
	 * @param PropertyId $propertyId
	 * @return Constraint[]
	 *
	 */
	public function queryConstraintsForProperty( PropertyId $propertyId );
}
