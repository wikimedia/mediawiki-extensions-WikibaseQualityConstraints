<?php

use MediaWiki\MediaWikiServices;
use Wikibase\Change;
use Wikibase\EntityChange;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\Diff\EntityDiff;

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

	public static function onWikibaseChange( Change $change ) {
		if ( MediaWikiServices::getInstance()->getMainConfig()->get( 'WBQualityConstraintsEnableConstraintsImportFromStatements' ) &&
			self::isPropertyStatementsChange( $change )
		) {
			$title = Title::newMainPage();
			$params = [ 'propertyId' => $change->getEntityId()->getSerialization() ];
			JobQueueGroup::singleton()->push(
				new JobSpecification( 'constraintsTableUpdate', $params, [], $title )
			);
		}
	}

	private static function isPropertyStatementsChange( Change $change ) {
		if ( !( $change instanceof EntityChange ) ||
			 $change->getAction() !== EntityChange::UPDATE ||
			 !( $change->getEntityId() instanceof PropertyId )
		) {
			return false;
		}

		$diff = $change->getDiff();
		if ( !( $diff instanceof EntityDiff ) ) {
			return false;
		}

		// TODO inspect the diff once T113468 or T163465 are resolved
		return true;
	}

}
