<?php

final class WikidataQualityConstraintsHooks {

	/**
	 * @param DatabaseUpdater $updater
	 *
	 * @return bool
	 */
	public static function onCreateSchema( DatabaseUpdater $updater ) {
		$updater->addExtensionTable( CONSTRAINT_TABLE, __DIR__ . '/sql/create_wdqa_constraints.sql' );
		return true;
	}

	public static function onUnitTestsList( &$files ) {
		$files = array_merge(
			$files,
			glob( __DIR__ . '/tests/phpunit/*Test.php' ) );
		return true;
	}

	public static function onNewRevisionFromEditComplete( $article, Revision $rev, $baseID, User $user ) {
		$accumulator = array (
			'special_page_id' => 42,
			'entity_id' => $article->mTitle->mTextform,
			'insertion_timestamp' => $article->mPreparedEdit->timestamp,
			'reference_timestamp' => null,
			'result_string' => 'This was written by onNewRevisionFromEditComplete'
		);
		wfWaitForSlaves();
		$loadBalancer = wfGetLB();
		$db = $loadBalancer->getConnection( DB_MASTER );
		$db->insert( EVALUATION_TABLE, $accumulator );
	}
}