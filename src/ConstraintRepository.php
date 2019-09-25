<?php

namespace WikibaseQuality\ConstraintReport;

use MediaWiki\Logger\LoggerFactory;
use Wikimedia\Rdbms\DBUnexpectedError;
use Wikimedia\Rdbms\ILoadBalancer;
use Wikimedia\Rdbms\IResultWrapper;
use Wikibase\DataModel\Entity\PropertyId;

/**
 * @author BP2014N1
 * @license GPL-2.0-or-later
 */
class ConstraintRepository implements ConstraintLookup {

	/** @var ILoadBalancer */
	private $lb;

	public function __construct( ILoadBalancer $lb ) {
		$this->lb = $lb;
	}

	/**
	 * @param PropertyId $propertyId
	 *
	 * @return Constraint[]
	 */
	public function queryConstraintsForProperty( PropertyId $propertyId ) {
		$dbr = $this->lb->getConnection( ILoadBalancer::DB_REPLICA );

		$results = $dbr->select(
			'wbqc_constraints',
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
