<?php

final class WikibaseQualityConstraintsHooks {

	public static function onExtensionRegistration() {
		// Define database table names
		define( 'CONSTRAINT_TABLE', 'wbqc_constraints' );
	}

	/**
	 * @param DatabaseUpdater $updater
	 *
	 * @return bool
	 */
	public static function onCreateSchema( DatabaseUpdater $updater ) {
		$updater->addExtensionTable( CONSTRAINT_TABLE, __DIR__ . '/sql/create_wbqc_constraints.sql' );
		return true;
	}

	public static function onUnitTestsList( &$paths ) {
		$paths[] = __DIR__ . '/tests/phpunit';
		return true;
	}

}
