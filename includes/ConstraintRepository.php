<?php

namespace WikidataQuality\ConstraintReport;

use ResultWrapper;

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
	 * @return bool|ResultWrapper
	 */
	public function queryConstraintsForProperty( $prop ) {
		return $this->db->select(
			CONSTRAINT_TABLE,
			array ( 'pid', 'constraint_type_qid', 'constraint_parameters' ),
			( "pid = $prop" ),
			__METHOD__,
			array ( '' )
		);
	}

}