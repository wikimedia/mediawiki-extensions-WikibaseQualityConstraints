<?php

// Temporary script to be used as long as MediaWiki extension classes
// cannot be loaded with PSR-4-compliant autoloading.

namespace WikibaseQuality\Build;

use AutoloadGenerator;
use Maintenance;

require_once getenv( 'MW_INSTALL_PATH' ) !== false
	? getenv( 'MW_INSTALL_PATH' ) . '/maintenance/Maintenance.php'
	: __DIR__ . '/../../../maintenance/Maintenance.php';

/**
 * Generates WikibaseQualityConstraints autoload info
 */
class GenerateAutoload extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->mDescription = 'Generates WikibaseQualityConstraints autoload data';
	}

	public function execute() {
		$base = dirname( __DIR__ );
		$generator = new AutoloadGenerator( $base );
		$dirs = [
			'api',
			'includes',
			'maintenance',
			'specials',
		];
		foreach ( $dirs as $dir ) {
			$generator->readDir( $base . '/' . $dir );
		}
		foreach ( glob( $base . '/*.php' ) as $file ) {
			$generator->readFile( $file );
		}
		$generator->readFile( $base . '/tests/phpunit/ConstraintParameters.php' );
		$generator->readFile( $base . '/tests/phpunit/DefaultConfig.php' );
		$generator->readFile( $base . '/tests/phpunit/ResultAssertions.php' );
		$generator->readFile( $base . '/tests/phpunit/SparqlHelperMock.php' );
		$generator->readFile( $base . '/tests/phpunit/TitleParserMock.php' );
		$generator->readFile( $base . '/tests/phpunit/Fake/FakeChecker.php' );
		$generator->readFile( $base . '/tests/phpunit/Fake/FakeSnakContext.php' );
		$generator->readFile( $base . '/tests/phpunit/Fake/InMemoryConstraintLookup.php' );

		$target = $generator->getTargetFileInfo();

		file_put_contents(
			$target['filename'],
			$generator->getAutoload( basename( __DIR__ ) . '/' . basename( __FILE__ ) )
		);

		echo "Done.\n\n";
	}

}

$maintClass = GenerateAutoload::class;
require_once RUN_MAINTENANCE_IF_MAIN;
