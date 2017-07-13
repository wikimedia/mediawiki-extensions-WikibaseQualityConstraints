<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Helper;

use InvalidArgumentException;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Snak\PropertySomeValueSnak;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Statement\StatementList;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ItemIdSnakValue;

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
	 * Checks if there is a statement with a claim using the given property and having one of the given values.
	 *
	 * @param StatementList $statementList
	 * @param string $propertyIdSerialization
	 * @param ItemIdSnakValue[]|string[]|string $values
	 *
	 * @return ItemIdSnakValue|null the value from $values that was found, or null if no statement was found
	 */
	public function findStatement(
		StatementList $statementList,
		$propertyIdSerialization,
		$values
	) {
		$values = (array) $values;
		try {
			$propertyId = new PropertyId( $propertyIdSerialization );
		} catch ( InvalidArgumentException $e ) {
			return null;
		}
		if ( is_string( $values[0] ) ) {
			$values = $this->parseItemIdSnakValues( $values );
		}
		/** @var Statement $statement */
		foreach ( $statementList->getByPropertyId( $propertyId ) as $statement ) {
			$result = $this->arrayHasClaim( $statement, $values );
			if ( $result !== null ) {
				return $result;
			}
		}
		return null;
	}

	/**
	 * @param Statement $statement
	 * @param ItemIdSnakValue[] $values
	 *
	 * @return ItemIdSnakValue|null the value from $values that was found, or null if no statement was found
	 */
	private function arrayHasClaim( Statement $statement, array $values ) {
		$mainSnak = $statement->getMainSnak();

		foreach ( $values as $value ) {
			if ( $value->matchesSnak( $mainSnak ) ) {
				return $value;
			}
		}

		return null;
	}

	/**
	 * @param string[] $values
	 * @return ItemIdSnakValue[]
	 */
	private function parseItemIdSnakValues( array $values ) {
		$ret = [];
		foreach ( $values as $value ) {
			switch ( $value ) {
				case 'somevalue':
					$ret[] = ItemIdSnakValue::someValue();
					break;
				case 'novalue':
					$ret[] = ItemIdSnakValue::noValue();
					break;
				default:
					try {
						$ret[] = ItemIdSnakValue::fromItemId( new ItemId( strtoupper( $value ) ) );
					} catch ( InvalidArgumentException $e ) {
						// ignore
					}
					break;
			}
		}
		return $ret;
	}

}
