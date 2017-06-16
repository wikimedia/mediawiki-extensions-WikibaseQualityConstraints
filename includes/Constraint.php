<?php

namespace WikibaseQuality\ConstraintReport;

use Wikibase\DataModel\Entity\PropertyId;

/**
 * @package WikibaseQuality\ConstraintReport
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
	private $constraintTypeQid;

	/**
	 * @var array (key: string with parameter name (e.g. 'property'); value: string (e.g. 'P21'))
	 */
	private $constraintParameters;

	/**
	 * @param string $constraintId
	 * @param PropertyId $propertyId
	 * @param string $constraintTypeQid
	 * @param array $constraintParameters
	 */
	public function __construct(
		$constraintId,
		PropertyId $propertyId,
		$constraintTypeQid,
		array $constraintParameters
	) {
		$this->constraintId = $constraintId;
		$this->propertyId = $propertyId;
		$this->constraintTypeQid = $constraintTypeQid;
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
	public function getConstraintTypeQid() {
		return $this->constraintTypeQid;
	}

	/**
	 * @return string
	 */
	public function getConstraintTypeName() {
		//TODO: use label lookup when constraints are migrated
		return $this->constraintTypeQid;
	}

	/**
	 * @return PropertyId
	 */
	public function getPropertyId() {
		return $this->propertyId;
	}

	/**
	 * There are two formats of constraint parameters that this method can return:
	 *
	 * 1. Statement parameters were imported from constraint statements by {@link UpdateConstraintsTableJob}.
	 *    They are lists of snak array serializations, indexed by property ID serialization.
	 * 2. Template parameters were imported from constraint templates on property talk pages.
	 *    They are plain strings (e. g. 'Q5,Q6,Q7') indexed by template parameters (e. g. 'item', 'property').
	 *
	 * Support for template parameters will soon be removed.
	 *
	 * @return array
	 */
	public function getConstraintParameters() {
		return $this->constraintParameters;
	}

}
