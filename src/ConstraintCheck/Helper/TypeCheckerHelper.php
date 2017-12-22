<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Helper;

use Config;
use MediaWiki\MediaWikiServices;
use OverflowException;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Snak\Snak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Statement\StatementList;
use Wikibase\DataModel\Statement\StatementListProvider;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachedBool;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\Metadata;
use WikibaseQuality\ConstraintReport\ConstraintParameterRenderer;
use WikibaseQuality\ConstraintReport\Role;

/**
 * Class for helper functions for range checkers.
 *
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
	 * @var ConstraintParameterRenderer
	 */
	private $constraintParameterRenderer;

	/**
	 * @var SparqlHelper
	 */
	private $sparqlHelper;

	/**
	 * @param EntityLookup $lookup
	 * @param Config $config
	 * @param ConstraintParameterRenderer $constraintParameterRenderer
	 * @param SparqlHelper|null $sparqlHelper
	 */
	public function __construct(
		EntityLookup $lookup,
		Config $config,
		ConstraintParameterRenderer $constraintParameterRenderer,
		SparqlHelper $sparqlHelper = null
	) {
		$this->entityLookup = $lookup;
		$this->config = $config;
		$this->constraintParameterRenderer = $constraintParameterRenderer;
		$this->sparqlHelper = $sparqlHelper;
	}

	/**
	 * Checks if $comparativeClass is a subclass
	 * of one of the item ID serializations in $classesToCheck.
	 * If the class hierarchy is not exhausted before
	 * the configured limit (WBQualityConstraintsTypeCheckMaxEntities) is reached,
	 * an OverflowException is thrown.
	 *
	 * @param EntityId $comparativeClass
	 * @param string[] $classesToCheck
	 * @param int &$entitiesChecked
	 *
	 * @return bool
	 * @throws OverflowException if $entitiesChecked exceeds the configured limit
	 */
	private function isSubclassOf( EntityId $comparativeClass, array $classesToCheck, &$entitiesChecked = 0 ) {
		$maxEntities = $this->config->get( 'WBQualityConstraintsTypeCheckMaxEntities' );
		if ( ++$entitiesChecked > $maxEntities ) {
			throw new OverflowException( 'Too many entities to check' );
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
	 * Checks if $comparativeClass is a subclass
	 * of one of the item ID serializations in $classesToCheck.
	 * If isSubclassOf() aborts due to hitting the configured limit,
	 * the injected {@link SparqlHelper} is consulted if present,
	 * otherwise the check returns false.
	 *
	 * @param EntityId $comparativeClass
	 * @param string[] $classesToCheck
	 *
	 * @return CachedBool
	 * @throws SparqlHelperException if SPARQL is used and the query times out or some other error occurs
	 */
	public function isSubclassOfWithSparqlFallback( EntityId $comparativeClass, array $classesToCheck ) {
		try {
			return new CachedBool(
				$this->isSubclassOf( $comparativeClass, $classesToCheck ),
				Metadata::blank()
			);
		} catch ( OverflowException $e ) {
			if ( $this->sparqlHelper !== null ) {
				MediaWikiServices::getInstance()->getStatsdDataFactory()
					->increment( 'wikibase.quality.constraints.sparql.typeFallback' );
				return $this->sparqlHelper->hasType(
					$comparativeClass->getSerialization(),
					$classesToCheck,
					/* withInstance = */ false
				);
			} else {
				return new CachedBool( false, Metadata::blank() );
			}
		}
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
	 * @return CachedBool
	 * @throws SparqlHelperException if SPARQL is used and the query times out or some other error occurs
	 */
	public function hasClassInRelation( StatementList $statements, $relationId, array $classesToCheck ) {
		$metadatas = [];

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
				// discard $metadatas, we know this is fresh
				return new CachedBool( true, Metadata::blank() );
			}

			$result = $this->isSubclassOfWithSparqlFallback( $comparativeClass, $classesToCheck );
			$metadatas[] = $result->getMetadata();
			if ( $result->getBool() ) {
				return new CachedBool(
					true,
					Metadata::merge( $metadatas )
				);
			}
		}

		return new CachedBool(
			false,
			Metadata::merge( $metadatas )
		);
	}

	private function hasCorrectType( Snak $mainSnak ) {
		return $mainSnak instanceof PropertyValueSnak
			&& $mainSnak->getDataValue()->getType() === 'wikibase-entityid';
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
		// Possible messages:
		// wbqc-violation-message-type-instance
		// wbqc-violation-message-type-subclass
		// wbqc-violation-message-valueType-instance
		// wbqc-violation-message-valueType-subclass
		$message = wfMessage( 'wbqc-violation-message-' . $checker . '-' . $relation );

		$message->rawParams(
			$this->constraintParameterRenderer->formatEntityId( $propertyId, Role::CONSTRAINT_PROPERTY ),
			$this->constraintParameterRenderer->formatEntityId( $entityId, Role::SUBJECT )
		);
		$message->numParams( count( $classes ) );
		$message->rawParams( $this->constraintParameterRenderer->formatItemIdList( $classes, Role::OBJECT ) );

		return $message->escaped();
	}

}