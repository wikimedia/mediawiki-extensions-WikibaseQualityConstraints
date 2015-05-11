<?php

namespace WikidataQuality\ConstraintReport;


use WikidataQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper;
use WikidataQuality\ConstraintReport\ConstraintParameterMap;


class Constraint {

	private $constraintTypeQid;
	private $constraintParameter;

	public function __construct( $constraintEntry ) {
		$this->constraintTypeQid = $constraintEntry->constraint_type_qid;

		$parameterMap = ConstraintReportFactory::getDefaultInstance()->getConstraintParameterMap();
		$constraintParameter = array();
		$jsonParameter = json_decode( $constraintEntry->constraint_parameters );
		$helper = new ConstraintReportHelper();
		if( array_key_exists( $this->constraintTypeQid, $parameterMap ) ) {
			foreach( $parameterMap[$this->constraintTypeQid] as $par ) {
				$constraintParameter[$par] = $helper->stringToArray( $helper->getParameterFromJson( $jsonParameter, $par ) );
			}
		}
		$constraintParameter['exceptions'] = $helper->stringToArray( $helper->getParameterFromJson( $jsonParameter, 'known_exception' ) );
		$this->constraintParameter = $constraintParameter;
	}

	public function getConstraintTypeQid() {
		return $this->constraintTypeQid;
	}

	public function getConstraintParameter() {
		return $this->constraintParameter;
	}
}