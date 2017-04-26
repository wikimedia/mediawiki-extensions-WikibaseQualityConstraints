<?php

namespace WikibaseQuality\ConstraintReport;

use DBUnexpectedError;
use InvalidArgumentException;
use Wikimedia\Rdbms\LikeMatch;
use ResultWrapper;
use Wikibase\DataModel\Entity\PropertyId;

/**
 * @package WikibaseQuality\ConstraintReport
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class ConstraintRepository implements ConstraintLookup {

	/**
	 * @param PropertyId $propertyId
	 *
	 * @return Constraint[]
	 */
	public function queryConstraintsForProperty( PropertyId $propertyId ) {
		$db = wfGetDB( DB_REPLICA );

		$results = $db->select(
			CONSTRAINT_TABLE,
			'*',
			array( 'pid' => $propertyId->getNumericId() )
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
					'constraint_guid' => $constraint->getConstraintId(),
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
	 * @param LikeMatch $any should be IDatabase::anyChar()
	 *
	 * @return array
	 */
	private function uuidPattern( LikeMatch $any ) {
		return array_merge(
			array_fill( 0, 8, $any ), [ '-' ],
			array_fill( 0, 4, $any ), [ '-' ],
			array_fill( 0, 4, $any ), [ '-' ],
			array_fill( 0, 4, $any ), [ '-' ],
			array_fill( 0, 12, $any )
		);
	}

	/**
	 * Delete all constraints where the constraint ID is a UUID
	 * (formatted in groups of 8-4-4-4-12 digits).
	 *
	 * @throws DBUnexpectedError
	 */
	public function deleteWhereConstraintIdIsUuid() {
		$db = wfGetDB( DB_MASTER );
		$db->delete(
			CONSTRAINT_TABLE,
			// WHERE constraint_guid LIKE ________-____-____-____-____________
			'constraint_guid ' . $db->buildLike( $this->uuidPattern( $db->anyChar() ) )
		);
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
				wfGetLBFactory()->waitForReplication();
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
