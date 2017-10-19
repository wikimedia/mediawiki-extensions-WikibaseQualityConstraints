<?php

namespace WikibaseQuality\ConstraintReport\Maintenance;

use Maintenance;
use Title;
use MediaWiki\MediaWikiServices;
use WikibaseQuality\ConstraintReport\UpdateConstraintsTableJob;
use Wikibase\Repo\WikibaseRepo;

$basePath = getenv( "MW_INSTALL_PATH" ) !== false
	? getenv( "MW_INSTALL_PATH" ) : __DIR__ . "/../../..";

require_once $basePath . "/maintenance/Maintenance.php";

/**
 * Runs {@link UpdateConstraintsTableJob} once for every property.
 *
 * @license GNU GPL v2+
 */
class ImportConstraintStatements extends Maintenance {

	public function __construct() {
		parent::__construct();

		$this->addDescription( 'Imports property constraints from statements on properties' );
	}

	public function execute() {
		if ( !MediaWikiServices::getInstance()->getMainConfig()->get( 'WBQualityConstraintsEnableConstraintsImportFromStatements' ) ) {
			$this->error( 'Constraint statements are not enabled. Aborting.', 1 );
		}

		$propertyInfoLookup = WikibaseRepo::getDefaultInstance()->getStore()->getPropertyInfoLookup();
		foreach ( $propertyInfoLookup->getAllPropertyInfo() as $propertyIdSerialization => $info ) {
			$this->output( sprintf( 'Importing constraint statements for % 6s... ', $propertyIdSerialization ), $propertyIdSerialization );
			$startTime = microtime( true );
			$job = UpdateConstraintsTableJob::newFromGlobalState( Title::newMainPage(), [ 'propertyId' => $propertyIdSerialization ] );
			$job->run();
			$endTime = microtime( true );
			$millis = ( $endTime - $startTime ) * 1000;
			$this->output( sprintf( 'done in % 6.2f ms.', $millis ), $propertyIdSerialization );
		}
	}

}

// @codeCoverageIgnoreStart
$maintClass = ImportConstraintStatements::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
