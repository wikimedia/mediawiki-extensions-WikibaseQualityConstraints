<?php

final class WikidataQualityConstraintsHooks {

	/**
	 * @param DatabaseUpdater $updater
	 *
	 * @return bool
	 */
	public static function onCreateSchema( DatabaseUpdater $updater ) {
		$updater->addExtensionTable( CONSTRAINT_TABLE, __DIR__ . '/constraint-report/sql/create_wdqa_constraints.sql' );
        return true;
	}

	public static function onUnitTestsList( &$files ) {
		$files = array_merge(
			$files,
			glob( __DIR__ . '/tests/phpunit/*Test.php' ) );
		return true;
	}
}