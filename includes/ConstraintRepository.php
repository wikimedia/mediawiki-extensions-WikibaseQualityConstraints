<?php

namespace WikibaseQuality\ConstraintReport;

use DBUnexpectedError;
use InvalidArgumentException;
use ResultWrapper;
use Wikibase\DataModel\Entity\PropertyId;


/**
 * Class ConstraintRepository
 * @package WikibaseQuality\ConstraintReport
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class ConstraintRepository {

	/**
	 * @param int $numericPropertyId
	 *
	 * @return Constraint[]
	 */
	public function queryConstraintsForProperty( $numericPropertyId ) {
		$db = wfGetDB( DB_SLAVE );

		$results = $db->select(
			CONSTRAINT_TABLE,
			'*',
			array( 'pid' => $numericPropertyId )
		);

		return $this->convertToConstraints( $results );
	}

	/**
	 * @param Constraint[] $constraints
	 *
	 * @throws DBUnexpectedError
	 * @return bool
	 */
	public function insertBatch( array $constraints ) {
		$accumulator = array_map(
			function ( Constraint $constraint ) {
				return array(
					'constraint_guid' => $constraint->getConstraintStatementGuid(),
					'pid' => $constraint->getPropertyId()->getNumericId(),
					'constraint_type_qid' => $constraint->getConstraintTypeQid(),
					'constraint_parameters' => json_encode( $constraint->getConstraintParameters() )
				);
			},
			$constraints
		);

		$db = wfGetDB( DB_MASTER );
		return $db->insert( CONSTRAINT_TABLE, $accumulator );
	}

	/**
	 * @param int $batchSize
	 *
	 * @throws InvalidArgumentException
	 * @throws DBUnexpectedError
	 */
	public function deleteAll( $batchSize = 1000 ) {
		if ( !is_int( $batchSize ) ) {
			throw new InvalidArgumentException();
		}
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

	/**
	 * @param ResultWrapper $results
	 *
	 * @return Constraint[]
	 */
	private function convertToConstraints( ResultWrapper $results ) {
		$constraints = array();
		foreach( $results as $result ) {
			$constraintTypeQid = $result->constraint_type_qid;
			$constraintParameters = (array) json_decode( $result->constraint_parameters );

			$constraints[] = new Constraint(
				$result->constraint_guid,
				PropertyId::newFromNumber( $result->pid ),
				$constraintTypeQid,
				$constraintParameters
			);
		}
		return $constraints;
	}

}
