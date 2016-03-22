<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Helper;

use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Snak\Snak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Statement\StatementList;
use Wikibase\DataModel\Statement\StatementListProvider;

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
	 * @param EntityId $comparativeClass
	 * @param string[] $classesToCheck
	 * @param int $depth
	 *
	 * @return bool
	 */
	public function isSubclassOf( EntityId $comparativeClass, array $classesToCheck, $depth ) {
		$item = $this->entityLookup->getEntity( $comparativeClass );
		if ( !( $item instanceof StatementListProvider ) ) {
			return false; // lookup failed, probably because item doesn't exist
		}

		/** @var Statement $statement */
		foreach ( $item->getStatements()->getByPropertyId( new PropertyId( self::subclassId ) ) as $statement ) {
			$mainSnak = $statement->getMainSnak();

			if ( !( $this->hasCorrectType( $mainSnak ) ) ) {
				continue;
			}
			/** @var PropertyValueSnak $mainSnak */
			/** @var EntityIdValue $dataValue */

			$dataValue = $mainSnak->getDataValue();
			$comparativeClass = $dataValue->getEntityId();

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

	/**
	 * Checks, if one of the itemId serializations in $classesToCheck
	 * is contained in the list of $statements
	 * via property $relationId or if it is a subclass of
	 * one of the items referenced in $statements via $relationId
	 *
	 * @param StatementList $statements
	 * @param string $relationId
	 * @param string[] $classesToCheck
	 *
	 * @return bool
	 */
	public function hasClassInRelation( StatementList $statements, $relationId, array $classesToCheck ) {
		/** @var Statement $statement */
		foreach ( $statements->getByPropertyId( new PropertyId( $relationId ) ) as $statement ) {
			$mainSnak = $statement->getMainSnak();

			if ( !$this->hasCorrectType( $mainSnak ) ) {
				continue;
			}
			/** @var PropertyValueSnak $mainSnak */
			/** @var EntityIdValue $dataValue */

			$dataValue = $mainSnak->getDataValue();
			$comparativeClass = $dataValue->getEntityId();

			if( in_array( $comparativeClass->getSerialization(), $classesToCheck ) ) {
				return true;
			}

			if ( $this->isSubclassOf( $comparativeClass, $classesToCheck, 1 ) ) {
				return true;
			}
		}

		return false;
	}

	private function hasCorrectType( Snak $mainSnak ) {
		return $mainSnak instanceof PropertyValueSnak
			&& $mainSnak->getDataValue()->getType() === 'wikibase-entityid';
	}

}
