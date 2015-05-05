<?php

namespace WikidataQuality\ConstraintReport\ConstraintCheck;

use Wikibase\Lib\Store\EntityLookup;
use Wikibase\Repo\Store;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Statement\StatementList;
use Wikibase\DataModel\Snak;
use Wikibase\DataModel\Entity\Entity;
use Wikibase\DataModel\Entity\PropertyId;
use DataValues\DataValue;
use WikidataQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper;
use WikidataQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikidataQuality\ConstraintReport\ConstraintRepository;


/**
 * Class ConstraintCheck
 * Used to start the constraint-check process
 *
 * @package WikidataQuality\ConstraintReport\ConstraintCheck
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
	 * @var ConstraintReportHelper
	 */
	private $helper;

	/**
	 * @var
	 */
	private $checkerMap;

	/**
	 * List of all statements of given entity.
	 *
	 * @var StatementList
	 */
	private $statements;

	public function __construct( EntityLookup $lookup, $checkerMap ) {
		$this->entityLookup = $lookup;
		$this->checkerMap = $checkerMap;
		$this->helper = new ConstraintReportHelper();
		$this->constraintRepository = new ConstraintRepository();
	}

	/**
	 * Starts the whole constraint-check process.
	 * Statements of the entity will be checked against every constraint that is defined on the property.
	 *
	 * @param Entity $entity - Entity that shall be checked against constraints
	 *
	 * @return array|null
	 */
	public function checkAgainstConstraints( $entity ) {
		if ( $entity ) {

			$this->statements = $entity->getStatements();

			$dbr = wfGetDB( DB_SLAVE );

			$result = $this->checkEveryStatement( $entity, $dbr );

			return $this->sortResult( $result );
		}
		return null;
	}

	private function checkEveryStatement( $entity, $dbr ) {
		$result = array ();
		foreach ( $this->statements as $statement ) {

			$claim = $statement->getClaim();

			if ( $claim->getMainSnak()->getType() !== 'value' ) {
				// skip 'somevalue' and 'novalue' cases, todo: handle in a better way
				continue;
			}

			$propertyId = $claim->getPropertyId();
			$numericPropertyId = $propertyId->getNumericId();

			$constraints = $this->constraintRepository->queryConstraintsForProperty( $numericPropertyId );

			$result = array_merge( $result, $this->checkConstraintsForStatementOnEntity( $constraints, $entity, $statement ) );

		}

		return $result;
	}

	private function checkConstraintsForStatementOnEntity( $constraints, $entity, $statement ) {
		$result = array ();
		foreach ( $constraints as $row ) {
			$constraintParameters = json_decode( $row->constraint_parameters );

			if ( in_array( $entity->getId()->getSerialization(), $this->helper->stringToArray( $this->helper->getParameterFromJson( $constraintParameters, 'known_exception' ) ) ) ) {
				$message = 'This entity is a known exception for this constraint and has been marked as such.';
				$result[ ] = new CheckResult( $statement, $row->constraint_type_qid, array (), CheckResult::STATUS_EXCEPTION, $message ); // todo: display parameters anyway
				continue;
			}

			$result[ ] = $this->getCheckResultFor( $statement, $row->constraint_type_qid, $constraintParameters, $entity );
		}
		return $result;
	}

	/**
	 * @param Statement $statement
	 * @param string $constraintTypeQid
	 * @param $constraintParameters
	 * @param Entity $entity
	 *
	 * @return CheckResult
	 */
	private function getCheckResultFor( Statement $statement, $constraintTypeQid, $constraintParameters, Entity $entity ) {
		$parameters = $this->getParameters( $constraintParameters );

		if( array_key_exists( $constraintTypeQid, $this->checkerMap ) ) {
			$checker = $this->checkerMap[$constraintTypeQid];
			return $checker->checkConstraint( $statement, $parameters, $entity );
		} else {
			return new CheckResult( $statement, $constraintTypeQid );
		}
	}

	private function getParameters( $constraintParameters ) {
		return array(
			'class' => $this->helper->stringToArray( $this->helper->getParameterFromJson( $constraintParameters, 'class' ) ),
			'item' => $this->helper->stringToArray( $this->helper->getParameterFromJson( $constraintParameters, 'item' ) ),
			'property' => $this->helper->stringToArray( $this->helper->getParameterFromJson( $constraintParameters, 'property' ) ),
			'minimum_quantity' => $this->helper->getParameterFromJson( $constraintParameters, 'minimum_quantity' ),
			'maximum_quantity' => $this->helper->getParameterFromJson( $constraintParameters, 'maximum_quantity' ),
			'minimum_date' => $this->helper->getParameterFromJson( $constraintParameters, 'minimum_date' ),
			'maximum_date' => $this->helper->getParameterFromJson( $constraintParameters, 'maximum_date' ),
			'namespace' => $this->helper->getParameterFromJson( $constraintParameters, 'namespace' ),
			'pattern' => $this->helper->getParameterFromJson( $constraintParameters, 'pattern' ),
			'relation' => $this->helper->getParameterFromJson( $constraintParameters, 'relation' )
		);
	}

	private function sortResult( $result ) {
		if ( count( $result ) < 2 ) {
			return $result;
		}

		$sortFunction = function ( $a, $b ) {
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