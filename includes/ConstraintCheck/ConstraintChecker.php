<?php

namespace WikidataQuality\ConstraintReport\ConstraintCheck;

use Wikibase\Lib\Store\EntityLookup;
use LoadBalancer;
use Wikibase\Repo\Store;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Statement\StatementList;
use Wikibase\DataModel\Snak;
use Wikibase\DataModel\Entity\Entity;
use Wikibase\DataModel\Entity\PropertyId;
use DataValues\DataValue;
use WikidataQuality\ConstraintReport\ConstraintCheck\Checker\ValueCountChecker;
use WikidataQuality\ConstraintReport\ConstraintCheck\Checker\CommonsLinkChecker;
use WikidataQuality\ConstraintReport\ConstraintCheck\Checker\ConnectionChecker;
use WikidataQuality\ConstraintReport\ConstraintCheck\Checker\QualifierChecker;
use WikidataQuality\ConstraintReport\ConstraintCheck\Checker\RangeChecker;
use WikidataQuality\ConstraintReport\ConstraintCheck\Checker\TypeChecker;
use WikidataQuality\ConstraintReport\ConstraintCheck\Checker\FormatChecker;
use WikidataQuality\ConstraintReport\ConstraintCheck\Checker\OneOfChecker;
use WikidataQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper;
use WikidataQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;


