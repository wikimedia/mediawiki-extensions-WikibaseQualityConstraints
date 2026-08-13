<?php

declare( strict_types = 1 );

namespace WikibaseQuality\ConstraintReport\Maintenance;

use DataValues\DataValue;
use DataValues\QuantityValue;
use DataValues\StringValue;
use DataValues\UnboundedQuantityValue;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\NumericPropertyId;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\DataModel\Services\Statement\GuidGenerator;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Snak\Snak;
use Wikibase\DataModel\Snak\SnakList;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Statement\StatementList;
use Wikibase\DataModel\Statement\StatementListProvider;
use Wikibase\DataModel\Term\Fingerprint;
use Wikibase\DataModel\Term\Term;
use Wikibase\DataModel\Term\TermList;
use Wikibase\Lexeme\Domain\Model\Lexeme;
use Wikibase\Lib\Store\EntityStore;
use Wikibase\Lib\Store\LookupConstants;
use Wikibase\Lib\Store\MatchingTermsLookup;
use Wikibase\Lib\Store\StorageException;
use Wikibase\Lib\TermIndexEntry;
use Wikibase\Repo\Store\Sql\SqlEntityIdPagerFactory;
use Wikibase\Repo\Store\Store;
use Wikibase\Repo\WikibaseRepo;
use WikibaseQuality\ConstraintReport\Job\UpdateConstraintsTableJob;
use Wikimedia\Assert\Assert;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

/**
 * Creates entities with various constraint violations, for local testing.
 *
 * @license GPL-2.0-or-later
 */
