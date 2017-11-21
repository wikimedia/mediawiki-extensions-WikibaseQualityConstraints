<?php

namespace WikibaseQuality\ConstraintReport;

use DatabaseUpdater;
use JobQueueGroup;
use JobSpecification;
use MediaWiki\MediaWikiServices;
use Title;
use Wikibase\Change;
use Wikibase\EntityChange;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\Diff\EntityDiff;

/**
 * Container for hook callbacks registered in extension.json.
 *
 * @license GNU GPL v2+
 */
final class WikibaseQualityConstraintsHooks {

	/**
	 * @param DatabaseUpdater $updater
	 *
	 * @return bool
	 */
	public static function onCreateSchema( DatabaseUpdater $updater ) {
		$updater->addExtensionTable( 'wbqc_constraints', __DIR__ . '/sql/create_wbqc_constraints.sql' );
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
			/** @var EntityChange $change */
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
