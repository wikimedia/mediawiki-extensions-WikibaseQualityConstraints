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
	private $constraintStatementGuid;

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
	 * @param string $constraintStatementGuid
	 * @param PropertyId $propertyId
	 * @param string $constraintTypeQid
	 * @param array $constraintParameters
	 */
	public function __construct(
		$constraintStatementGuid,
		PropertyId $propertyId,
		$constraintTypeQid,
		array $constraintParameters
	) {
		$this->constraintStatementGuid = $constraintStatementGuid;
		$this->propertyId = $propertyId;
		$this->constraintTypeQid = $constraintTypeQid;
		$this->constraintParameters = $constraintParameters;
	}

	/**
	 * @return string
	 */
	public function getConstraintStatementGuid() {
		return $this->constraintStatementGuid;
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
	 * @return array (key: string with parameter name (e.g. 'property'); value: string (e.g. 'P21'))
	 */
	public function getConstraintParameters() {
		return $this->constraintParameters;
	}

}
