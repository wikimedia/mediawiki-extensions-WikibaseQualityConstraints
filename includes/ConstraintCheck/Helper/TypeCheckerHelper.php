<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Helper;

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
	const instanceId = 31;
	const subclassId = 279;

	/**
	 * @var EntityLookup $entityLookup
	 */
	private $entityLookup;

	public function __construct( EntityLookup $lookup ) {
		$this->entityLookup = $lookup;
	}

	public function isSubclassOf( $comparativeClass, $classesToCheck, $depth ) {
		$compliance = null;
		$item = $this->entityLookup->getEntity( $comparativeClass );
		if ( !$item ) {
			return false; // lookup failed, probably because item doesn't exist
		}

		foreach ( $item->getStatements() as $statement ) {
			$claim = $statement->getClaim();
			$propertyId = $claim->getPropertyId();
			$numericPropertyId = $propertyId->getNumericId();

			if ( $numericPropertyId === self::subclassId ) {
				$mainSnak = $claim->getMainSnak();

				if ( $mainSnak->getType() === 'value' && $mainSnak->getDataValue()->getType() === 'wikibase-entityid' ) {
					$comparativeClass = $mainSnak->getDataValue()->getEntityId();

					foreach ( $classesToCheck as $class ) {
						if ( $class === $comparativeClass->getSerialization() ) {
							return true;
						}
					}

				}

				if ( $depth > self::MAX_DEPTH ) {
					return false;
				}

				$compliance = $this->isSubclassOf( $comparativeClass, $classesToCheck, $depth + 1 );

			}
			if ( $compliance === true ) {
				return true;
			}
		}
		return false;
	}

	public function hasClassInRelation( $statements, $relationId, $classesToCheck ) {
		$compliance = null;
		foreach ( $statements as $statement ) {
			$claim = $statement->getClaim();
			$propertyId = $claim->getPropertyId();
			$numericPropertyId = $propertyId->getNumericId();

			if ( $numericPropertyId === $relationId ) {
				$mainSnak = $claim->getMainSnak();

				if ( $mainSnak->getType() === 'value' && $mainSnak->getDataValue()->getType() === 'wikibase-entityid' ) {
					$comparativeClass = $mainSnak->getDataValue()->getEntityId();

					foreach ( $classesToCheck as $class ) {
						if ( $class === $comparativeClass->getSerialization() ) {
							return true;
						}
					}
					$compliance = $this->isSubclassOf( $comparativeClass, $classesToCheck, 1 );
				}
			}
			if ( $compliance === true ) {
				return true;
			}
		}
		return false;
	}
}