<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck;

use InvalidArgumentException;
use MediaWiki\MediaWikiServices;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Statement\StatementList;
use Wikibase\DataModel\Statement\StatementListProvider;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikibaseQuality\ConstraintReport\ConstraintRepository;
use WikibaseQuality\ConstraintReport\Constraint;

/**
 * Class DelegatingConstraintCheck
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
	 * Class for helper functions for constraint checkers.
	 *
	 * @var ConstraintParameterParser
	 */
	private $helper;

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
	 * @param EntityLookup $lookup
	 * @param ConstraintChecker[] $checkerMap
	 */
	public function __construct( EntityLookup $lookup, array $checkerMap ) {
		$this->entityLookup = $lookup;
		$this->checkerMap = $checkerMap;
		$this->helper = new ConstraintParameterParser();
		$this->constraintRepository = new ConstraintRepository();
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
	 * @param EntityDocument|StatementListProvider $entity
	 *
	 * @return CheckResult[]
	 */
	private function checkEveryStatement( EntityDocument $entity ) {
		$result = array ();

		/** @var Statement $statement */
		foreach ( $this->statements as $statement ) {
			if ( $statement->getMainSnak()->getType() !== 'value' ) {
				// skip 'somevalue' and 'novalue' cases, todo: handle in a better way
				continue;
			}

			$propertyId = $statement->getPropertyId();
			$numericPropertyId = $propertyId->getNumericId();

			$constraints = $this->constraintRepository->queryConstraintsForProperty( $numericPropertyId );

			$result = array_merge( $result, $this->checkConstraintsForStatementOnEntity( $constraints, $entity, $statement ) );
		}

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
		$result = array ();

		foreach ( $constraints as $constraint ) {
			$parameter = $constraint->getConstraintParameters();
			if ( array_key_exists( 'known_exception', $parameter) && in_array( $entity->getId()->getSerialization(), explode( ',', $parameter['known_exception'] ) ) ) {
				$message = 'This entity is a known exception for this constraint and has been marked as such.';
				$result[] = new CheckResult( $statement, $constraint->getConstraintTypeQid(), array (), CheckResult::STATUS_EXCEPTION, $message ); // todo: display parameters anyway
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
		if( array_key_exists( $constraint->getConstraintTypeQid(), $this->checkerMap ) ) {
			$checker = $this->checkerMap[$constraint->getConstraintTypeQid()];
			$statsd = MediaWikiServices::getInstance()->getStatsdDataFactory();

			$startTime = microtime( true );
			$result = $checker->checkConstraint( $statement, $constraint, $entity );
			$statsd->timing(
				'wikibase.quality.constraints.check.timing' . $constraint->getConstraintTypeQid(),
				( microtime( true ) - $startTime ) * 1000
			);

			return $result;
		} else {
			return new CheckResult( $statement, $constraint->getConstraintTypeQid() );
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
			$order = array ( 'other' => 4, 'compliance' => 3, 'exception' => 2, 'violation' => 1 );

			$statusA = $a->getStatus();
			$statusB = $b->getStatus();

			$orderA = array_key_exists( $statusA, $order ) ? $order[ $statusA ] : $order[ 'other' ];
			$orderB = array_key_exists( $statusB, $order ) ? $order[ $statusB ] : $order[ 'other' ];

			if ( $orderA === $orderB ) {
				return 0;
			} else {
				return ( $orderA > $orderB ) ? 1 : -1;
			}
		};

		uasort( $result, $sortFunction );

		return $result;
	}

}
