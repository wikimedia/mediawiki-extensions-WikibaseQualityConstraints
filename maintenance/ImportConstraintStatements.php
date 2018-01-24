<?php

namespace WikibaseQuality\ConstraintReport\Maintenance;

use Maintenance;
use Title;
use Wikibase\Lib\Store\PropertyInfoLookup;
use WikibaseQuality\ConstraintReport\UpdateConstraintsTableJob;
use Wikibase\Repo\WikibaseRepo;

// @codeCoverageIgnoreStart
$basePath = getenv( "MW_INSTALL_PATH" ) !== false
	? getenv( "MW_INSTALL_PATH" ) : __DIR__ . "/../../..";

require_once $basePath . "/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

/**
 * Runs {@link UpdateConstraintsTableJob} once for every property.
 *
 * @license GNU GPL v2+
 */
class ImportConstraintStatements extends Maintenance {

	/**
	 * @var PropertyInfoLookup
	 */
	private $propertyInfoLookup;

	/**
	 * @var callable
	 * @param string $propertyIdSerialization
	 * @return UpdateConstraintsTableJob
	 */
	private $newUpdateConstraintsTableJob;

	public function __construct() {
		parent::__construct();
		$repo = WikibaseRepo::getDefaultInstance();
		$this->propertyInfoLookup = $repo->getStore()->getPropertyInfoLookup();
		$this->newUpdateConstraintsTableJob = function ( $propertyIdSerialization ) {
			return UpdateConstraintsTableJob::newFromGlobalState(
				Title::newMainPage(),
				[ 'propertyId' => $propertyIdSerialization ]
			);
		};

		$this->addDescription( 'Imports property constraints from statements on properties' );
	}

	public function execute() {
		if ( !$this->getConfig()->get( 'WBQualityConstraintsEnableConstraintsImportFromStatements' ) ) {
			$this->error( 'Constraint statements are not enabled. Aborting.' );
			return;
		}

		foreach ( $this->propertyInfoLookup->getAllPropertyInfo() as $propertyIdSerialization => $info ) {
			$this->output( sprintf( 'Importing constraint statements for % 6s... ', $propertyIdSerialization ), $propertyIdSerialization );
			$startTime = microtime( true );
			$job = call_user_func( $this->newUpdateConstraintsTableJob, $propertyIdSerialization );
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
