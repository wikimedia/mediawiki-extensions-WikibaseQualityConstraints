<?php

namespace WikibaseQuality\ConstraintReport;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Wikibase\DataModel\Entity\NumericPropertyId;
use Wikimedia\Rdbms\ILoadBalancer;
use Wikimedia\Rdbms\IResultWrapper;

/**
 * @author BP2014N1
 * @license GPL-2.0-or-later
 */
class ConstraintRepositoryLookup implements ConstraintLookup {

	/** @var ILoadBalancer */
	private $lb;

	/** @var string|false */
	private $dbName;

	private LoggerInterface $logger;

	/**
	 * @param ILoadBalancer $lb Load balancer for database connections.
	 * Must match $dbName, i.e. if $dbName is not false,
	 * then using the main DBLoadBalancer service may be incorrect.
	 * @param string|false $dbName Database name ($domain for ILoadBalancer methods).
	 * @param LoggerInterface|null $logger
	 */
	public function __construct( ILoadBalancer $lb, $dbName, ?LoggerInterface $logger = null ) {
		$this->lb = $lb;
		$this->dbName = $dbName;
		$this->logger = $logger ?? new NullLogger();
	}

	/**
	 * @param NumericPropertyId $propertyId
	 *
	 * @return Constraint[]
	 */
	public function queryConstraintsForProperty( NumericPropertyId $propertyId ) {
		$dbr = $this->lb->getConnection( ILoadBalancer::DB_REPLICA, [], $this->dbName );

		$results = $dbr->newSelectQueryBuilder()
			->select( [
				'constraint_type_qid',
				'constraint_parameters',
				'constraint_guid',
				'pid',
			] )
			->from( 'wbqc_constraints' )
			->where( [ 'pid' => $propertyId->getNumericId() ] )
			->caller( __METHOD__ )
			->fetchResultSet();

		return $this->convertToConstraints( $results );
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
				$this->logger->warning( 'Constraint {constraintId} has invalid constraint parameters.', [
					'method' => __METHOD__,
					'constraintId' => $result->constraint_guid,
					'constraintParameters' => $result->constraint_parameters,
				] );
				$constraintParameters = [ '@error' => [ /* unknown */ ] ];
			}

			$constraints[] = new Constraint(
				$result->constraint_guid,
				NumericPropertyId::newFromNumber( $result->pid ),
				$constraintTypeItemId,
				$constraintParameters
			);
		}
		return $constraints;
	}

}
