<?php

namespace WikibaseQuality\ConstraintReport;

use LogicException;
use MediaWiki\Logger\LoggerFactory;
use Wikibase\DataModel\Entity\PropertyId;
use Wikimedia\Rdbms\DBUnexpectedError;
use Wikimedia\Rdbms\ILoadBalancer;
use Wikimedia\Rdbms\IResultWrapper;

/**
 * @author BP2014N1
 * @license GPL-2.0-or-later
 */
class ConstraintRepository implements ConstraintLookup {

	/** @var ILoadBalancer */
	private $lb;

	/** @var string|false */
	private $dbName;

	/**
	 * @param ILoadBalancer $lb Load balancer for database connections.
	 * Must match $dbName, i.e. if $dbName is not false,
	 * then using the main DBLoadBalancer service may be incorrect.
	 * @param string|false $dbName Database name ($domain for ILoadBalancer methods).
	 */
	public function __construct( ILoadBalancer $lb, $dbName ) {
		$this->lb = $lb;
		$this->dbName = $dbName;
	}

	/**
	 * @param PropertyId $propertyId
	 *
	 * @return Constraint[]
	 */
	public function queryConstraintsForProperty( PropertyId $propertyId ) {
		$dbr = $this->lb->getConnection( ILoadBalancer::DB_REPLICA, [], $this->dbName );

		$results = $dbr->select(
			'wbqc_constraints',
			'*',
			[ 'pid' => $propertyId->getNumericId() ]
		);

		if ( $this->dbName !== false ) {
			$this->lb->reuseConnection( $dbr );
		}

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
		if ( $this->dbName !== false ) {
			throw new LogicException( __METHOD__ . ' should not be called when constraints defined in non-local database.' );
		}

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

		$dbw = $this->lb->getConnection( ILoadBalancer::DB_MASTER );
		return $dbw->insert( 'wbqc_constraints', $accumulator );
	}

	/**
	 * Delete all constraints for the property ID.
	 *
	 * @param PropertyId $propertyId
	 *
	 * @throws DBUnexpectedError
	 */
	public function deleteForProperty( PropertyId $propertyId ) {
		if ( $this->dbName !== false ) {
			throw new LogicException( __METHOD__ . ' should not be called when constraints defined in non-local database.' );
		}

		$dbw = $this->lb->getConnection( ILoadBalancer::DB_MASTER );
		$dbw->delete(
			'wbqc_constraints',
			[
				'pid' => $propertyId->getNumericId(),
			]
		);
	}

	/**
	 * @param IResultWrapper $results
	 *
	 * @return Constraint[]
	 */
	private function convertToConstraints( IResultWrapper $results ) {
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
