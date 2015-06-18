<?php

namespace WikibaseQuality\ConstraintReport;

use Wikibase\DataModel\Entity\PropertyId;


/**
 * Class Constraint
 *
 * @package WikibaseQuality\ConstraintReport
 *
 * Contains all data belonging to a certain constraint.
 */
class Constraint {

	/**
	 * @var string
	 */
	private $constraintClaimGuid;

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
	 * @var PropertyId
	 */
	private $propertyId;

	/**
	 * @var array (variable length; key: string with parameter name (e.g. 'property'); value: string (e.g. 'P21')
	 */
	private $constraintParameters;

	/**
	 * @param string $constraintClaimGuid
	 * @param PropertyId $propertyId
	 * @param string $constraintTypeQid
	 * @param array $constraintParameters
	 */
	public function __construct( $constraintClaimGuid, PropertyId $propertyId, $constraintTypeQid, $constraintParameters) {
		$this->constraintClaimGuid = $constraintClaimGuid;
		$this->constraintTypeQid = $constraintTypeQid;
		$this->propertyId = $propertyId;
		$this->constraintParameters = $constraintParameters;
	}

	/**
	 * @return string
	 */
	public function getConstraintClaimGuid() {
		return $this->constraintClaimGuid;
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
	 * @return array (variable length; key: string with parameter name (e.g. 'property'); value: string (e.g. 'P21')
	 */
	public function getConstraintParameters() {
		return $this->constraintParameters;
	}

}