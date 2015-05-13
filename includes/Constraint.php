<?php

namespace WikidataQuality\ConstraintReport;


use WikidataQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper;
use WikidataQuality\ConstraintReport\ConstraintParameterMap;


class Constraint {

	/**
	 * @var string
	 */
	private $constraintClaimGuid;

	/**
	 * @var string
	 */
	private $constraintTypeQid;

	/**
	 * @var string
	 */
	private $propertyId;

	/**
	 * @var array
	 */
	private $constraintParameters;

	public function __construct( $constraintClaimGuid, $propertyId, $constraintTypeQid, $constraintParameters) {
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
	public function getPropertyId() {
		return $this->propertyId;
	}

	/**
	 * @return array
	 */
	public function getConstraintParameters() {
		return $this->constraintParameters;
	}

}