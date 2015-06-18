<?php

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

call_user_func( function() {
	// Set credits
	$GLOBALS['wgExtensionCredits']['specialpage'][] = array(
		'path' => __FILE__,
		'name' => 'WikibaseQualityConstraints',
		'author' => 'BP2014N1',
		'url' => 'https://www.mediawiki.org/wiki/Extension:WikibaseQualityConstraints',
		'descriptionmsg' => 'wbqc-desc',
		'version' => '1.0.0'
	);

	// Initialize localization and aliases
	$GLOBALS['wgMessagesDirs']['WikibaseQualityConstraints'] = __DIR__ . '/i18n';
	$GLOBALS['wgExtensionMessagesFiles']['WikibaseQualityConstraintsAlias'] = __DIR__ . '/WikibaseQualityConstraints.alias.php';

	// Initalize hooks for creating database tables
	$GLOBALS['wgHooks']['LoadExtensionSchemaUpdates'][] = 'WikibaseQualityConstraintsHooks::onCreateSchema';

	// Register hooks for Unit Tests
	$GLOBALS['wgHooks']['UnitTestsList'][] = 'WikibaseQualityConstraintsHooks::onUnitTestsList';

	// Initialize special pages
	$GLOBALS['wgSpecialPages']['ConstraintReport'] = 'WikibaseQuality\ConstraintReport\Specials\SpecialConstraintReport::newFromGlobalState';

	// Define modules
    $remoteExtPathParts = explode(
        DIRECTORY_SEPARATOR . 'extensions' . DIRECTORY_SEPARATOR, __DIR__, 2
    );
	$GLOBALS['wgResourceModules']['SpecialConstraintReportPage'] = array (
        'styles' => '/modules/SpecialConstraintReportPage.css',
        'scripts' => '/modules/SpecialConstraintReportPage.js',
		'localBasePath' => __DIR__,
		'remoteExtPath' => $remoteExtPathParts[1]
	);

	// Define database table names
	define( 'CONSTRAINT_TABLE', 'wbqc_constraints' );

	// Jobs
	$GLOBALS['wgJobClasses']['evaluateConstraintReportJob'] = 'WikibaseQuality\ConstraintReport\EvaluateConstraintReportJob';
	$GLOBALS['wgDebugLogGroups']['wbq_evaluation'] = '/var/log/mediawiki/wbq_evaluation.log';
} );