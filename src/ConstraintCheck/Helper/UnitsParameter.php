<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Helper;

use DataValues\UnboundedQuantityValue;
use Wikibase\DataModel\Entity\ItemId;

/**
 * Wrapper class for a constraint parameter representing a list of units.
 *
 * @license GPL-2.0-or-later
 * @author Lucas Werkmeister
 */
class UnitsParameter {

	/**
	 * @param ItemId[] $unitItemIds The item IDs of the allowed units.
	 * @param UnboundedQuantityValue[] $unitQuantities Quantities with the allowed units.
	 * @param bool $unitlessAllowed Whether unitless values (unit '1') are allowed or not.
	 */
	public function __construct(
		private readonly array $unitItemIds,
		private readonly array $unitQuantities,
		private readonly bool $unitlessAllowed,
	) {
	}

	/**
	 * @return ItemId[] The item IDs of the allowed units.
	 */
	public function getUnitItemIds() {
		return $this->unitItemIds;
	}

	/**
	 * @return UnboundedQuantityValue[] Quantities with the allowed units.
	 */
	public function getUnitQuantities() {
		return $this->unitQuantities;
	}

	/**
	 * @return bool Whether unitless values (unit '1') are allowed or not.
	 */
	public function getUnitlessAllowed() {
		return $this->unitlessAllowed;
	}

}
