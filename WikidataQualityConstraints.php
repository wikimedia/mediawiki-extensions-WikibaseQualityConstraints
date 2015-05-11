<?php

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

call_user_func( function() {
	// Set credits
	$GLOBALS['wgExtensionCredits']['specialpage'][] = array(
		'path' => __FILE__,
		'name' => 'WikidataQualityConstraints',
		'author' => 'BP2014N1',
		'url' => 'https://www.mediawiki.org/wiki/Extension:WikidataQualityConstraints',
		'descriptionmsg' => 'wbqc-constraints-desc',
		'version' => '0.0.0'
	);

	// Initialize localization and aliases
	$GLOBALS['wgMessagesDirs']['WikidataQualityConstraints'] = __DIR__ . '/i18n';
	$GLOBALS['wgExtensionMessagesFiles']['WikidataQualityConstraintsAlias'] = __DIR__ . '/WikidataQualityConstraints.alias.php';

	// Initalize hooks for creating database tables
	$GLOBALS['wgHooks']['LoadExtensionSchemaUpdates'][] = 'WikidataQualityConstraintsHooks::onCreateSchema';

	// Register hooks for Unit Tests
	$GLOBALS['wgHooks']['UnitTestsList'][] = 'WikidataQualityConstraintsHooks::onUnitTestsList';

	// Initialize special pages
	$GLOBALS['wgSpecialPages']['ConstraintReport'] = 'WikidataQuality\ConstraintReport\Specials\SpecialConstraintReport';

	// Define modules
	$GLOBALS['wgResourceModules']['SpecialConstraintReportPage'] = array (
		'styles' => '/modules/SpecialConstraintReportPage.css',
		'localBasePath' => __DIR__,
		'remoteExtPath' => 'WikidataQualityConstraints'
	);

	// Define database table names
	define( 'CONSTRAINT_TABLE', 'wbqc_constraints' );

	// Jobs
	$GLOBALS['wgJobClasses']['evaluateConstraintReportJob'] = 'EvaluateConstraintReportJob';
	$GLOBALS['wgDebugLogGroups']['wbq_evaluation'] = '/var/log/mediawiki/wbq_evaluation.log';
} );