<?php

namespace WikibaseQuality\ConstraintReport\Maintenance;

use Config;
use Deserializers\Deserializer;
use Maintenance;
use Serializers\Serializer;
use User;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\SiteLinkList;
use Wikibase\DataModel\Statement\StatementListProvider;
use Wikibase\Lib\Store\EntityStore;
use Wikibase\Lib\Store\StorageException;
use Wikibase\Repo\WikibaseRepo;

// @codeCoverageIgnoreStart
$basePath = getenv( "MW_INSTALL_PATH" ) !== false
	? getenv( "MW_INSTALL_PATH" ) : __DIR__ . "/../../..";

require_once $basePath . "/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

/**
 * Imports entities needed for constraint checks from Wikidata into the local repository.
 *
 * @license GPL-2.0-or-later
 */
class ImportConstraintEntities extends Maintenance {

	/**
	 * @var Serializer
	 */
	private $entitySerializer;

	/**
	 * @var Deserializer
	 */
	private $entityDeserializer;

	/**
	 * @var EntityStore
	 */
	private $entityStore;

	/**
	 * @var User
	 */
	private $user;

	public function __construct() {
		parent::__construct();

		$this->addDescription(
			'Import entities needed for constraint checks ' .
			'from Wikidata into the local repository.'
		);
		$this->addOption(
			'config-format',
			'The format in which the resulting configuration will be omitted: ' .
			'"globals" for directly settings global variables, suitable for inclusion in LocalSettings.php (default), ' .
			'or "wgConf" for printing parts of arrays suitable for inclusion in $wgConf->settings.'
		);
		$this->addOption(
			'dry-run',
			'Don’t actually import entities, just print which ones would be imported.'
		);
	}

	/**
	 * (This cannot happen in the constructor because the autoloader is not yet initialized there.)
	 */
	private function setupServices() {
		$repo = WikibaseRepo::getDefaultInstance();
		$this->entitySerializer = $repo->getAllTypesEntitySerializer();
		$this->entityDeserializer = $repo->getInternalFormatEntityDeserializer();
		$this->entityStore = $repo->getEntityStore();
		$this->user = User::newSystemUser( 'WikibaseQualityConstraints importer' );
	}

	public function execute() {
		$this->setupServices();

		$configUpdates = [];

		$extensionJsonFile = __DIR__ . '/../extension.json';
		$extensionJsonText = file_get_contents( $extensionJsonFile );
		$extensionJson = json_decode( $extensionJsonText, /* assoc = */ true );
		$wikidataEntityIds = $this->getEntitiesToImport( $extensionJson['config'], $this->getConfig() );

		foreach ( $wikidataEntityIds as $key => $wikidataEntityId ) {
			$localEntityId = $this->importEntityFromWikidata( $wikidataEntityId );
			$configUpdates[$key] = [
				'wikidata' => $wikidataEntityId,
				'local' => $localEntityId,
			];
		}

		$this->outputConfigUpdates( $configUpdates );
	}

	/**
	 * @param array $extensionJsonConfig
	 * @param Config $wikiConfig
	 * @return string[]
	 */
	private function getEntitiesToImport( array $extensionJsonConfig, Config $wikiConfig ) {
		$wikidataEntityIds = [];

		foreach ( $extensionJsonConfig as $key => $value ) {
			if ( !preg_match( '/Id$/', $key ) ) {
				continue;
			}

			$wikidataEntityId = $value['value'];
			$localEntityId = $wikiConfig->get( $key );

			if ( $localEntityId === $wikidataEntityId ) {
				$wikidataEntityIds[$key] = $wikidataEntityId;
			}
		}

		return $wikidataEntityIds;
	}

	/**
	 * @param string $wikidataEntityId
	 * @return string local entity ID
	 */
	private function importEntityFromWikidata( $wikidataEntityId ) {
		$wikidataEntityUrl = "https://www.wikidata.org/wiki/Special:EntityData/$wikidataEntityId.json";
		$wikidataEntitiesJson = file_get_contents( $wikidataEntityUrl );
		return $this->importEntityFromJson( $wikidataEntityId, $wikidataEntitiesJson );
	}

	/**
	 * @param string $wikidataEntityId
	 * @param string $wikidataEntitiesJson
	 * @return string local entity ID
	 */
	private function importEntityFromJson( $wikidataEntityId, $wikidataEntitiesJson ) {
		$wikidataEntityArray = json_decode( $wikidataEntitiesJson, true )['entities'][$wikidataEntityId];
		$wikidataEntity = $this->entityDeserializer->deserialize( $wikidataEntityArray );

		$wikidataEntity->setId( null );

		if ( $wikidataEntity instanceof StatementListProvider ) {
			$wikidataEntity->getStatements()->clear();
		}

		if ( $wikidataEntity instanceof Item ) {
			$wikidataEntity->setSiteLinkList( new SiteLinkList() );
		}

		if ( $this->getOption( 'dry-run', false ) ) {
			$wikidataEntityJson = json_encode( $this->entitySerializer->serialize( $wikidataEntity ) );
			$this->output( $wikidataEntityJson . "\n" );
			return "-$wikidataEntityId";
		}

		try {
			$localEntity = $this->entityStore->saveEntity(
				$wikidataEntity,
				"imported from [[wikidata:$wikidataEntityId]]",
				$this->user,
				EDIT_NEW | EDIT_FORCE_BOT
			)->getEntity();

			return $localEntity->getId()->getSerialization();
		} catch ( StorageException $storageException ) {
			$message = $storageException->getMessage();
			// example message:
			// * Item [[Item:Q475|Q475]] already has label "as references" associated with language code en, using the same description text.
			// note that the label and language code may vary (conflicts in any language),
			// and that the item link may or may not be in the main namespace
			$pattern = '/[[|]([^]|]*)]] already has label .* using the same description text/';
			if ( preg_match( $pattern, $message, $matches ) ) {
				return $matches[1];
			} else {
				throw $storageException;
			}
		}
	}

	private function outputConfigUpdates( array $configUpdates ) {
		$configFormat = $this->getOption( 'config-format', 'globals' );
		switch ( $configFormat ) {
			case 'globals':
				$this->outputConfigUpdatesGlobals( $configUpdates );
				break;
			case 'wgConf':
				$this->outputConfigUpdatesWgConf( $configUpdates );
				break;
			default:
				$this->error( "Invalid config format \"$configFormat\", using \"globals\"" );
				$this->outputConfigUpdatesGlobals( $configUpdates );
				break;
		}
	}

	private function outputConfigUpdatesGlobals( array $configUpdates ) {
		foreach ( $configUpdates as $key => $value ) {
			$localValueCode = var_export( $value['local'], true );
			$this->output( "\$wg$key = $localValueCode;\n" );
		}
	}

	private function outputConfigUpdatesWgConf( array $configUpdates ) {
		foreach ( $configUpdates as $key => $value ) {
			$keyCode = var_export( $key, true );
			$wikidataValueCode = var_export( $value['wikidata'], true );
			$localValueCode = var_export( $value['local'], true );
			$wikiIdCode = var_export( wfWikiID(), true );
			$block = <<< EOF
$keyCode => [
	'default' => $wikidataValueCode,
	$wikiIdCode => $localValueCode,
],


EOF;
			$this->output( $block );
		}
	}

}

// @codeCoverageIgnoreStart
$maintClass = ImportConstraintEntities::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
