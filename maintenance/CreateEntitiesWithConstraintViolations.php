<?php

declare( strict_types = 1 );

namespace WikibaseQuality\ConstraintReport\Maintenance;

use Maintenance;
use MediaWiki\User\User;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\NumericPropertyId;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\Statement\GuidGenerator;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Snak\SnakList;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Statement\StatementList;
use Wikibase\DataModel\Statement\StatementListProvider;
use Wikibase\DataModel\Term\Fingerprint;
use Wikibase\DataModel\Term\Term;
use Wikibase\DataModel\Term\TermList;
use Wikibase\Repo\WikibaseRepo;
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
	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'WikibaseQualityConstraints' );
	}

	public function execute(): void {
		if ( $this->getConfig()->get( 'WBQualityConstraintsTypeCheckMaxEntities' ) !== 0 ) {
			$this->output(
				'warning: type checking will partially happen in PHP;' . PHP_EOL .
				'set $wgWBQualityConstraintsTypeCheckMaxEntities = 0; to ensure only SPARQL is used',
			);
		}

		$this->createItemWithSubjectTypeConstraintViolation();
		$this->output( 'Now run the runJobs maintenance script so the constraint definitions are up-to-date.' );
	}

	private function createItemWithSubjectTypeConstraintViolation(): void {
		$expectedClassId = $this->createItem( 'expected class for subject type constraint' );
		$propertyId = $this->createProperty(
			'wikibase-item',
			'property with subject type constraint',
			new Statement( mainSnak: new PropertyValueSnak(
				$this->configPropertyId( 'WBQualityConstraintsPropertyConstraintId' ),
				new EntityIdValue( $this->configItemId( 'WBQualityConstraintsTypeConstraintId' ) ),
			), qualifiers: new SnakList( [
				new PropertyValueSnak(
					$this->configPropertyId( 'WBQualityConstraintsRelationId' ),
					new EntityIdValue( $this->configItemId( 'WBQualityConstraintsInstanceOfRelationId' ) ),
				),
				new PropertyValueSnak(
					$this->configPropertyId( 'WBQualityConstraintsClassId' ),
					new EntityIdValue( $expectedClassId ),
				),
			] ) ),
		);
		$actualClassId = $this->createItem( 'actual class for subject type constraint' );
		$itemId = $this->createItem(
			'item with subject type constraint violation',
			new StatementList(
				new Statement( new PropertyValueSnak(
					$this->configPropertyId( 'WBQualityConstraintsInstanceOfId' ),
					new EntityIdValue( $actualClassId ),
				) ),
				new Statement( new PropertyNoValueSnak( $propertyId ) ),
			),
		);
		$this->output( "{$itemId->getSerialization()} has a subject type constraint violation" . PHP_EOL );
	}

	private function configPropertyId( string $name ): NumericPropertyId {
		return new NumericPropertyId( $this->getConfig()->get( $name ) );
	}

	private function configItemId( string $name ): ItemId {
		return new ItemId( $this->getConfig()->get( $name ) );
	}

	private function createProperty( string $dataTypeId, string $englishLabel, Statement $statement ): PropertyId {
		$property = new Property(
			null,
			new Fingerprint( new TermList( [ new Term( 'en', $englishLabel ) ] ) ),
			$dataTypeId,
			new StatementList( $statement ),
		);
		$id = $this->createEntity( $property );
		Assert::postcondition( $id instanceof PropertyId, '$id instanceof PropertyId' );
		return $id;
	}

	private function createItem( string $englishLabel, ?StatementList $statements = null ): ItemId {
		$item = new Item(
			null,
			new Fingerprint( new TermList( [ new Term( 'en', $englishLabel ) ] ) ),
			null,
			$statements,
		);
		$id = $this->createEntity( $item );
		Assert::postcondition( $id instanceof ItemId, '$id instanceof ItemId' );
		return $id;
	}

	private function createEntity( EntityDocument $entity ): EntityId {
		$store = WikibaseRepo::getEntityStore( $this->getServiceContainer() );
		$store->assignFreshId( $entity );
		$id = $entity->getId();
		$guidGenerator = new GuidGenerator();
		if ( $entity instanceof StatementListProvider ) {
			foreach ( $entity->getStatements() as $statement ) {
				$statement->setGuid( $guidGenerator->newGuid( $id ) );
			}
		}
		$user = User::newSystemUser( User::MAINTENANCE_SCRIPT_USER );
		$store->saveEntity( $entity, 'CreateEntitiesWithConstraintViolations', $user, EDIT_NEW );
		return $id;
	}
}

// @codeCoverageIgnoreStart
$maintClass = CreateEntitiesWithConstraintViolations::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
