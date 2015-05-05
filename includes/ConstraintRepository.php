<?php

namespace WikidataQuality\ConstraintReport;


use WikidataQuality\ConstraintReport\Constraint;


class ConstraintRepository {

	private $db;

	public function __construct() {
		wfWaitForSlaves();
		$loadBalancer = wfGetLB();
		$this->db = $loadBalancer->getConnection( DB_MASTER );
	}

	/**
	 * @param $prop
	 *
	 * @return array
	 */
	public function queryConstraintsForProperty( $prop ) {
		$results = $this->db->select(
			CONSTRAINT_TABLE,
			array ( 'pid', 'constraint_type_qid', 'constraint_parameters' ),
			( "pid = $prop" ),
			__METHOD__,
			array ( '' )
		);

		return $this->convertToConstraints( $results );
	}

	private function convertToConstraints( $results ) {
		$constraints = array();
		foreach( $results as $result ) {
			$constraints[] = new Constraint( $result );
		}
		return $constraints;
	}

}