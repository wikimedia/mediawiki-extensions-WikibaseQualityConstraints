<?php

namespace WikibaseQuality\ConstraintReport;

use Wikibase\DataModel\Entity\PropertyId;

/**
 * Contains all data belonging to a certain constraint.
 *
 * @license GNU GPL v2+
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
	 */
	private $constraintTypeItemId;

	/**
	 * @var array
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
	 * Item ID serialization of the constraint type item.
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
