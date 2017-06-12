<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck;

use InvalidArgumentException;
use MediaWiki\MediaWikiServices;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Statement\StatementGuid;
use Wikibase\DataModel\Statement\StatementList;
use Wikibase\DataModel\Statement\StatementListProvider;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterException;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\SparqlHelperException;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikibaseQuality\ConstraintReport\ConstraintLookup;
use WikibaseQuality\ConstraintReport\Constraint;
use Wikibase\DataModel\Entity\EntityId;

/**
 * Used to start the constraint-check process and to delegate
 * the statements that has to be checked to the corresponding checkers
 *
 * @package WikibaseQuality\ConstraintReport\ConstraintCheck
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class DelegatingConstraintChecker {

	/**
	 * Wikibase entity lookup.
	 *
	 * @var EntityLookup
	 */
	private $entityLookup;

	/**
	 * @var ConstraintChecker[]
	 */
	private $checkerMap;

	/**
	 * List of all statements of given entity.
	 *
	 * @var StatementList
	 */
	private $statements;

	/**
	 * @var ConstraintLookup
	 */
	private $constraintLookup;

	/**
	 * @param EntityLookup $lookup
	 * @param ConstraintChecker[] $checkerMap
	 * @param ConstraintLookup $constraintRepository
	 */
	public function __construct(
		EntityLookup $lookup,
		array $checkerMap,
		ConstraintLookup $constraintRepository
	) {
		$this->entityLookup = $lookup;
		$this->checkerMap = $checkerMap;
		$this->constraintLookup = $constraintRepository;
	}

	/**
	 * Starts the whole constraint-check process.
	 * Statements of the entity will be checked against every constraint that is defined on the property.
	 *
	 * @param EntityDocument|null $entity
	 *
	 * @return CheckResult[]|null
	 */
	public function checkAgainstConstraints( EntityDocument $entity = null ) {
		if ( $entity instanceof StatementListProvider ) {
			$this->statements = $entity->getStatements();

			$result = $this->checkEveryStatement( $entity );

			return $this->sortResult( $result );
		}

		return null;
	}

	/**
	 * Starts the whole constraint-check process for entity or constraint ID on entity.
	 * Statements of the entity will be checked against every constraint that is defined on the property.
	 *
	 * @param EntityId $entityId
	 * @param array $constraintIds
	 *
	 * @return CheckResult[]
	 *
	 */
	public function checkAgainstConstraintsOnEntityId( EntityId $entityId, $constraintIds = null ) {

		$entity = $this->entityLookup->getEntity( $entityId );
		if ( $entity instanceof StatementListProvider ) {
			$this->statements = $entity->getStatements();
			$result = $this->checkEveryStatement( $this->entityLookup->getEntity( $entityId ), $constraintIds );
			$output = $this->sortResult( $result );
			return $output;
		}

		return [];
	}

	/**
	 * Starts the whole constraint-check process.
	 * Statements of the entity will be checked against every constraint that is defined on the claim.
	 *
	 * @param StatementGuid $guid
	 * @param array $constraintIds
	 * @return CheckResult[]
	 */
	public function checkAgainstConstraintsOnClaimId( StatementGuid $guid, $constraintIds = null ) {

		$entityId = $guid->getEntityId();
		$entity = $this->entityLookup->getEntity( $entityId );
		if ( $entity instanceof StatementListProvider ) {
			$statement = $entity->getStatements()->getFirstStatementWithGuid( $guid->getSerialization() );
			if ( $statement ) {
				$result = $this->checkStatement( $entity, $statement, $constraintIds );
				$output = $this->sortResult( $result );
				return $output;
			}
		}

		return [];
	}

	/**
	 * @param EntityDocument|StatementListProvider $entity
	 * @param string[]|null $constraintIds list of constraints to check (if null: all constraints)
	 *
	 * @return CheckResult[]
	 */
	private function checkEveryStatement( EntityDocument $entity, $constraintIds = null ) {
		$result = [];

		/** @var Statement $statement */
		foreach ( $this->statements as $statement ) {
			$result = array_merge( $result, $this->checkStatement( $entity, $statement, $constraintIds ) );
		}

		return $result;
	}

	/**
	 *
	 * @param EntityDocument|StatementListProvider $entity
	 * @param Statement $statement
	 * @param string[]|null $constraintIds list of constraints to check (if null: all constraints)
	 *
	 *
	 * @return CheckResult[]
	 */
	private function checkStatement( EntityDocument $entity, Statement $statement, $constraintIds = null ) {
		$result = [];

		if ( $statement->getMainSnak()->getType() !== 'value' ) {
			// skip 'somevalue' and 'novalue' cases, todo: handle in a better way
			return [];
		}

		$constraints = $this->constraintLookup->queryConstraintsForProperty(
			$statement->getPropertyId()
		);
		if ( $constraintIds !== null ) {
			$constraintsToUse = [];
			foreach ( $constraints as $constraint ) {
				if ( in_array( $constraint->getConstraintId(), $constraintIds ) ) {
					$constraintsToUse[] = $constraint;
				}
			}
		} else {
			$constraintsToUse = $constraints;
		}
		$result = array_merge(
			$result,
			$this->checkConstraintsForStatementOnEntity( $constraintsToUse, $entity, $statement )
		);

		return $result;
	}

	/**
	 * @param Constraint[] $constraints
	 * @param EntityDocument|StatementListProvider $entity
	 * @param Statement $statement
	 *
	 * @return CheckResult[]
	 */
	private function checkConstraintsForStatementOnEntity( array $constraints, EntityDocument $entity, $statement ) {
		$entityId = $entity->getId();
		$result = [];

		foreach ( $constraints as $constraint ) {
			$parameters = $constraint->getConstraintParameters();

			if ( array_key_exists( 'known_exception', $parameters )
				&& in_array( $entityId->getSerialization(), explode( ',', $parameters['known_exception'] ) )
			) {
				$result[] = new CheckResult(
					$entityId,
					$statement,
					$constraint->getConstraintTypeQid(),
					$constraint->getConstraintId(),
					// TODO: Display parameters anyway.
					[],
					CheckResult::STATUS_EXCEPTION,
					'This entity is a known exception for this constraint and has been marked as such.'
				);
				continue;
			}

			$result[ ] = $this->getCheckResultFor( $statement, $constraint, $entity );
		}

		return $result;
	}

	/**
	 * @param Statement $statement
	 * @param Constraint $constraint
	 * @param EntityDocument|StatementListProvider $entity
	 *
	 * @throws InvalidArgumentException
	 * @return CheckResult
	 */
	private function getCheckResultFor( Statement $statement, Constraint $constraint, EntityDocument $entity ) {
		if ( array_key_exists( $constraint->getConstraintTypeQid(), $this->checkerMap ) ) {
			$checker = $this->checkerMap[$constraint->getConstraintTypeQid()];
			$statsd = MediaWikiServices::getInstance()->getStatsdDataFactory();

			$startTime = microtime( true );
			try {
				$result = $checker->checkConstraint( $statement, $constraint, $entity );
			} catch ( ConstraintParameterException $e ) {
				$result = new CheckResult(
					$entity->getId(),
					$statement,
					$constraint->getConstraintTypeQid(),
					$constraint->getConstraintId(),
					[],
					CheckResult::STATUS_VIOLATION,
					$e->getMessage()
				);
			} catch ( SparqlHelperException $e ) {
				$result = new CheckResult(
					$entity->getId(),
					$statement,
					$constraint->getConstraintTypeQid(),
					$constraint->getConstraintId(),
					[],
					CheckResult::STATUS_VIOLATION,
					wfMessage( 'wbqc-violation-message-sparql-error' )->escaped()
				);
			}
			$statsd->timing(
				'wikibase.quality.constraints.check.timing.' . $constraint->getConstraintTypeQid(),
				( microtime( true ) - $startTime ) * 1000
			);

			return $result;
		} else {
			return new CheckResult( $entity->getId(), $statement, $constraint->getConstraintTypeQid(),
				$constraint->getConstraintId(), null, CheckResult::STATUS_TODO, null );
		}
	}

	/**
	 * @param CheckResult[] $result
	 *
	 * @return CheckResult[]
	 */
	private function sortResult( array $result ) {
		if ( count( $result ) < 2 ) {
			return $result;
		}

		$sortFunction = function ( CheckResult $a, CheckResult $b ) {
			$order = [ 'other' => 4, 'compliance' => 3, 'exception' => 2, 'violation' => 1 ];

			$statusA = $a->getStatus();
			$statusB = $b->getStatus();

			$orderA = array_key_exists( $statusA, $order ) ? $order[ $statusA ] : $order[ 'other' ];
			$orderB = array_key_exists( $statusB, $order ) ? $order[ $statusB ] : $order[ 'other' ];

			if ( $orderA === $orderB ) {
				$pidA = $a->getPropertyId()->getSerialization();
				$pidB = $b->getPropertyId()->getSerialization();

				if ( $pidA === $pidB ) {
					$hashA = $a->getStatement()->getHash();
					$hashB = $b->getStatement()->getHash();

					if ( $hashA === $hashB ) {
						$nameA = $a->getConstraintName();
						$nameB = $b->getConstraintName();

						if ( $nameA == $nameB ) {
							return 0;
						} else {
							return ( $nameA > $nameB ) ? 1 : -1;
						}
					} else {
						return ( $hashA > $hashB ) ? 1 : -1;
					}
				} else {
					return ( $pidA > $pidB ) ? 1 : -1;
				}
			} else {
				return ( $orderA > $orderB ) ? 1 : -1;
			}
		};

		uasort( $result, $sortFunction );

		return $result;
	}

}
