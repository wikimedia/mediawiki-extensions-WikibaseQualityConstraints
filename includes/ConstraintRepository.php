<?php

namespace WikibaseQuality\ConstraintReport;

use InvalidArgumentException;
use MediaWiki\Logger\LoggerFactory;
use Wikimedia\Rdbms\DBUnexpectedError;
use Wikimedia\Rdbms\LikeMatch;
use Wikibase\DataModel\Entity\PropertyId;
use Wikimedia\Rdbms\ResultWrapper;

/**
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
			[ 'pid' => $propertyId->getNumericId() ]
		);

		return $this->convertToConstraints( $results );
	}

	private function encodeConstraintParameters( array $constraintParameters ) {
		$json = json_encode( $constraintParameters, JSON_FORCE_OBJECT );

		if ( strlen( $json ) > 50000 ) {
			$json = json_encode( [ '@error' => [ 'toolong' => true ] ] );
		}

		return $json;
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
				return [
					'constraint_guid' => $constraint->getConstraintId(),
					'pid' => $constraint->getPropertyId()->getNumericId(),
					'constraint_type_qid' => $constraint->getConstraintTypeItemId(),
					'constraint_parameters' => $this->encodeConstraintParameters( $constraint->getConstraintParameters() )
				];
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
	 * Delete all constraints for the property ID where the constraint ID is a statement ID
	 * (an entity ID, a '$' separator, and a UUID).
	 *
	 * @throws DBUnexpectedError
	 */
	public function deleteForPropertyWhereConstraintIdIsStatementId( PropertyId $propertyId ) {
		$db = wfGetDB( DB_MASTER );
		$db->delete(
			CONSTRAINT_TABLE,
			[
				'pid' => $propertyId->getNumericId(),
				// AND constraint_guid LIKE %$________-____-____-____-____________
				'constraint_guid ' . $db->buildLike( array_merge( [ $db->anyString(), '$' ], $this->uuidPattern( $db->anyChar() ) ) )
			]
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
				$db->query( sprintf( 'DELETE FROM %s LIMIT %d', $table, $batchSize ) );
			} while ( $db->affectedRows() > 0 );
		}
	}

	/**
	 * @param ResultWrapper $results
	 *
	 * @return Constraint[]
	 */
	private function convertToConstraints( ResultWrapper $results ) {
		$constraints = [];
		foreach ( $results as $result ) {
			$constraintTypeItemId = $result->constraint_type_qid;
			$constraintParameters = json_decode( $result->constraint_parameters, true );

			if ( $constraintParameters === null ) {
				// T171295
				LoggerFactory::getInstance( 'WikibaseQualityConstraints' )
					->warning( 'Constraint {constraintId} has invalid constraint parameters.', [
						'method' => __METHOD__,
						'constraintId' => $result->constraint_guid,
						'constraintParameters' => $result->constraint_parameters,
					] );
				$constraintParameters = [ '@error' => [ /* unknown */ ] ];
			}

			$constraints[] = new Constraint(
				$result->constraint_guid,
				PropertyId::newFromNumber( $result->pid ),
				$constraintTypeItemId,
				$constraintParameters
			);
		}
		return $constraints;
	}

}
