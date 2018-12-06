<?php

namespace WikibaseQuality\ConstraintReport\Tests\Maintenance;

use HashConfig;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use Wikibase\DataModel\Entity\Item;
use Wikibase\Lib\Store\EntityStore;
use Wikibase\Repo\WikibaseRepo;
use WikibaseQuality\ConstraintReport\Maintenance\ImportConstraintEntities;

/**
 * @covers \WikibaseQuality\ConstraintReport\Maintenance\ImportConstraintEntities
 *
 * @group WikibaseQualityConstraints
 *
 * @author Lucas Werkmeister
 * @license GPL-2.0-or-later
 */
class ImportConstraintEntitiesTest extends MaintenanceBaseTestCase {

	/**
	 * @var ImportConstraintEntities
	 */
	protected $maintenance;

	public function getMaintenanceClass() {
		return ImportConstraintEntities::class;
	}

	public function testGetEntitiesToImport() {
		$extensionJsonConfig = [
			'WBQualityConstraintsFooId' => [ 'value' => 'Q1' ],
			'WBQualityConstraintsBarId' => [ 'value' => 'Q2' ],
			'WBQualityConstraintsOther' => [ 'value' => 'not an entity ID' ],
		];
		$wikiConfig = new HashConfig( [
			'WBQualityConstraintsFooId' => 'Q123',
			'WBQualityConstraintsBarId' => 'Q2',
			'WBQualityConstraintsOther' => 'custom not an entity ID',
			'OtherExtensionSetting' => 'unrelated',
		] );

		$wikidataEntityIds = $this->maintenance->getEntitiesToImport( $extensionJsonConfig, $wikiConfig );

		$this->assertSame(
			[ 'WBQualityConstraintsBarId' => 'Q2' ],
			$wikidataEntityIds
		);
	}

	public function testImportEntityFromJson_dryRun() {
		$this->maintenance->loadParamsAndArgs( null, [ 'dry-run' => 1 ], null );
		$repo = WikibaseRepo::getDefaultInstance();
		$this->maintenance->entitySerializer = $repo->getAllTypesEntitySerializer();
		$this->maintenance->entityDeserializer = $repo->getInternalFormatEntityDeserializer();
		$entityStore = $this->getMock( EntityStore::class );
		$entityStore->expects( $this->never() )->method( 'saveEntity' );
		$this->maintenance->entityStore = $entityStore;

		$json = file_get_contents( __DIR__ . '/Q21503250.json' );
		$localEntityId = $this->maintenance->importEntityFromJson( 'Q21503250', $json );
		$this->assertSame( '-Q21503250', $localEntityId );

		$localEntityArray = json_decode( $this->getActualOutput(), true );
		$this->assertSame( 'type constraint', $localEntityArray['labels']['en']['value'] );
		$this->assertEmpty( $localEntityArray['sitelinks'] );
		$this->assertEmpty( $localEntityArray['claims'] );
	}

	public function testImportEntityFromJson() {
		$repo = WikibaseRepo::getDefaultInstance();
		$this->maintenance->setupServices();

		$json = file_get_contents( __DIR__ . '/Q21503250.json' );
		$localEntityId = $this->maintenance->importEntityFromJson( 'Q21503250', $json );

		$repo = WikibaseRepo::getDefaultInstance();
		/** @var Item $localEntity */
		$localEntity = $repo->getEntityLookup()->getEntity( $repo->getEntityIdParser()->parse( $localEntityId ) );
		$this->assertInstanceOf( Item::class, $localEntity );
		$this->assertSame( 'type constraint', $localEntity->getLabels()->getByLanguage( 'en' )->getText() );
		$this->assertEmpty( $localEntity->getSiteLinkList()->toArray() );
		$this->assertEmpty( $localEntity->getStatements()->toArray() );
	}

	public function testOutputConfigUpdatesGlobals() {
		$configUpdates = [
			'WBQualityConstraintsFooId' => [
				'wikidata' => 'Q1',
				'local' => 'Q123',
			],
			'WBQualityConstraintsBarId' => [
				'wikidata' => 'Q2',
				'local' => 'Q"\'\\456',
			],
		];
		$expected = <<< 'EOF'
$wgWBQualityConstraintsFooId = 'Q123';
$wgWBQualityConstraintsBarId = 'Q"\'\\456';
EOF;

		$this->maintenance->outputConfigUpdatesGlobals( $configUpdates );

		$this->expectOutputString( $expected . "\n" );
	}

	public function testOutputConfigUpdatesWgConf() {
		$configUpdates = [
			'WBQualityConstraintsFooId' => [
				'wikidata' => 'Q1',
				'local' => 'Q123',
			],
			'WBQualityConstraintsBarId' => [
				'wikidata' => 'Q2',
				'local' => 'Q"\'\\456',
			],
		];
		$wikiId = wfWikiID();
		$expected = <<< EOF
'WBQualityConstraintsFooId' => [
	'default' => 'Q1',
	'$wikiId' => 'Q123',
],

'WBQualityConstraintsBarId' => [
	'default' => 'Q2',
	'$wikiId' => 'Q"\\'\\\\456',
],

EOF;

		$this->maintenance->outputConfigUpdatesWgConf( $configUpdates );

		$this->expectOutputString( $expected . "\n" );
	}

}
