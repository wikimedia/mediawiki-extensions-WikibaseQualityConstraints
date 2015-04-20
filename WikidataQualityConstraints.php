<?php
// Alert the user that this is not a valid access point to MediaWiki if they try to access the special pages file directly.
if ( !defined( 'MEDIAWIKI' ) ) {
    echo <<<EOT
	To install my extension, put the following line in LocalSettings.php:
	require_once( "\$IP/extensions/WikidataQualityConstraints/WikidataQualityConstraints.php" );
EOT;
    exit( 1 );
}

// Enable autoload
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Set credits
$wgExtensionCredits[ 'specialpage' ][ ] = array(
    'path' => __FILE__,
    'name' => 'WikidataQualityConstraints',
    'author' => 'BP2014N1',
    'url' => 'https://www.mediawiki.org/wiki/Extension:WikidataQualityConstraints',
    'descriptionmsg' => 'wikidataquality-constraints-desc',
    'version' => '0.0.0'
);

// Initialize localization and aliases
$wgMessagesDirs[ 'WikidataQualityConstraints' ] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles[ 'WikidataQualityConstraintsAlias' ] = __DIR__ . '/WikidataQualityConstraints.alias.php';

// Initalize hooks for creating database tables
global $wgHooks;
$wgHooks[ 'LoadExtensionSchemaUpdates' ][ ] = 'WikidataQualityConstraintsHooks::onCreateSchema';

// Register hooks for Unit Tests
$wgHooks[ 'UnitTestsList' ][ ] = 'WikidataQualityConstraintsHooks::onUnitTestsList';

// Initialize special pages
$wgSpecialPages[ 'ConstraintReport' ] = 'WikidataQuality\ConstraintReport\Specials\SpecialConstraintReport';

// Define database table names
define( 'CONSTRAINT_TABLE', 'wdqa_constraints' );