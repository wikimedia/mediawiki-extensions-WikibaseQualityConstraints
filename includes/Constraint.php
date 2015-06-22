<?php

namespace WikibaseQuality\ConstraintReport;

use Wikibase\DataModel\Entity\PropertyId;


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
	 * @var string[] key/value pairs representing constraint parameters, as defined on-wiki.
	 */
	private $constraintParameters;

	/**
	 * @param string $constraintClaimGuid
	 * @param PropertyId $propertyId
	 * @param string $constraintTypeQid
	 * @param string[] $constraintParameters key/value pairs representing constraint parameters, as defined on-wiki.
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
	 * @return string[] key/value pairs representing constraint parameters, as defined on-wiki.
	 */
	public function getConstraintParameters() {
		return $this->constraintParameters;
	}

}