/**
 * Class ConstraintCheck
 * Used to start the constraint-check process
 *
 * @package WikidataQuality\ConstraintReport\ConstraintCheck
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class ConstraintChecker {

	/**
	 * Wikibase entity lookup.
	 *
	 * @var EntityLookup
	 */
	private $entityLookup;

	/**
	 * Wikibase load balancer for database connections.
	 *
	 * @var LoadBalancer
	 */
	private $loadBalancer;

	/**
	 * Class for helper functions for constraint checkers.
	 *
	 * @var ConstraintReportHelper
	 */
	private $helper;

	/**
	 * Checks Single, Multi and Unique value constraint.
	 *
	 * @var ValueCountChecker
	 */
	private $valueCountChecker;

	/**
	 * Checks Qualifier and Qualifiers constraint.
	 *
	 * @var QualifierChecker
	 */
	private $qualifierChecker;

	/**
	 * Checks Conflicts with, Item, Target required claim, Symmetric and Inverse constraint.
	 *
	 * @var ConnectionChecker
	 */
	private $connectionChecker;

	/**
	 * Checks Type and Value type constraint.
	 *
	 * @var TypeChecker
	 */
	private $typeChecker;

	/**
	 * Checks Range and Diff within range constraint.
	 *
	 * @var RangeChecker
	 */
	private $rangeChecker;

	/**
	 * Checks Format constraint.
	 *
	 * @var FormatChecker
	 */
	private $formatChecker;

	/**
	 * Checks One of constraint.
	 *
	 * @var OneOfChecker
	 */
	private $oneOfChecker;

	/**
	 * Checks Commons link constraint.
	 *
	 * @var CommonsLinkChecker
	 */
	private $commonsLinkChecker;

	/**
	 * List of all statements of given entity.
	 *
	 * @var StatementList
	 */
	private $statements;

	public function __construct( EntityLookup $lookup ) {
		// Get entity lookup
		$this->entityLookup = $lookup;

		// Get load balancer
		wfWaitForSlaves();
		$this->loadBalancer = wfGetLB();

		// Get helper to pass it to every checker
		$this->helper = new ConstraintReportHelper();
	}

	/**
	 * Starts the whole constraint-check process.
	 * Statements of the entity will be checked against every constraint that is defined on the property.
	 *
	 * @param Entity $entity - Entity that shall be checked against constraints
	 *
	 * @return array|null
	 */
	public function execute( $entity ) {
		if ( $entity ) {

			$this->statements = $entity->getStatements();

			$dbr = wfGetDB( DB_SLAVE );

			$result = $this->checkEveryStatement( $entity, $dbr );

			if ( count( $result ) > 1 ) {
				return $this->sortResult( $result );
			} else {
				return $result;
			}

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

			$constraints = $this->queryConstraintsForProperty( $dbr, $numericPropertyId );

			$result = array_merge( $result, $this->checkConstraintsForStatementOnEntity( $constraints, $entity, $statement ) );

		}

		return $result;
	}

	private function checkConstraintsForStatementOnEntity( $constraints, $entity, $statement ) {
		$result = array ();
		foreach ( $constraints as $row ) {
			$constraintParameters = json_decode( $row->constraint_parameters );

			if ( in_array( $entity->getId()->getSerialization(), $this->helper->stringToArray( $this->helper->getPropertyOfJson( $constraintParameters, 'known_exception' ) ) ) ) {
				$message = 'This entity is a known exception for this constraint and has been marked as such.';
				$result[ ] = new CheckResult( $statement, $row->constraint_type_qid, array (), 'exception', $message ); // todo: display parameters anyway
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
		$classArray = $this->helper->stringToArray( $this->helper->getPropertyOfJson( $constraintParameters, 'class' ) );
		$itemArray = $this->helper->stringToArray( $this->helper->getPropertyOfJson( $constraintParameters, 'item' ) );
		$propertyArray = $this->helper->stringToArray( $this->helper->getPropertyOfJson( $constraintParameters, 'property' ) );

		switch ( $constraintTypeQid ) {

			// ConnectionChecker
			case 'Conflicts with':
				return $this->getConnectionChecker()
							->checkConflictsWithConstraint( $statement, $this->helper->getPropertyOfJson( $constraintParameters, 'property' ), $itemArray );
			case 'Item':
				return $this->getConnectionChecker()
							->checkItemConstraint( $statement, $this->helper->getPropertyOfJson( $constraintParameters, 'property' ), $itemArray );
			case 'Target required claim':
				return $this->getConnectionChecker()
							->checkTargetRequiredClaimConstraint( $statement, $this->helper->getPropertyOfJson( $constraintParameters, 'property' ), $itemArray );
			case 'Symmetric':
				return $this->getConnectionChecker()
							->checkSymmetricConstraint( $statement, $entity->getId()->getSerialization() );
			case 'Inverse':
				return $this->getConnectionChecker()
							->checkInverseConstraint( $statement, $entity->getId()->getSerialization(), $this->helper->getPropertyOfJson( $constraintParameters, 'property' ) );

			// QualifierChecker
			case 'Qualifier':
				return $this->getQualifierChecker()
							->checkQualifierConstraint( $statement );
			case 'Qualifiers':
				return $this->getQualifierChecker()
							->checkQualifiersConstraint( $statement, $propertyArray );
			case 'Mandatory qualifiers':
				return $this->getQualifierChecker()
							->checkMandatoryQualifiersConstraint( $statement, $propertyArray );

			// RangeChecker
			case 'Range':
				return $this->getRangeChecker()
							->checkRangeConstraint( $statement, $this->helper->getPropertyOfJson( $constraintParameters, 'minimum_quantity' ), $this->helper->getPropertyOfJson( $constraintParameters, 'maximum_quantity' ), $this->helper->getPropertyOfJson( $constraintParameters, 'minimum_date' ), $this->helper->getPropertyOfJson( $constraintParameters, 'maximum_date' ) );
			case 'Diff within range':
				return $this->getRangeChecker()
							->checkDiffWithinRangeConstraint( $statement, $this->helper->getPropertyOfJson( $constraintParameters, 'property' ), $this->helper->getPropertyOfJson( $constraintParameters, 'minimum_quantity' ), $this->helper->getPropertyOfJson( $constraintParameters, 'maximum_quantity' ) );

			// Type Checker
			case 'Type':
				return $this->getTypeChecker()
							->checkTypeConstraint( $statement, $classArray, $this->helper->getPropertyOfJson( $constraintParameters, 'relation' ) );
			case 'Value type':
				return $this->getTypeChecker()
							->checkValueTypeConstraint( $statement, $classArray, $this->helper->getPropertyOfJson( $constraintParameters, 'relation' ) );

			// ValueCountChecker
			case 'Single value':
				return $this->getValueCountChecker()
							->checkSingleValueConstraint( $statement );
			case 'Multi value':
				return $this->getValueCountChecker()
							->checkMultiValueConstraint( $statement );
			case 'Unique value':
				return $this->getValueCountChecker()
							->checkUniqueValueConstraint( $statement );

			// Rest
			case 'Format':
				return $this->getFormatChecker()
							->checkFormatConstraint( $statement, $this->helper->getPropertyOfJson( $constraintParameters, 'pattern' ) );
			case 'Commons link':
				return $this->getCommonsLinkChecker()
							->checkCommonsLinkConstraint( $statement, $this->helper->getPropertyOfJson( $constraintParameters, 'namespace' ) );
			case 'One of':
				return $this->getOneOfChecker()
							->checkOneOfConstraint( $statement, $itemArray );

			// error case, should not be invoked
			default:
				return new CheckResult( $statement, $constraintTypeQid );
		}
	}

	private function queryConstraintsForProperty( $dbr, $prop ) {
		return $dbr->select(
			CONSTRAINT_TABLE,
			array ( 'pid', 'constraint_type_qid', 'constraint_parameters' ),
			( "pid = $prop" ),
			__METHOD__,
			array ( '' )
		);
	}

	private function sortResult( $result ) {
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

	/**
	 * @return ValueCountChecker
	 */
	private function getValueCountChecker() {
		if ( !isset( $this->valueCountChecker ) ) {
			$this->valueCountChecker = new ValueCountChecker( $this->statements, $this->helper );
		}
		return $this->valueCountChecker;
	}

	/**
	 * @return ConnectionChecker
	 */
	private function getConnectionChecker() {
		if ( !isset( $this->connectionChecker ) ) {
			$this->connectionChecker = new ConnectionChecker( $this->statements, $this->entityLookup, $this->helper );
		}
		return $this->connectionChecker;
	}

	/**
	 * @return QualifierChecker
	 */
	private function getQualifierChecker() {
		if ( !isset( $this->qualifierChecker ) ) {
			$this->qualifierChecker = new QualifierChecker( $this->helper );
		}
		return $this->qualifierChecker;
	}

	/**
	 * @return RangeChecker
	 */
	private function getRangeChecker() {
		if ( !isset( $this->rangeChecker ) ) {
			$this->rangeChecker = new RangeChecker( $this->statements, $this->helper );
		}
		return $this->rangeChecker;
	}

	/**
	 * @return TypeChecker
	 */
	private function getTypeChecker() {
		if ( !isset( $this->typeChecker ) ) {
			$this->typeChecker = new TypeChecker( $this->statements, $this->entityLookup, $this->helper );
		}
		return $this->typeChecker;
	}

	/**
	 * @return OneOfChecker
	 */
	private function getOneOfChecker() {
		if ( !isset( $this->oneOfChecker ) ) {
			$this->oneOfChecker = new OneOfChecker( $this->helper );
		}
		return $this->oneOfChecker;
	}

	/**
	 * @return CommonsLinkChecker
	 */
	private function getCommonsLinkChecker() {
		if ( !isset( $this->commonsLinkChecker ) ) {
			$this->commonsLinkChecker = new CommonsLinkChecker( $this->helper );
		}
		return $this->commonsLinkChecker;
	}

	/**
	 * @return FormatChecker
	 */
	private function getFormatChecker() {
		if ( !isset( $this->formatChecker ) ) {
			$this->formatChecker = new FormatChecker( $this->helper );
		}
		return $this->formatChecker;
	}

}