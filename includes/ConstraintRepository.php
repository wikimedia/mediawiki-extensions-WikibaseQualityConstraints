<?php

namespace WikidataQuality\ConstraintReport;

class ConstraintRepository {

	/**
	 * @param $prop
	 *
	 * @return Constraint[]
	 */
	public function queryConstraintsForProperty( $prop ) {
        $db = wfGetDB( DB_SLAVE );
		$results = $db->select(
			CONSTRAINT_TABLE,
			'*',
			"pid = $prop"
		);

		return $this->convertToConstraints( $results );
	}

	/**
	 * @param Constraint[] $constraints
	 *
	 * @return bool
	 * @throws \DBUnexpectedError
	 */
	public function insertBatch( array $constraints ) {
		$accumulator = array_map(
			function ( Constraint $constraint ) {
				return array(
					'constraint_guid' => $constraint->getConstraintClaimGuid(),
					'pid' => $constraint->getPropertyId(),
					'constraint_type_qid' => $constraint->getConstraintTypeQid(),
					'constraint_parameters' => json_encode( $constraint->getConstraintParameters() )
				);
			},
			$constraints
		);

		$db = wfGetDB( DB_MASTER );
		$db->commit( __METHOD__, "flush" );
		wfWaitForSlaves();

		return $db->insert( CONSTRAINT_TABLE, $accumulator );
	}


	public function deleteAll( $batchSize = 1000 ) {
		$db = wfGetDB( DB_MASTER );
		if ( $db->getType() === 'sqlite' ) {
			$db->delete( CONSTRAINT_TABLE, '*' );
		} else {
			do {
				$db->commit( __METHOD__, 'flush' );
				wfWaitForSlaves();
				$table = $db->tableName( CONSTRAINT_TABLE );
				$db->query( sprintf('DELETE FROM %s LIMIT %s', $table, $batchSize ) );
			} while ( $db->affectedRows() > 0 );
		}
	}

	private function convertToConstraints( $results ) {
		$constraints = array();
		foreach( $results as $result ) {
			$constraintTypeQid = $result->constraint_type_qid;
			$constraintParameters = (array) json_decode( $result->constraint_parameters );
			$constraints[] = new Constraint( $result->constraint_guid, $result->pid, $constraintTypeQid, $constraintParameters );
		}
		return $constraints;
	}

}