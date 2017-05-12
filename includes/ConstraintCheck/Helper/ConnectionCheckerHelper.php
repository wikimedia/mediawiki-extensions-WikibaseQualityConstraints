<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Helper;

use InvalidArgumentException;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Statement\StatementList;

/**
 * Class for helper functions for the connection checkers.
 *
 * @package WikibaseQuality\ConstraintReport\ConstraintCheck\Helper
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class ConnectionCheckerHelper {

	/**
	 * Checks if there is a statement with a claim using the given property.
	 *
	 * @param StatementList $statementList
	 * @param string $propertyIdSerialization
	 *
	 * @return boolean
	 */
	public function hasProperty( StatementList $statementList, $propertyIdSerialization ) {
		$propertyIdSerialization = strtoupper( $propertyIdSerialization ); // FIXME strtoupper should not be necessary, remove once constraints are imported from statements
		/** @var Statement $statement */
		foreach ( $statementList as $statement ) {
			if ( $statement->getPropertyId()->getSerialization() === $propertyIdSerialization ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Checks if there is a statement with a claim using the given property and having one of the given entities as its value.
	 *
	 * @param StatementList $statementList
	 * @param string $propertyIdSerialization
	 * @param string|string[] $entityIdSerializationOrArray
	 *
	 * @return EntityId|null the entity ID from $entityIdSerializationOrArray that was found, or null if no entity was found
	 */
	public function hasClaim(
		StatementList $statementList,
		$propertyIdSerialization,
		$entityIdSerializationOrArray
	) {
		$entityIdSerializations = (array) $entityIdSerializationOrArray;
		try {
			$propertyId = new PropertyId( $propertyIdSerialization );
		} catch ( InvalidArgumentException $e ) {
			return null;
		}
		/** @var Statement $statement */
		foreach ( $statementList->getByPropertyId( $propertyId ) as $statement ) {
			$result = $this->arrayHasClaim( $statement, $entityIdSerializations );
			if ( $result !== null ) {
				return $result;
			}
		}
		return null;
	}

	/**
	 * @param Statement $statement
	 * @param string[] $entityIdSerializationArray
	 *
	 * @return EntityId|null the entity ID from $entityIdSerializationArray that was found, or null if no entity was found
	 */
	private function arrayHasClaim( Statement $statement, array $entityIdSerializationArray ) {
		$mainSnak = $statement->getMainSnak();

		// FIXME strtoupper should not be necessary, remove once constraints are imported from statements
		$entityIdSerializationArray = array_map( "strtoupper", $entityIdSerializationArray );

		if ( $mainSnak instanceof PropertyValueSnak ) {
			$dataValue = $mainSnak->getDataValue();

			if ( $dataValue instanceof EntityIdValue ) {
				$entityId = $dataValue->getEntityId();
				if ( in_array( $entityId->getSerialization(), $entityIdSerializationArray, true ) ) {
					return $entityId;
				}
			}
		}

		return null;
	}

}
