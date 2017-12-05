<?php

namespace WikibaseQuality\ConstraintReport;

use DatabaseUpdater;
use JobQueueGroup;
use JobSpecification;
use MediaWiki\MediaWikiServices;
use Title;
use Wikibase\Change;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\EntityChange;
use Wikibase\Lib\Changes\EntityDiffChangedAspects;

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

		$info = $change->getInfo();

		if ( !array_key_exists( 'compactDiff', $info ) ) {
			// the non-compact diff ($info['diff']) does not contain statement diffs (T110996),
			// so we only know that the change *might* affect the constraint statements
			return true;
		}

		/** @var EntityDiffChangedAspects $aspects */
		$aspects = $info['compactDiff'];

		$propertyConstraintId = MediaWikiServices::getInstance()->getMainConfig()
			->get( 'WBQualityConstraintsPropertyConstraintId' );
		return in_array( $propertyConstraintId, $aspects->getStatementChanges() );
	}

}
