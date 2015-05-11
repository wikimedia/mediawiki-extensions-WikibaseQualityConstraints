<?php

final class WikidataQualityConstraintsHooks {

	/**
	 * @param DatabaseUpdater $updater
	 *
	 * @return bool
	 */
	public static function onCreateSchema( DatabaseUpdater $updater ) {
		$updater->addExtensionTable( CONSTRAINT_TABLE, __DIR__ . '/sql/create_wbqc_constraints.sql' );
		return true;
	}

	public static function onUnitTestsList( &$files ) {
		$files = array_merge(
			$files,
			glob( __DIR__ . '/tests/phpunit/*Test.php' ) );
		return true;
	}
}