class CreateEntitiesWithConstraintViolations extends Maintenance {
	/** @var string[] */
	private array $constraintPropertyIds = [];
	private EntityLookup $entityLookup;
	private EntityStore $entityStore;
	private MatchingTermsLookup $matchingTermsLookup;
	private User $user;

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'WikibaseQualityConstraints' );
		$this->setBatchSize( 10 );
	}

	public function execute(): void {
		if ( !$this->getConfig()->get( 'WBQualityConstraintsEnableConstraintsImportFromStatements' ) ) {
			$this->error( 'Constraint statements are not enabled. Aborting.' );
			return;
		}

		if ( $this->getConfig()->get( 'WBQualityConstraintsTypeCheckMaxEntities' ) !== 0 ) {
			$this->output(
				'warning: type checking will partially happen in PHP;' . PHP_EOL .
				'set $wgWBQualityConstraintsTypeCheckMaxEntities = 0; to ensure only SPARQL is used' . PHP_EOL,
			);
		}
		if ( !$this->getConfig()->get( 'WBQualityConstraintsCheckQualifiers' ) ) {
			$this->output(
				'warning: the used-for-values-only violation needs qualifier checks to be enabled' . PHP_EOL,
			);
		}

		if ( $this->getConfig()->get( 'WBQualityConstraintsSparqlEndpoint' ) === '' ) {
			$this->output(
				'warning: distinct-values and SPARQL-backed format checks need a configured SPARQL endpoint' .
				PHP_EOL,
			);
		}

		$this->setupServices();
		$this->waitForReplicationWithOutput();
		$this->createItemWithSubjectTypeConstraintViolation();
		$this->createItemWithValueTypeConstraintViolation();
		$this->createItemsWithConnectionConstraintViolations();
		$this->createItemsWithValueConstraintViolations();
		$this->createItemWithUsageConstraintViolations();
		$this->createLexemeWithLanguageConstraintViolation();
		$this->importConstraintStatements();
	}

	private function setupServices(): void {
		$services = $this->getServiceContainer();
		$this->entityLookup = WikibaseRepo::getStore( $services )->getEntityLookup(
			Store::LOOKUP_CACHING_DISABLED,
			LookupConstants::LATEST_FROM_MASTER
		);
		$this->matchingTermsLookup = WikibaseRepo::getMatchingTermsLookupFactory( $services )->getLookupForSource(
			WikibaseRepo::getLocalEntitySource( $services )
		);
		$this->entityStore = WikibaseRepo::getEntityStore( $services );
		$user = User::newSystemUser( User::MAINTENANCE_SCRIPT_USER );
		Assert::postcondition( $user instanceof User, '$user instanceof User' );
		$this->user = $user;
	}

	private function importConstraintStatements(): void {
		$this->waitForReplicationWithOutput();

		foreach ( array_chunk( $this->constraintPropertyIds, $this->getBatchSize() ) as $propertyIdsChunk ) {
			foreach ( $propertyIdsChunk as $propertyIdSerialization ) {
				$this->output( sprintf(
					'Importing constraint statements for % 6s... ',
					$propertyIdSerialization ),
					$propertyIdSerialization
				);
				$startTime = microtime( true );
				$job = UpdateConstraintsTableJob::newFromGlobalState(
					Title::newMainPage(),
					[ 'propertyId' => $propertyIdSerialization ]
				);
				$job->run();
				$endTime = microtime( true );
				$millis = ( $endTime - $startTime ) * 1000;
				$this->output( sprintf( 'done in % 6.2f ms.', $millis ), $propertyIdSerialization );
			}

			$this->waitForReplicationWithOutput();
		}
	}

	private function waitForReplicationWithOutput(): void {
		$this->output( 'Waiting for replication... ', 'waitForReplication' );
		$startTime = microtime( true );
		$this->waitForReplication();
		$endTime = microtime( true );
		$millis = ( $endTime - $startTime ) * 1000;
		$this->output( sprintf( 'done in % 6.2f ms.', $millis ), 'waitForReplication' );
	}

	private function createItemWithSubjectTypeConstraintViolation(): void {
		$expectedClassId = $this->createItem( 'expected class for subject type constraint' );
		$propertyId = $this->createProperty(
			'wikibase-item',
			'property with subject type constraint',
			$this->constraint(
				'WBQualityConstraintsTypeConstraintId',
				$this->entityIdSnak(
					'WBQualityConstraintsRelationId',
					$this->configItemId( 'WBQualityConstraintsInstanceOfRelationId' ),
				),
				$this->entityIdSnak( 'WBQualityConstraintsClassId', $expectedClassId ),
			),
		);
		$actualClassId = $this->createItem( 'actual class for subject type constraint' );
		$itemId = $this->createItem(
			'item with subject type constraint violation',
			new StatementList(
				$this->entityIdStatement(
					$this->configPropertyId( 'WBQualityConstraintsInstanceOfId' ),
					$actualClassId,
				),
				new Statement( new PropertyNoValueSnak( $propertyId ) ),
			),
		);
		$this->output( "{$itemId->getSerialization()} has a subject type constraint violation" . PHP_EOL );
	}

	private function createItemWithValueTypeConstraintViolation(): void {
		$expectedClassId = $this->createItem( 'expected class for value type constraint' );
		$propertyId = $this->createProperty(
			'wikibase-item',
			'property with value type constraint',
			$this->constraint(
				'WBQualityConstraintsValueTypeConstraintId',
				$this->entityIdSnak(
					'WBQualityConstraintsRelationId',
					$this->configItemId( 'WBQualityConstraintsInstanceOfRelationId' ),
				),
				$this->entityIdSnak( 'WBQualityConstraintsClassId', $expectedClassId ),
			),
		);
		$actualClassId = $this->createItem( 'actual class for value type constraint' );
		$valueId = $this->createItem(
			'value with wrong type',
			new StatementList( $this->entityIdStatement(
				$this->configPropertyId( 'WBQualityConstraintsInstanceOfId' ),
				$actualClassId,
			) ),
		);
		$itemId = $this->createItem(
			'item with value type constraint violation',
			new StatementList( $this->entityIdStatement( $propertyId, $valueId ) ),
		);
		$this->output( "{$itemId->getSerialization()} has a value type constraint violation" . PHP_EOL );
	}

	private function createItemsWithConnectionConstraintViolations(): void {
		$requiredPropertyId = $this->createProperty( 'string', 'required property' );
		$conflictingPropertyId = $this->createProperty( 'string', 'conflicting property' );
		$inversePropertyId = $this->createProperty( 'wikibase-item', 'inverse property' );

		$conflictsWithPropertyId = $this->createProperty(
			'string',
			'property with conflicts-with constraint',
			$this->constraint(
				'WBQualityConstraintsConflictsWithConstraintId',
				$this->propertyIdSnak( $conflictingPropertyId ),
			),
		);
		$itemRequiresClaimPropertyId = $this->createProperty(
			'string',
			'property with item-requires-claim constraint',
			$this->constraint(
				'WBQualityConstraintsItemRequiresClaimConstraintId',
				$this->propertyIdSnak( $requiredPropertyId ),
			),
		);
		$valueRequiresClaimPropertyId = $this->createProperty(
			'wikibase-item',
			'property with value-requires-claim constraint',
			$this->constraint(
				'WBQualityConstraintsValueRequiresClaimConstraintId',
				$this->propertyIdSnak( $requiredPropertyId ),
			),
		);
		$symmetricPropertyId = $this->createProperty(
			'wikibase-item',
			'property with symmetric constraint',
			$this->constraint( 'WBQualityConstraintsSymmetricConstraintId' ),
		);
		$propertyWithInverseConstraintId = $this->createProperty(
			'wikibase-item',
			'property with inverse constraint',
			$this->constraint(
				'WBQualityConstraintsInverseConstraintId',
				$this->propertyIdSnak( $inversePropertyId ),
			),
		);
		$contemporaryPropertyId = $this->createProperty(
			'string',
			'property with contemporary constraint',
			$this->constraint( 'WBQualityConstraintsContemporaryConstraintId' ),
		);

		$targetId = $this->createItem( 'target lacking reciprocal and required statements' );
		$itemId = $this->createItem(
			'item with connection constraint violations',
			new StatementList(
				$this->stringStatement( $conflictsWithPropertyId, 'conflicting value' ),
				$this->stringStatement( $conflictingPropertyId, 'other value' ),
				$this->stringStatement( $itemRequiresClaimPropertyId, 'value' ),
				$this->entityIdStatement( $valueRequiresClaimPropertyId, $targetId ),
				$this->entityIdStatement( $symmetricPropertyId, $targetId ),
				$this->entityIdStatement( $propertyWithInverseConstraintId, $targetId ),
				$this->stringStatement( $contemporaryPropertyId, 'not an entity ID' ),
			),
		);
		$this->output(
			"{$itemId->getSerialization()} has conflicts-with, item-requires-claim, " .
			'value-requires-claim, symmetric, inverse, and contemporary constraint violations' . PHP_EOL,
		);
	}

	private function createItemsWithValueConstraintViolations(): void {
		$allowedItemId = $this->createItem( 'allowed value' );
		$otherItemId = $this->createItem( 'other value' );
		$allowedUnitId = $this->createItem( 'allowed unit' );

		$singleValuePropertyId = $this->createProperty(
			'string',
			'property with single-value constraint',
			$this->constraint( 'WBQualityConstraintsSingleValueConstraintId' ),
		);
		$multiValuePropertyId = $this->createProperty(
			'string',
			'property with multi-value constraint',
			$this->constraint( 'WBQualityConstraintsMultiValueConstraintId' ),
		);
		$singleBestValuePropertyId = $this->createProperty(
			'string',
			'property with single-best-value constraint',
			$this->constraint( 'WBQualityConstraintsSingleBestValueConstraintId' ),
		);
		$distinctValuesPropertyId = $this->createProperty(
			'string',
			'property with distinct-values constraint',
			$this->constraint( 'WBQualityConstraintsDistinctValuesConstraintId' ),
		);
		$oneOfPropertyId = $this->createProperty(
			'wikibase-item',
			'property with one-of constraint',
			$this->constraint(
				'WBQualityConstraintsOneOfConstraintId',
				$this->itemIdSnak( $allowedItemId ),
			),
		);
		$noneOfPropertyId = $this->createProperty(
			'wikibase-item',
			'property with none-of constraint',
			$this->constraint(
				'WBQualityConstraintsNoneOfConstraintId',
				$this->itemIdSnak( $allowedItemId ),
			),
		);
		$rangePropertyId = $this->createProperty(
			'quantity',
			'property with range constraint',
			$this->constraint(
				'WBQualityConstraintsRangeConstraintId',
				$this->quantitySnak( 'WBQualityConstraintsMinimumQuantityId', 0 ),
				$this->quantitySnak( 'WBQualityConstraintsMaximumQuantityId', 5 ),
			),
		);
		$differencePropertyId = $this->createProperty( 'quantity', 'property used for difference' );
		$differenceWithinRangePropertyId = $this->createProperty(
			'quantity',
			'property with difference-within-range constraint',
			$this->constraint(
				'WBQualityConstraintsDifferenceWithinRangeConstraintId',
				$this->propertyIdSnak( $differencePropertyId ),
				$this->quantitySnak( 'WBQualityConstraintsMinimumQuantityId', 0 ),
				$this->quantitySnak( 'WBQualityConstraintsMaximumQuantityId', 5 ),
			),
		);
		$formatPropertyId = $this->createProperty(
			'string',
			'property with format constraint',
			$this->constraint(
				'WBQualityConstraintsFormatConstraintId',
				$this->stringSnak( 'WBQualityConstraintsFormatAsARegularExpressionId', '^[A-Z]+$' ),
			),
		);
		$commonsLinkPropertyId = $this->createProperty(
			'commonsMedia',
			'property with Commons-link constraint',
			$this->constraint(
				'WBQualityConstraintsCommonsLinkConstraintId',
				$this->stringSnak( 'WBQualityConstraintsNamespaceId', 'File' ),
			),
		);
		$noBoundsPropertyId = $this->createProperty(
			'quantity',
			'property with no-bounds constraint',
			$this->constraint( 'WBQualityConstraintsNoBoundsConstraintId' ),
		);
		$allowedUnitsPropertyId = $this->createProperty(
			'quantity',
			'property with allowed-units constraint',
			$this->constraint(
				'WBQualityConstraintsAllowedUnitsConstraintId',
				$this->itemIdSnak( $allowedUnitId ),
			),
		);
		$integerPropertyId = $this->createProperty(
			'quantity',
			'property with integer constraint',
			$this->constraint( 'WBQualityConstraintsIntegerConstraintId' ),
		);
		$labelInLanguagePropertyId = $this->createProperty(
			'string',
			'property with label-in-language constraint',
			$this->constraint(
				'WBQualityConstraintsLabelInLanguageConstraintId',
				$this->stringSnak( 'WBQualityConstraintsLanguagePropertyId', 'de' ),
			),
		);

		$duplicateValue = 'duplicate value for distinct-values constraint';
		$itemId = $this->createItem(
			'item with value constraint violations',
			new StatementList(
				$this->stringStatement( $singleValuePropertyId, 'first' ),
				$this->stringStatement( $singleValuePropertyId, 'second' ),
				$this->stringStatement( $multiValuePropertyId, 'only value' ),
				$this->stringStatement( $singleBestValuePropertyId, 'first best value' ),
				$this->stringStatement( $singleBestValuePropertyId, 'second best value' ),
				$this->stringStatement( $distinctValuesPropertyId, $duplicateValue ),
				$this->entityIdStatement( $oneOfPropertyId, $otherItemId ),
				$this->entityIdStatement( $noneOfPropertyId, $allowedItemId ),
				$this->quantityStatement( $rangePropertyId, UnboundedQuantityValue::newFromNumber( 10 ) ),
				$this->quantityStatement(
					$differenceWithinRangePropertyId,
					UnboundedQuantityValue::newFromNumber( 10 ),
				),
				$this->quantityStatement( $differencePropertyId, UnboundedQuantityValue::newFromNumber( 0 ) ),
				$this->stringStatement( $formatPropertyId, 'lowercase' ),
				$this->stringStatement( $commonsLinkPropertyId, 'invalid_file_name' ),
				$this->quantityStatement( $noBoundsPropertyId, QuantityValue::newFromNumber( 10, '1', 11, 9 ) ),
				$this->quantityStatement( $allowedUnitsPropertyId, UnboundedQuantityValue::newFromNumber( 10 ) ),
				$this->quantityStatement( $integerPropertyId, UnboundedQuantityValue::newFromNumber( '1.5' ) ),
				$this->stringStatement( $labelInLanguagePropertyId, 'value' ),
			),
		);
		$duplicateItemId = $this->createItem(
			'other item with duplicate value',
			new StatementList( $this->stringStatement( $distinctValuesPropertyId, $duplicateValue ) ),
		);
		$this->output(
			"{$itemId->getSerialization()} has single-value, multi-value, single-best-value, " .
			'one-of, none-of, range, difference-within-range, format, Commons-link, no-bounds, ' .
			'allowed-units, integer, and label-in-language constraint violations' . PHP_EOL,
		);
		$this->output(
			"{$itemId->getSerialization()} and {$duplicateItemId->getSerialization()} " .
			'have a distinct-values constraint violation once both are available through SPARQL' . PHP_EOL,
		);
	}

	private function createItemWithUsageConstraintViolations(): void {
		$requiredQualifierPropertyId = $this->createProperty( 'string', 'required qualifier property' );
		$allowedQualifierPropertyId = $this->createProperty( 'string', 'allowed qualifier property' );
		$unexpectedQualifierPropertyId = $this->createProperty( 'string', 'unexpected qualifier property' );

		$usedAsQualifierPropertyId = $this->createProperty(
			'string',
			'property with used-as-qualifier constraint',
			$this->constraint( 'WBQualityConstraintsUsedAsQualifierConstraintId' ),
		);
		$mandatoryQualifierPropertyId = $this->createProperty(
			'string',
			'property with mandatory-qualifier constraint',
			$this->constraint(
				'WBQualityConstraintsMandatoryQualifierConstraintId',
				$this->propertyIdSnak( $requiredQualifierPropertyId ),
			),
		);
		$allowedQualifiersPropertyId = $this->createProperty(
			'string',
			'property with allowed-qualifiers constraint',
			$this->constraint(
				'WBQualityConstraintsAllowedQualifiersConstraintId',
				$this->propertyIdSnak( $allowedQualifierPropertyId ),
			),
		);
		$valueOnlyPropertyId = $this->createProperty(
			'string',
			'property with used-for-values-only constraint',
			$this->constraint( 'WBQualityConstraintsUsedForValuesOnlyConstraintId' ),
		);
		$referenceOnlyPropertyId = $this->createProperty(
			'string',
			'property with used-as-reference constraint',
			$this->constraint( 'WBQualityConstraintsUsedAsReferenceConstraintId' ),
		);
		$citationNeededPropertyId = $this->createProperty(
			'string',
			'property with citation-needed constraint',
			$this->constraint( 'WBQualityConstraintsCitationNeededConstraintId' ),
		);
		$propertyScopePropertyId = $this->createProperty(
			'string',
			'property with property-scope constraint',
			$this->constraint(
				'WBQualityConstraintsPropertyScopeConstraintId',
				$this->entityIdSnak(
					'WBQualityConstraintsPropertyScopeId',
					$this->configItemId( 'WBQualityConstraintsAsQualifiersId' ),
				),
			),
		);
		$allowedEntityTypesPropertyId = $this->createProperty(
			'string',
			'property with allowed-entity-types constraint',
			$this->constraint(
				'WBQualityConstraintsAllowedEntityTypesConstraintId',
				$this->entityIdSnak(
					'WBQualityConstraintsConstraintEntityTypesId',
					$this->configItemId( 'WBQualityConstraintsWikibasePropertyId' ),
				),
			),
		);

		$statementWithUnexpectedQualifier = $this->stringStatement(
			$allowedQualifiersPropertyId,
			'value',
			new PropertyValueSnak( $unexpectedQualifierPropertyId, new StringValue( 'unexpected' ) ),
		);
		$statementWithValueOnlyQualifier = $this->stringStatement(
			$mandatoryQualifierPropertyId,
			'value',
			new PropertyValueSnak( $valueOnlyPropertyId, new StringValue( 'used as qualifier' ) ),
		);

		$itemId = $this->createItem(
			'item with property usage constraint violations',
			new StatementList(
				$this->stringStatement( $usedAsQualifierPropertyId, 'used as main value' ),
				$statementWithValueOnlyQualifier,
				$statementWithUnexpectedQualifier,
				$this->stringStatement( $referenceOnlyPropertyId, 'used as main value' ),
				$this->stringStatement( $citationNeededPropertyId, 'statement without a reference' ),
				$this->stringStatement( $propertyScopePropertyId, 'used as main value' ),
				$this->stringStatement( $allowedEntityTypesPropertyId, 'used on an item' ),
			),
		);
		$this->output(
			"{$itemId->getSerialization()} has used-as-qualifier, mandatory-qualifier, " .
			'allowed-qualifiers, used-for-values-only, used-as-reference, citation-needed, ' .
			'property-scope, and allowed-entity-types constraint violations' . PHP_EOL,
		);
	}

	private function createLexemeWithLanguageConstraintViolation(): void {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'WikibaseLexeme' ) ) {
			$this->output(
				'warning: skipping the lexeme-language constraint violation because WikibaseLexeme is not loaded' .
				PHP_EOL,
			);
			return;
		}

		$allowedLanguageId = $this->createItem( 'allowed lexeme language' );
		$actualLanguageId = $this->createItem( 'actual lexeme language' );
		$lexicalCategoryId = $this->createItem( 'lexical category for violating lexeme' );
		$propertyId = $this->createProperty(
			'string',
			'property with lexeme-language constraint',
			$this->constraint(
				'WBQualityConstraintsLexemeLanguageConstraintId',
				$this->itemIdSnak( $allowedLanguageId ),
			),
		);
		/** @phan-suppress-next-line PhanParamTooMany */
		$lexeme = new Lexeme(
			null,
			new TermList( [ new Term( 'en', 'violating lexeme' ) ] ),
			$lexicalCategoryId,
			$actualLanguageId,
			new StatementList( $this->stringStatement( $propertyId, 'value' ) ),
		);
		'@phan-var EntityDocument $lexeme';
		$lexemeId = $this->createEntity( $lexeme, 'violating lexeme' );
		$this->output( "{$lexemeId->getSerialization()} has a lexeme-language constraint violation" . PHP_EOL );
	}

	private function constraint( string $constraintIdConfigName, Snak ...$qualifiers ): Statement {
		return new Statement(
			$this->entityIdSnak(
				'WBQualityConstraintsPropertyConstraintId',
				$this->configItemId( $constraintIdConfigName ),
			),
			new SnakList( $qualifiers ),
		);
	}

	private function entityIdSnak( string $propertyIdConfigName, EntityId $value ): PropertyValueSnak {
		return new PropertyValueSnak(
			$this->configPropertyId( $propertyIdConfigName ),
			new EntityIdValue( $value ),
		);
	}

	private function propertyIdSnak( PropertyId $propertyId ): PropertyValueSnak {
		return $this->entityIdSnak( 'WBQualityConstraintsPropertyId', $propertyId );
	}

	private function itemIdSnak( ItemId $itemId ): PropertyValueSnak {
		return $this->entityIdSnak( 'WBQualityConstraintsQualifierOfPropertyConstraintId', $itemId );
	}

	private function stringSnak( string $propertyIdConfigName, string $value ): PropertyValueSnak {
		return new PropertyValueSnak(
			$this->configPropertyId( $propertyIdConfigName ),
			new StringValue( $value ),
		);
	}

	private function quantitySnak( string $propertyIdConfigName, int $value ): PropertyValueSnak {
		return new PropertyValueSnak(
			$this->configPropertyId( $propertyIdConfigName ),
			UnboundedQuantityValue::newFromNumber( $value ),
		);
	}

	private function stringStatement( PropertyId $propertyId, string $value, Snak ...$qualifiers ): Statement {
		return new Statement(
			new PropertyValueSnak( $propertyId, new StringValue( $value ) ),
			new SnakList( $qualifiers ),
		);
	}

	private function entityIdStatement( PropertyId $propertyId, EntityId $value ): Statement {
		return new Statement( new PropertyValueSnak( $propertyId, new EntityIdValue( $value ) ) );
	}

	private function quantityStatement( PropertyId $propertyId, DataValue $value ): Statement {
		return new Statement( new PropertyValueSnak( $propertyId, $value ) );
	}

	private function configPropertyId( string $name ): NumericPropertyId {
		return new NumericPropertyId( $this->getConfig()->get( $name ) );
	}

	private function configItemId( string $name ): ItemId {
		return new ItemId( $this->getConfig()->get( $name ) );
	}

	private function createProperty(
		string $dataTypeId,
		string $englishLabel,
		Statement ...$statements
	): PropertyId {
		$property = new Property(
			null,
			new Fingerprint( new TermList( [ new Term( 'en', $englishLabel ) ] ) ),
			$dataTypeId,
			new StatementList( ...$statements ),
		);
		$id = $this->createEntity( $property, $englishLabel );
		Assert::postcondition( $id instanceof PropertyId, '$id instanceof PropertyId' );
		if ( $statements ) {
			$this->constraintPropertyIds[] = $id->getSerialization();
		}
		return $id;
	}

	private function createItem( string $englishLabel, ?StatementList $statements = null ): ItemId {
		$item = new Item(
			null,
			new Fingerprint( new TermList( [ new Term( 'en', $englishLabel ) ] ) ),
			null,
			$statements,
		);
		$id = $this->createEntity( $item, $englishLabel );
		Assert::postcondition( $id instanceof ItemId, '$id instanceof ItemId' );
		return $id;
	}

	private function createEntity( EntityDocument $entity, string $englishTerm ): EntityId {
		$existingEntity = $this->findExistingEntity( $entity, $englishTerm );
		$status = 'reused';
		if ( $existingEntity === null ) {
			try {
				$id = $this->saveNewEntity( $entity );
				$status = 'created';
			} catch ( StorageException $storageException ) {
				$this->waitForReplication();
				$existingEntity = $this->findExistingEntity( $entity, $englishTerm );
				if ( $existingEntity === null ) {
					throw $storageException;
				}
				$id = $existingEntity->getId();
			}
		} else {
			$id = $existingEntity->getId();
		}

		Assert::postcondition( $id instanceof EntityId, '$id instanceof EntityId' );
		$this->output( "{$id->getSerialization()}: $englishTerm ($status)" . PHP_EOL );

		return $id;
	}

	private function findExistingEntity( EntityDocument $expectedEntity, string $englishTerm ): ?EntityDocument {
		$existingEntities = $expectedEntity instanceof Lexeme
			? $this->findLexemesByLemma( $englishTerm )
			: $this->findEntitiesByTerm( $expectedEntity->getType(), $englishTerm );
		if ( !$existingEntities ) {
			return null;
		}

		$compatibleEntities = array_filter(
			$existingEntities,
			fn ( EntityDocument $entity ): bool =>
				$this->entitiesEqualIgnoringStatementGuids( $entity, $expectedEntity )
		);
		if ( count( $compatibleEntities ) > 1 ) {
			ksort( $compatibleEntities, SORT_NATURAL );
			$this->output( sprintf(
				'warning: found several matching %s entities with English term "%s"; ' .
				'using %s (candidates: %s)%s',
				$expectedEntity->getType(),
				$englishTerm,
				array_key_first( $compatibleEntities ),
				implode( ', ', array_keys( $compatibleEntities ) ),
				PHP_EOL
			) );
		}
		if ( !$compatibleEntities ) {
			$this->fatalError( sprintf(
				'Existing %s entities with English term "%s" do not have the expected content; ' .
				'refusing to overwrite them: %s',
				$expectedEntity->getType(),
				$englishTerm,
				implode( ', ', array_keys( $existingEntities ) )
			) );
		}

		$compatibleEntity = reset( $compatibleEntities );
		Assert::postcondition(
			$compatibleEntity instanceof EntityDocument,
			'$compatibleEntity instanceof EntityDocument'
		);
		return $compatibleEntity;
	}

	/** @return EntityDocument[] */
	private function findEntitiesByTerm( string $entityType, string $englishTerm ): array {
		$matchingTerms = $this->matchingTermsLookup->getMatchingTerms(
			$englishTerm,
			$entityType,
			'en',
			TermIndexEntry::TYPE_LABEL,
			[ 'caseSensitive' => true ]
		);
		$entities = [];
		foreach ( $matchingTerms as $matchingTerm ) {
			$entityId = $matchingTerm->getEntityId();
			$entity = $this->entityLookup->getEntity( $entityId );
			if ( $entity !== null ) {
				$entities[$entityId->getSerialization()] = $entity;
			}
		}

		return $entities;
	}

	/** @return Lexeme[] */
	private function findLexemesByLemma( string $englishLemma ): array {
		$services = $this->getServiceContainer();
		$pagerFactory = new SqlEntityIdPagerFactory(
			WikibaseRepo::getEntityNamespaceLookup( $services ),
			WikibaseRepo::getEntityIdLookup( $services ),
			WikibaseRepo::getRepoDomainDbFactory( $services )->newRepoDb()
		);
		$pager = $pagerFactory->newSqlEntityIdPager( [ Lexeme::ENTITY_TYPE ] );
		$lexemes = [];

		do {
			$batch = $pager->fetchIds( $this->getBatchSize() );
			foreach ( $batch as $entityId ) {
				$entity = $this->entityLookup->getEntity( $entityId );
				if ( !$entity instanceof Lexeme ) {
					continue;
				}
				'@phan-var Lexeme $entity';
				$lemmas = $entity->getLemmas();
				if ( $lemmas->hasTermForLanguage( 'en' ) &&
					$lemmas->getByLanguage( 'en' )->getText() === $englishLemma
				) {
					$lexemes[$entityId->getSerialization()] = $entity;
				}
			}
		} while ( $batch );

		return $lexemes;
	}

	private function entitiesEqualIgnoringStatementGuids(
		EntityDocument $existingEntity,
		EntityDocument $expectedEntity
	): bool {
		$existingEntity = $existingEntity->copy();
		$expectedEntity = $expectedEntity->copy();
		$this->clearStatementGuids( $existingEntity );
		$this->clearStatementGuids( $expectedEntity );

		return $existingEntity->equals( $expectedEntity );
	}

	private function clearStatementGuids( EntityDocument $entity ): void {
		if ( $entity instanceof StatementListProvider ) {
			foreach ( $entity->getStatements() as $statement ) {
				$statement->setGuid( null );
			}
		}
	}

	private function saveNewEntity( EntityDocument $entity ): EntityId {
		$this->entityStore->assignFreshId( $entity );
		$id = $entity->getId();
		$guidGenerator = new GuidGenerator();
		if ( $entity instanceof StatementListProvider ) {
			foreach ( $entity->getStatements() as $statement ) {
				$statement->setGuid( $guidGenerator->newGuid( $id ) );
			}
		}
		$this->entityStore->saveEntity(
			$entity,
			'CreateEntitiesWithConstraintViolations',
			$this->user,
			EDIT_NEW
		);
		return $id;
	}
}

// @codeCoverageIgnoreStart
$maintClass = CreateEntitiesWithConstraintViolations::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
