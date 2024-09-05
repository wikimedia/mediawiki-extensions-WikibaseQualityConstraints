<?php

namespace WikibaseQuality\ConstraintReport;

use MediaWiki\Installer\DatabaseUpdater;
use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

/**
 * Container for hook callbacks registered in extension.json.
 *
 * @license GPL-2.0-or-later
 */
final class WikibaseQualityConstraintsSchemaHooks implements LoadExtensionSchemaUpdatesHook {

	/**
	 * @param DatabaseUpdater $updater
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$dir = dirname( __DIR__ ) . '/sql/';

		$updater->addExtensionTable(
			'wbqc_constraints',
			$dir . "/{$updater->getDB()->getType()}/tables-generated.sql"
		);
	}

}
