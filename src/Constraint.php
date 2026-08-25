<?php

namespace WikibaseQuality\ConstraintReport;

use Wikibase\DataModel\Entity\NumericPropertyId;

/**
 * Contains all data belonging to a certain constraint.
 *
 * @license GPL-2.0-or-later
 */
class Constraint {

	public function __construct(
		private readonly string $constraintId,
		private readonly NumericPropertyId $propertyId,
		private readonly string $constraintTypeItemId,
		private readonly array $constraintParameters,
	) {
	}

	/**
	 * @return string
	 */
	public function getConstraintId() {
		return $this->constraintId;
	}

	/**
	 * @return string
	 *
	 * Item ID serialization of the constraint type item.
	 */
	public function getConstraintTypeItemId() {
		return $this->constraintTypeItemId;
	}

	/**
	 * @return NumericPropertyId
	 */
	public function getPropertyId() {
		return $this->propertyId;
	}

	/**
	 * The constraint parameters, imported from the qualifiers of the constraint statement.
	 * Contains lists of snak array serializations, indexed by property ID serialization.
	 * (The import is done by {@link UpdateConstraintsTableJob}.)
	 *
	 * @return array
	 */
	public function getConstraintParameters() {
		return $this->constraintParameters;
	}

}
