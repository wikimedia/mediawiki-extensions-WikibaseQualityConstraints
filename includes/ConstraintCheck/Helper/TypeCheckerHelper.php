<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Helper;

use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Statement\StatementList;
use Wikibase\DataModel\Statement\StatementListProvider;
use Wikibase\Lib\Store\EntityLookup;

/**
 * Class for helper functions for range checkers.
 *
 * @package WikibaseQuality\ConstraintReport\ConstraintCheck\Helper
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class TypeCheckerHelper {

	const MAX_DEPTH = 20;
	const instanceId = 'P31';
	const subclassId = 'P279';

	/**
	 * @var EntityLookup $entityLookup
	 */
	private $entityLookup;

	public function __construct( EntityLookup $lookup ) {
		$this->entityLookup = $lookup;
	}

	/**
	 * Checks if one of the itemId serializations in $classesToCheck
	 * is subclass of $comparativeClass
	 * Due to cyclic dependencies, the checks stops after a certain
	 * depth is reached
	 *
	 * FIXME: the implementation here relies on the ID being passed being an ItemId.
	 * However, the callers can not provide a guarentee of this.
	 *
	 * @param EntityId $comparativeClass
	 * @param array $classesToCheck
	 * @param int $depth
	 *
	 * @return bool
	 */
	public function isSubclassOf( EntityId $comparativeClass, $classesToCheck, $depth ) {
		/**
		 * @var Item $item
		 */
		$item = $this->entityLookup->getEntity( $comparativeClass );

		if ( $item === null ) {
			return false;
		}

		foreach ( $this->getSubclassStatements( $item ) as $statement ) {
			$mainSnak = $statement->getMainSnak();

			if ( !( $this->hasCorrectType( $mainSnak ) ) ) {
				continue;
			}

			$comparativeClass = $mainSnak->getDataValue()->getEntityId();

			if( in_array( $comparativeClass->getSerialization(), $classesToCheck ) ) {
				return true;
			}

			if ( $depth > self::MAX_DEPTH ) {
				return false;
			}

			if ( $this->isSubclassOf( $comparativeClass, $classesToCheck, $depth + 1 ) ) {
				return true;
			}

		}
		return false;
	}

	private function getSubclassStatements( StatementListProvider $statementListProvider ) {
		return $statementListProvider->getStatements()
			->getByPropertyId( new PropertyId( self::subclassId ) )->toArray();
	}

	/**
	 * Checks, if one of the itemId serializations in $classesToCheck
	 * is contained in the list of $statements
	 * via property $relationId or if it is a subclass of
	 * one of the items referenced in $statements via $relationId
	 *
	 * @param StatementList $statements
	 * @param string $relationId
	 * @param array $classesToCheck
	 *
	 * @return bool
	 */
	public function hasClassInRelation( StatementList $statements, $relationId, $classesToCheck ) {
		$compliance = null;
		foreach ( $statements->getByPropertyId( new PropertyId( $relationId ) ) as $statement ) {
			$mainSnak = $claim = $statement->getMainSnak();

			if ( !$this->hasCorrectType( $mainSnak ) ) {
				continue;
			}

			$comparativeClass = $mainSnak->getDataValue()->getEntityId();

			if( in_array( $comparativeClass->getSerialization(), $classesToCheck ) ) {
				return true;
			}

			$compliance = $this->isSubclassOf( $comparativeClass, $classesToCheck, 1 );
			if ( $compliance === true ) {
				return true;
			}
		}
		return false;
	}

	private function hasCorrectType( $mainSnak ) {
		return $mainSnak->getType() === 'value' && $mainSnak->getDataValue()->getType() === 'wikibase-entityid';
	}

}