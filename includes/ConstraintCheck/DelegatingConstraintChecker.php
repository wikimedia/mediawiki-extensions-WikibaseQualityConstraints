<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck;

use InvalidArgumentException;
use LogicException;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\DataModel\Services\Statement\StatementGuidParser;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Statement\StatementListProvider;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\Context;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\MainSnakContext;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\QualifierContext;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\ReferenceContext;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\StatementContext;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterException;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\LoggingHelper;
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
	 * @var ConstraintLookup
	 */
	private $constraintLookup;

	/**
	 * @var ConstraintParameterParser
	 */
	private $constraintParameterParser;

	/**
	 * @var StatementGuidParser
	 */
	private $statementGuidParser;

	/**
	 * @var LoggingHelper
	 */
	private $loggingHelper;

	/**
	 * @var bool
	 */
	private $apiV2;

	/**
	 * @var bool
	 */
	private $checkQualifiers;

	/**
	 * @var bool
	 */
	private $checkReferences;

	/**
	 * @param EntityLookup $lookup
	 * @param ConstraintChecker[] $checkerMap
	 * @param ConstraintLookup $constraintRepository
	 * @param ConstraintParameterParser $constraintParameterParser
	 * @param StatementGuidParser $statementGuidParser
	 * @param LoggingHelper $loggingHelper
	 * @param bool $apiV2 whether to use the new API output format
	 * @param bool $checkQualifiers whether to check qualifiers
	 * @param bool $checkReferences whether to check references
	 */
	public function __construct(
		EntityLookup $lookup,
		array $checkerMap,
		ConstraintLookup $constraintRepository,
		ConstraintParameterParser $constraintParameterParser,
		StatementGuidParser $statementGuidParser,
		LoggingHelper $loggingHelper,
		$apiV2,
		$checkQualifiers,
		$checkReferences
	) {
		$this->entityLookup = $lookup;
		$this->checkerMap = $checkerMap;
		$this->constraintLookup = $constraintRepository;
		$this->constraintParameterParser = $constraintParameterParser;
		$this->statementGuidParser = $statementGuidParser;
		$this->loggingHelper = $loggingHelper;
		$this->apiV2 = $apiV2;
		$this->checkQualifiers = $apiV2 && $checkQualifiers;
		$this->checkReferences = $apiV2 && $checkReferences;
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

		$problems = [];
		try {
			$this->constraintParameterParser->parseExceptionParameter( $constraintParameters );
		} catch ( ConstraintParameterException $e ) {
			$problems[] = $e;
		}
		try {
			$this->constraintParameterParser->parseConstraintStatusParameter( $constraintParameters );
		} catch ( ConstraintParameterException $e ) {
			$problems[] = $e;
		}
		return $problems;
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
		foreach ( $entity->getStatements() as $statement ) {
			$result = array_merge( $result, $this->checkStatement( $entity, $statement, $constraintIds ) );
		}

		return $result;
	}

	/**
	 * @param EntityDocument|StatementListProvider $entity
	 * @param Statement $statement
	 * @param string[]|null $constraintIds list of constraints to check (if null: all constraints)
	 *
	 * @return CheckResult[]
	 */
	private function checkStatement( EntityDocument $entity, Statement $statement, $constraintIds = null ) {
		$result = [];

		$result = array_merge( $result,
			$this->checkConstraintsForMainSnak( $entity, $statement, $constraintIds ) );

		if ( $this->checkQualifiers ) {
			$result = array_merge( $result,
				$this->checkConstraintsForQualifiers( $entity, $statement, $constraintIds ) );
		}

		if ( $this->checkReferences ) {
			$result = array_merge( $result,
				$this->checkConstraintsForReferences( $entity, $statement, $constraintIds ) );
		}

		return $result;
	}

	/**
	 * Get the constraints to actually check for a given property ID.
	 * If $constraintIds is not null, only check constraints with those constraint IDs,
	 * otherwise check all constraints for that property.
	 *
	 * @param PropertyId $propertyId
	 * @param string[]|null $constraintIds
	 * @return Constraint[]
	 */
	private function getConstraintsToUse( PropertyId $propertyId, array $constraintIds = null ) {
		$constraints = $this->constraintLookup->queryConstraintsForProperty( $propertyId );
		if ( $constraintIds !== null ) {
			$constraintsToUse = [];
			foreach ( $constraints as $constraint ) {
				if ( in_array( $constraint->getConstraintId(), $constraintIds ) ) {
					$constraintsToUse[] = $constraint;
				}
			}
			return $constraintsToUse;
		} else {
			return $constraints;
		}
	}

	/**
	 * @param EntityDocument $entity
	 * @param Statement $statement
	 * @param string[]|null $constraintIds list of constraints to check (if null: all constraints)
	 * @return CheckResult[]
	 */
	private function checkConstraintsForMainSnak(
		EntityDocument $entity,
		Statement $statement,
		array $constraintIds = null
	) {
		$result = [];
		$context = $this->apiV2 ?
			new MainSnakContext( $entity, $statement ) :
			new StatementContext( $entity, $statement );
		$constraints = $this->getConstraintsToUse(
			$statement->getPropertyId(),
			$constraintIds
		);

		foreach ( $constraints as $constraint ) {
			$parameters = $constraint->getConstraintParameters();
			try {
				$exceptions = $this->constraintParameterParser->parseExceptionParameter( $parameters );
			} catch ( ConstraintParameterException $e ) {
				$result[] = new CheckResult( $context, $constraint, [], CheckResult::STATUS_BAD_PARAMETERS, $e->getMessage() );
				continue;
			}

			if ( in_array( $entity->getId(), $exceptions ) ) {
				$message = wfMessage( 'wbqc-exception-message' )->escaped();
				$result[] = new CheckResult( $context, $constraint, [], CheckResult::STATUS_EXCEPTION, $message );
				continue;
			}

			$result[] = $this->getCheckResultFor( $context, $constraint );
		}

		return $result;
	}

	/**
	 * @param EntityDocument $entity
	 * @param Statement $statement
	 * @param string[]|null $constraintIds list of constraints to check (if null: all constraints)
	 * @return CheckResult[]
	 */
	private function checkConstraintsForQualifiers(
		EntityDocument $entity,
		Statement $statement,
		array $constraintIds = null
	) {
		$result = [];

		foreach ( $statement->getQualifiers() as $qualifier ) {
			$qualifierContext = new QualifierContext( $entity, $statement, $qualifier );
			$qualifierConstraints = $this->getConstraintsToUse(
				$qualifierContext->getSnak()->getPropertyId(),
				$constraintIds
			);
			foreach ( $qualifierConstraints as $qualifierConstraint ) {
				$result[] = $this->getCheckResultFor( $qualifierContext, $qualifierConstraint );
			}
		}

		return $result;
	}

	/**
	 * @param EntityDocument $entity
	 * @param Statement $statement
	 * @param string[]|null $constraintIds list of constraints to check (if null: all constraints)
	 * @return CheckResult[]
	 */
	private function checkConstraintsForReferences(
		EntityDocument $entity,
		Statement $statement,
		array $constraintIds = null
	) {
		$result = [];

		foreach ( $statement->getReferences() as $reference ) {
			foreach ( $reference->getSnaks() as $snak ) {
				$referenceContext = new ReferenceContext(
					$entity, $statement, $reference, $snak
				);
				$referenceConstraints = $this->getConstraintsToUse(
					$referenceContext->getSnak()->getPropertyId(),
					$constraintIds
				);
				foreach ( $referenceConstraints as $referenceConstraint ) {
					$result[] = $this->getCheckResultFor(
						$referenceContext,
						$referenceConstraint
					);
				}
			}
		}

		return $result;
	}

	/**
	 * @param Context $context
	 * @param Constraint $constraint
	 *
	 * @throws InvalidArgumentException
	 * @return CheckResult
	 */
	private function getCheckResultFor( Context $context, Constraint $constraint ) {
		if ( array_key_exists( $constraint->getConstraintTypeItemId(), $this->checkerMap ) ) {
			$checker = $this->checkerMap[$constraint->getConstraintTypeItemId()];

			$startTime = microtime( true );
			try {
				$result = $checker->checkConstraint( $context, $constraint );
			} catch ( ConstraintParameterException $e ) {
				$result = new CheckResult( $context, $constraint, [], CheckResult::STATUS_BAD_PARAMETERS, $e->getMessage() );
			} catch ( SparqlHelperException $e ) {
				$message = wfMessage( 'wbqc-violation-message-sparql-error' )->escaped();
				$result = new CheckResult( $context, $constraint, [], CheckResult::STATUS_VIOLATION, $message );
			}
			$endTime = microtime( true );

			try {
				$constraintStatus = $this->constraintParameterParser
					->parseConstraintStatusParameter( $constraint->getConstraintParameters() );
			} catch ( ConstraintParameterException $e ) {
				$result = new CheckResult( $context, $constraint, [], CheckResult::STATUS_BAD_PARAMETERS, $e->getMessage() );
				$constraintStatus = null;
			}
			if ( $constraintStatus === null ) {
				// downgrade violation to warning
				if ( $result->getStatus() === CheckResult::STATUS_VIOLATION ) {
					$result->setStatus( CheckResult::STATUS_WARNING );
				}
			} else {
				if ( $constraintStatus !== 'mandatory' ) {
					// @codeCoverageIgnoreStart
					throw new LogicException(
						"Unknown constraint status '$constraintStatus', " .
						"only known status is 'mandatory'"
					);
					// @codeCoverageIgnoreEnd
				}
				$result->addParameter( 'constraint_status', $constraintStatus );
			}

			$this->loggingHelper->logConstraintCheck(
				$context,
				$constraint,
				$result,
				get_class( $checker ),
				$endTime - $startTime,
				__METHOD__
			);

			return $result;
		} else {
			return new CheckResult( $context, $constraint, [], CheckResult::STATUS_TODO, null );
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
				CheckResult::STATUS_BAD_PARAMETERS => $orderNum++,
				CheckResult::STATUS_VIOLATION => $orderNum++,
				CheckResult::STATUS_WARNING => $orderNum++,
				CheckResult::STATUS_EXCEPTION => $orderNum++,
				CheckResult::STATUS_COMPLIANCE => $orderNum++,
				CheckResult::STATUS_DEPRECATED => $orderNum++,
				CheckResult::STATUS_NOT_MAIN_SNAK => $orderNum++,
				'other' => $orderNum++,
			];

			$statusA = $a->getStatus();
			$statusB = $b->getStatus();

			$orderA = array_key_exists( $statusA, $order ) ? $order[ $statusA ] : $order[ 'other' ];
			$orderB = array_key_exists( $statusB, $order ) ? $order[ $statusB ] : $order[ 'other' ];

			if ( $orderA === $orderB ) {
				$pidA = $a->getContext()->getSnak()->getPropertyId()->getSerialization();
				$pidB = $b->getContext()->getSnak()->getPropertyId()->getSerialization();

				if ( $pidA === $pidB ) {
					$hashA = $a->getContext()->getSnak()->getHash();
					$hashB = $b->getContext()->getSnak()->getHash();

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
