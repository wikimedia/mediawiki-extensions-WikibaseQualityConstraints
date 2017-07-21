<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck;

use InvalidArgumentException;
use LogicException;
use MediaWiki\MediaWikiServices;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\DataModel\Services\Statement\StatementGuidParser;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Statement\StatementList;
use Wikibase\DataModel\Statement\StatementListProvider;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterException;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;
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
	 * @var ConstraintParameterParser
	 */
	private $constraintParameterParser;

	/**
	 *
	 * @var StatementGuidParser
	 */
	private $statementGuidParser;

	/**
	 * @param EntityLookup $lookup
	 * @param ConstraintChecker[] $checkerMap
	 * @param ConstraintLookup $constraintRepository
	 * @param ConstraintParameterParser $constraintParameterParser
	 * @param StatementGuidParser $statementGuidParser
	 */
	public function __construct(
		EntityLookup $lookup,
		array $checkerMap,
		ConstraintLookup $constraintRepository,
		ConstraintParameterParser $constraintParameterParser,
		StatementGuidParser $statementGuidParser
	) {
		$this->entityLookup = $lookup;
		$this->checkerMap = $checkerMap;
		$this->constraintLookup = $constraintRepository;
		$this->constraintParameterParser = $constraintParameterParser;
		$this->statementGuidParser = $statementGuidParser;
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
	 * @param string $guid
	 * @param array $constraintIds
	 * @return CheckResult[]
	 */
	public function checkAgainstConstraintsOnClaimId( $guid, $constraintIds = null ) {

		$parsedGuid = $this->statementGuidParser->parse( $guid );
		$entityId = $parsedGuid->getEntityId();
		$entity = $this->entityLookup->getEntity( $entityId );
		if ( $entity instanceof StatementListProvider ) {
			$statement = $entity->getStatements()->getFirstStatementWithGuid( $guid );
			if ( $statement ) {
				$result = $this->checkStatement( $entity, $statement, $constraintIds );
				$output = $this->sortResult( $result );
				return $output;
			}
		}

		return [];
	}

	/**
	 * Like ConstraintChecker::checkConstraintParameters,
	 * but for meta-parameters common to all checkers.
	 *
	 * @param Constraint $constraint
	 *
	 * @return ConstraintParameterException[]
	 */
	private function checkCommonConstraintParameters( Constraint $constraint ) {
		$constraintParameters = $constraint->getConstraintParameters();
		try {
			$this->constraintParameterParser->checkError( $constraintParameters );
		} catch ( ConstraintParameterException $e ) {
			return [ $e ];
		}

		// TODO implement T171198 here
		return [];
	}

	/**
	 * Check the constraint parameters of all constraints for the given property ID.
	 *
	 * @param PropertyId $propertyId
	 * @return ConstraintParameterException[][] first level indexed by constraint ID,
	 * second level like checkConstraintParametersOnConstraintId (but without possibility of null)
	 */
	public function checkConstraintParametersOnPropertyId( PropertyId $propertyId ) {
		$constraints = $this->constraintLookup->queryConstraintsForProperty( $propertyId );
		$result = [];

		foreach ( $constraints as $constraint ) {
			$problems = $this->checkCommonConstraintParameters( $constraint );

			if ( array_key_exists( $constraint->getConstraintTypeItemId(), $this->checkerMap ) ) {
				$checker = $this->checkerMap[$constraint->getConstraintTypeItemId()];
				$problems = array_merge( $problems, $checker->checkConstraintParameters( $constraint ) );
			}

			$result[$constraint->getConstraintId()] = $problems;
		}

		return $result;
	}

	/**
	 * Check the constraint parameters of the constraint with the given ID.
	 *
	 * @param string $constraintId
	 *
	 * @return ConstraintParameterException[]|null list of constraint parameter exceptions
	 * (empty means all parameters okay), or null if constraint is not found
	 */
	public function checkConstraintParametersOnConstraintId( $constraintId ) {
		$propertyId = $this->statementGuidParser->parse( $constraintId )->getEntityId();
		$constraints = $this->constraintLookup->queryConstraintsForProperty( $propertyId );

		foreach ( $constraints as $constraint ) {
			if ( $constraint->getConstraintId() === $constraintId ) {
				$problems = $this->checkCommonConstraintParameters( $constraint );

				if ( array_key_exists( $constraint->getConstraintTypeItemId(), $this->checkerMap ) ) {
					$checker = $this->checkerMap[$constraint->getConstraintTypeItemId()];
					$problems = array_merge( $problems, $checker->checkConstraintParameters( $constraint ) );
				}

				return $problems;
			}
		}

		return null;
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
			try {
				$exceptions = $this->constraintParameterParser->parseExceptionParameter( $parameters );
			} catch ( ConstraintParameterException $e ) {
				$result[] = new CheckResult(
					$entity->getId(),
					$statement,
					$constraint,
					[],
					CheckResult::STATUS_BAD_PARAMETERS,
					$e->getMessage()
				);
				continue;
			}

			if ( in_array( $entityId, $exceptions ) ) {
				$result[] = new CheckResult(
					$entityId,
					$statement,
					$constraint,
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
		if ( array_key_exists( $constraint->getConstraintTypeItemId(), $this->checkerMap ) ) {
			$checker = $this->checkerMap[$constraint->getConstraintTypeItemId()];
			$statsd = MediaWikiServices::getInstance()->getStatsdDataFactory();

			$startTime = microtime( true );
			try {
				$result = $checker->checkConstraint( $statement, $constraint, $entity );
			} catch ( ConstraintParameterException $e ) {
				$result = new CheckResult(
					$entity->getId(),
					$statement,
					$constraint,
					[],
					CheckResult::STATUS_BAD_PARAMETERS,
					$e->getMessage()
				);
			} catch ( SparqlHelperException $e ) {
				$result = new CheckResult(
					$entity->getId(),
					$statement,
					$constraint,
					[],
					CheckResult::STATUS_VIOLATION,
					wfMessage( 'wbqc-violation-message-sparql-error' )->escaped()
				);
			}
			$statsd->timing(
				'wikibase.quality.constraints.check.timing.' . $constraint->getConstraintTypeItemId(),
				( microtime( true ) - $startTime ) * 1000
			);

			try {
				$constraintStatus = $this->constraintParameterParser
					->parseConstraintStatusParameter( $constraint->getConstraintParameters() );
			} catch ( ConstraintParameterException $e ) {
				$result = new CheckResult(
					$entity->getId(),
					$statement,
					$constraint,
					[],
					CheckResult::STATUS_BAD_PARAMETERS,
					$e->getMessage()
				);
				$constraintStatus = null;
			}
			if ( $constraintStatus === null ) {
				// downgrade violation to warning
				if ( $result->getStatus() === CheckResult::STATUS_VIOLATION ) {
					$result->setStatus( CheckResult::STATUS_WARNING );
				}
			} else {
				if ( $constraintStatus !== 'mandatory' ) {
					throw new LogicException(
						"Unknown constraint status '$constraintStatus', " .
						"only known status is 'mandatory'"
					);
				}
				$result->getParameters['constraint_status'] = $constraintStatus;
			}

			return $result;
		} else {
			return new CheckResult( $entity->getId(), $statement, $constraint, [], CheckResult::STATUS_TODO, null );
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
			$orderNum = 0;
			$order = [
				'bad-parameters' => $orderNum++,
				'violation' => $orderNum++,
				'warning' => $orderNum++,
				'exception' => $orderNum++,
				'compliance' => $orderNum++,
				'deprecated' => $orderNum++,
				'other' => $orderNum++,
			];

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
						$typeA = $a->getConstraint()->getConstraintTypeItemId();
						$typeB = $b->getConstraint()->getConstraintTypeItemId();

						if ( $typeA == $typeB ) {
							return 0;
						} else {
							return ( $typeA > $typeB ) ? 1 : -1;
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
