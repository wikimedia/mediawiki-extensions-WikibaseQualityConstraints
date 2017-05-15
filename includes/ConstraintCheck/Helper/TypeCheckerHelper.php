<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Helper;

use Config;
use InvalidArgumentException;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\EntityId\EntityIdFormatter;
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

	/**
	 * @var EntityLookup
	 */
	private $entityLookup;

	/**
	 * @var Config
	 */
	private $config;

	/**
	 * @var EntityIdFormatter
	 */
	private $entityIdFormatter;

	/**
	 * @param EntityLookup $lookup
	 * @param Config $config
	 * @param EntityIdFormatter $entityIdFormatter should return HTML
	 */
	public function __construct(
		EntityLookup $lookup,
		Config $config,
		EntityIdFormatter $entityIdFormatter
	) {
		$this->entityLookup = $lookup;
		$this->config = $config;
		$this->entityIdFormatter = $entityIdFormatter;
	}

	/**
	 * Checks if $comparativeClass is a subclass
	 * of one of the item ID serializations in $classesToCheck.
	 * If the class hierarchy is not exhausted before
	 * the configured limit (WBQualityConstraintsTypeCheckMaxEntities) is reached,
	 * the check aborts and returns false.
	 *
	 * @param EntityId $comparativeClass
	 * @param string[] $classesToCheck
	 * @param int &$entitiesChecked
	 *
	 * @return bool
	 */
	public function isSubclassOf( EntityId $comparativeClass, array $classesToCheck, &$entitiesChecked = 0 ) {
		if ( ++$entitiesChecked > $this->config->get( 'WBQualityConstraintsTypeCheckMaxEntities' ) ) {
			return false;
		}

		$item = $this->entityLookup->getEntity( $comparativeClass );
		if ( !( $item instanceof StatementListProvider ) ) {
			return false; // lookup failed, probably because item doesn't exist
		}

		$subclassId = $this->config->get( 'WBQualityConstraintsSubclassOfId' );
		/** @var Statement $statement */
		foreach ( $item->getStatements()->getByPropertyId( new PropertyId( $subclassId ) ) as $statement ) {
			$mainSnak = $statement->getMainSnak();

			if ( !( $this->hasCorrectType( $mainSnak ) ) ) {
				continue;
			}
			/** @var PropertyValueSnak $mainSnak */
			/** @var EntityIdValue $dataValue */

			$dataValue = $mainSnak->getDataValue();
			$comparativeClass = $dataValue->getEntityId();

			if ( in_array( $comparativeClass->getSerialization(), $classesToCheck ) ) {
				return true;
			}

			if ( $this->isSubclassOf( $comparativeClass, $classesToCheck, $entitiesChecked ) ) {
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

			if ( in_array( $comparativeClass->getSerialization(), $classesToCheck ) ) {
				return true;
			}

			if ( $this->isSubclassOf( $comparativeClass, $classesToCheck ) ) {
				return true;
			}
		}

		return false;
	}

	private function hasCorrectType( Snak $mainSnak ) {
		return $mainSnak instanceof PropertyValueSnak
			&& $mainSnak->getDataValue()->getType() === 'wikibase-entityid';
	}

	/**
	 * @param string $class item ID serialization (any other string is HTML-escaped and returned without formatting)
	 * @return string HTML
	 */
	private function formatClass( $classId ) {
		try {
			return $this->entityIdFormatter->formatEntityId( new ItemId( $classId ) );
		} catch ( InvalidArgumentException $e ) {
			return htmlspecialchars( $classId );
		}
	}

	/**
	 * @param PropertyId $propertyId ID of the property that introduced the constraint
	 * @param EntityId $entityId ID of the entity that does not have the required type
	 * @param string[] $classes item ID serializations of the classes that $entityId should have
	 * @param string $checker "type" or "valueType" (for message key)
	 * @param string $relation "instance" or "subclass" (for message key)
	 *
	 * @return string Localized HTML message
	 */
	public function getViolationMessage( PropertyId $propertyId, EntityId $entityId, array $classes, $checker, $relation ) {
		$message = wfMessage( 'wbqc-violation-message-' . $checker . '-' . $relation );

		$message->rawParams(
			$this->entityIdFormatter->formatEntityId( $propertyId ),
			$this->entityIdFormatter->formatEntityId( $entityId )
		);
		$message->numParams( count( $classes ) );
		$message->rawParams(
			'<ul>'
			. implode( array_map(
				function ( $class ) {
					return '<li>' . $this->formatClass( $class ) . '</li>';
				},
				$classes
			) )
			. '</ul>'
		);
		$message->rawParams( array_map( [ $this, "formatClass" ], $classes ) );

		return $message->escaped();
	}

}
