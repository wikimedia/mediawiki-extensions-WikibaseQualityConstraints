<?php

namespace WikibaseQuality\ConstraintReport;

use Wikibase\DataModel\Entity\PropertyId;

/**
 *
 * Contains all data belonging to a certain constraint.
 */
class Constraint {

	/**
	 * @var string
	 */
	private $constraintId;

	/**
	 * @var PropertyId
	 */
	private $propertyId;

	/**
	 * @var string
	 *
	 * ItemId for the constraint (each Constraint will have
	 * a representation as an item)
	 * Currently contains the name since the constraints are
	 * not migrated yet.
	 */
	private $constraintTypeItemId;

	/**
	 * @var array (key: string with parameter name (e.g. 'property'); value: string (e.g. 'P21'))
	 */
	private $constraintParameters;

	/**
	 * @param string $constraintId
	 * @param PropertyId $propertyId
	 * @param string $constraintTypeItemId
	 * @param array $constraintParameters
	 */
	public function __construct(
		$constraintId,
		PropertyId $propertyId,
		$constraintTypeItemId,
		array $constraintParameters
	) {
		$this->constraintId = $constraintId;
		$this->propertyId = $propertyId;
		$this->constraintTypeItemId = $constraintTypeItemId;
		$this->constraintParameters = $constraintParameters;
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
	 * ItemId for the constraint (each Constraint will have
	 * a representation as an item)
	 * Currently contains the name since the constraints are
	 * not migrated yet.
	 */
	public function getConstraintTypeItemId() {
		return $this->constraintTypeItemId;
	}

	/**
	 * @return PropertyId
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
