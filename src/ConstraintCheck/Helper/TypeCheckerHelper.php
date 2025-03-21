<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Helper;

use MediaWiki\Config\Config;
use OverflowException;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\NumericPropertyId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Snak\Snak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Statement\StatementList;
use Wikibase\DataModel\Statement\StatementListProvider;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachedBool;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\Metadata;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessage;
use WikibaseQuality\ConstraintReport\Role;
use Wikimedia\Stats\StatsFactory;

/**
 * Class for helper functions for range checkers.
 *
 * @author BP2014N1
 * @license GPL-2.0-or-later
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
	 * @var SparqlHelper
	 */
	private $sparqlHelper;

	/**
	 * @var StatsFactory
	 */
	private $statsFactory;

	/**
	 * @param EntityLookup $lookup
	 * @param Config $config
	 * @param SparqlHelper $sparqlHelper
	 * @param StatsFactory $statsFactory
	 */
	public function __construct(
		EntityLookup $lookup,
		Config $config,
		SparqlHelper $sparqlHelper,
		StatsFactory $statsFactory
	) {
		$this->entityLookup = $lookup;
		$this->config = $config;
		$this->sparqlHelper = $sparqlHelper;
		$this->statsFactory = $statsFactory;
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
		$statements = $item->getStatements()
			->getByPropertyId( new NumericPropertyId( $subclassId ) )
			->getBestStatements();
		/** @var Statement $statement */
		foreach ( $statements as $statement ) {
			$mainSnak = $statement->getMainSnak();

			if ( !$this->hasCorrectType( $mainSnak ) ) {
				continue;
			}
			/** @var PropertyValueSnak $mainSnak */
			/** @var EntityIdValue $dataValue */

			$dataValue = $mainSnak->getDataValue();
			'@phan-var EntityIdValue $dataValue';
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
		$timing = $this->statsFactory->getTiming( 'isSubclassOf_duration_seconds' )
			->setLabel( 'result', 'success' )
			->setLabel( 'TypeCheckerImplementation', 'php' )
			->copyToStatsdAt( 'wikibase.quality.constraints.type.php.success.timing' );
		$timing->start();

		try {
			$entitiesChecked = 0;
			$isSubclass = $this->isSubclassOf( $comparativeClass, $classesToCheck, $entitiesChecked );
			$timing->stop();

			// not really a timing, but works like one (we want percentiles etc.)
			// TODO: probably a good candidate for T348796
			$this->statsFactory->getTiming( 'isSubclassOf_entities_total' )
				->setLabel( 'TypeCheckerImplementation', 'php' )
				->setLabel( 'result', 'success' )
				->copyToStatsdAt( 'wikibase.quality.constraints.type.php.success.entities' )
				->observe( $entitiesChecked );

			return new CachedBool( $isSubclass, Metadata::blank() );
		} catch ( OverflowException $e ) {
			$timing->setLabel( 'result', 'overflow' )
				->copyToStatsdAt( 'wikibase.quality.constraints.type.php.overflow.timing' )
				->stop();

			if ( !( $this->sparqlHelper instanceof DummySparqlHelper ) ) {
				$this->statsFactory->getCounter( 'sparql_typeFallback_total' )
					->copyToStatsdAt( 'wikibase.quality.constraints.sparql.typeFallback' )
					->increment();

				$timing->setLabel( 'TypeCheckerImplementation', 'sparql' )
					->setLabel( 'result', 'success' )
					->copyToStatsdAt( 'wikibase.quality.constraints.type.sparql.success.timing' )
					->start();

				$hasType = $this->sparqlHelper->hasType(
					$comparativeClass,
					$classesToCheck
				);

				$timing->stop();

				return $hasType;
			} else {
				return new CachedBool( false, Metadata::blank() );
			}
		}
	}

	/**
	 * Checks, if one of the itemId serializations in $classesToCheck
	 * is contained in the list of $statements
	 * via properties $relationIds or if it is a subclass of
	 * one of the items referenced in $statements via $relationIds
	 *
	 * @param StatementList $statements
	 * @param string[] $relationIds
	 * @param string[] $classesToCheck
	 *
	 * @return CachedBool
	 * @throws SparqlHelperException if SPARQL is used and the query times out or some other error occurs
	 */
	public function hasClassInRelation( StatementList $statements, array $relationIds, array $classesToCheck ) {
		$metadatas = [];

		foreach ( $this->getBestStatementsByPropertyIds( $statements, $relationIds ) as $statement ) {
			$mainSnak = $statement->getMainSnak();

			if ( !$this->hasCorrectType( $mainSnak ) ) {
				continue;
			}
			/** @var PropertyValueSnak $mainSnak */
			/** @var EntityIdValue $dataValue */

			$dataValue = $mainSnak->getDataValue();
			'@phan-var EntityIdValue $dataValue';
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

	/**
	 * @param Snak $mainSnak
	 * @return bool
	 * @phan-assert PropertyValueSnak $mainSnak
	 */
	private function hasCorrectType( Snak $mainSnak ) {
		return $mainSnak instanceof PropertyValueSnak
			&& $mainSnak->getDataValue()->getType() === 'wikibase-entityid';
	}

	/**
	 * @param StatementList $statements
	 * @param string[] $propertyIdSerializations
	 *
	 * @return Statement[]
	 */
	private function getBestStatementsByPropertyIds(
		StatementList $statements,
		array $propertyIdSerializations
	) {
		$statementArrays = [];

		foreach ( $propertyIdSerializations as $propertyIdSerialization ) {
			$propertyId = new NumericPropertyId( $propertyIdSerialization );
			$statementArrays[] = $statements
				->getByPropertyId( $propertyId )
				->getBestStatements()
				->toArray();
		}

		return array_merge( ...$statementArrays );
	}

	/**
	 * @param PropertyId $propertyId ID of the property that introduced the constraint
	 * @param EntityId $entityId ID of the entity that does not have the required type
	 * @param string[] $classes item ID serializations of the classes that $entityId should have
	 * @param string $checker "type" or "valueType" (for message key)
	 * @param string $relation "instance", "subclass", or "instanceOrSubclass" (for message key)
	 *
	 * @return ViolationMessage
	 */
	public function getViolationMessage(
		PropertyId $propertyId,
		EntityId $entityId,
		array $classes,
		$checker,
		$relation
	) {
		$classes = array_map(
			static function ( $itemIdSerialization ) {
				return new ItemId( $itemIdSerialization );
			},
			$classes
		);

		// Possible messages:
		// wbqc-violation-message-type-instance
		// wbqc-violation-message-type-subclass
		// wbqc-violation-message-type-instanceOrSubclass
		// wbqc-violation-message-valueType-instance
		// wbqc-violation-message-valueType-subclass
		// wbqc-violation-message-valueType-instanceOrSubclass
		return ( new ViolationMessage( 'wbqc-violation-message-' . $checker . '-' . $relation ) )
			->withEntityId( $propertyId, Role::CONSTRAINT_PROPERTY )
			->withEntityId( $entityId, Role::SUBJECT )
			->withEntityIdList( $classes, Role::OBJECT );
	}

}
