<?php

if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'WikibaseQualityConstraints', __DIR__ . '/extension.json' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['WikibaseQualityConstraints'] = __DIR__ . '/i18n';
	$wgExtensionMessagesFiles['WikibaseQualityConstraintsAlias'] = __DIR__ . '/WikibaseQualityConstraints.alias.php';
	/* wfWarn(
		'Deprecated PHP entry point used for WikibaseQualityConstraints extension. ' .
		'Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	); */
	return;
} else {
	die( 'This version of the WikibaseQualityConstraints extension requires MediaWiki 1.25+' );
}
