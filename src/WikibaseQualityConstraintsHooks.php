<?php

namespace WikibaseQuality\ConstraintReport;

use Config;
use DatabaseUpdater;
use JobQueueGroup;
use JobSpecification;
use MediaWiki\MediaWikiServices;
use OutputPage;
use Skin;
use Title;
use Wikibase\Change;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\EntityChange;
use Wikibase\Lib\Changes\EntityDiffChangedAspects;
use Wikibase\Repo\WikibaseRepo;
use WikibaseQuality\ConstraintReport\Api\ResultsCache;
use WikiPage;

/**
 * Container for hook callbacks registered in extension.json.
 *
 * @license GPL-2.0-or-later
 */
final class WikibaseQualityConstraintsHooks {

	/**
	 * @param DatabaseUpdater $updater
	 */
	public static function onCreateSchema( DatabaseUpdater $updater ) {
		$updater->addExtensionTable( 'wbqc_constraints', __DIR__ . '/../sql/create_wbqc_constraints.sql' );
		$updater->addExtensionField( 'wbqc_constraints', 'constraint_id', __DIR__ . '/../sql/patch-wbqc_constraints-constraint_id.sql' );
	}

	public static function onWikibaseChange( Change $change ) {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		if ( $config->get( 'WBQualityConstraintsEnableConstraintsImportFromStatements' ) &&
			self::isConstraintStatementsChange( $config, $change )
		) {
			/** @var EntityChange $change */
			$title = Title::newMainPage();
			$params = [ 'propertyId' => $change->getEntityId()->getSerialization() ];
			JobQueueGroup::singleton()->push(
				new JobSpecification( 'constraintsTableUpdate', $params, [], $title )
			);
		}
	}

	public static function isConstraintStatementsChange( Config $config, Change $change ) {
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

		$propertyConstraintId = $config->get( 'WBQualityConstraintsPropertyConstraintId' );
		return in_array( $propertyConstraintId, $aspects->getStatementChanges() );
	}

	public static function onArticlePurge( WikiPage $wikiPage ) {
		$repo = WikibaseRepo::getDefaultInstance();

		$entityContentFactory = $repo->getEntityContentFactory();
		if ( $entityContentFactory->isEntityContentModel( $wikiPage->getContentModel() ) ) {
			$entityId = $entityContentFactory->getEntityIdForTitle( $wikiPage->getTitle() );
			$resultsCache = ResultsCache::getDefaultInstance();
			$resultsCache->delete( $entityId );
		}
	}

	/**
	 * @param string $userName
	 * @param int $timestamp UTC timestamp (seconds since the Epoch)
	 * @return bool
	 */
	public static function isGadgetEnabledForUserName( $userName, $timestamp ) {
		$initial = $userName[0];

		if ( $initial === 'Z' ) {
			$firstWeek = 0;
		} elseif ( $initial >= 'W' && $initial < 'Z' ) {
			$firstWeek = 1;
		} elseif ( $initial >= 'T' && $initial < 'W' ) {
			$firstWeek = 2;
		} elseif ( $initial >= 'N' && $initial < 'T' ) {
			$firstWeek = 3;
		} elseif ( $initial >= 'E' && $initial < 'N' ) {
			$firstWeek = 4;
		} else {
			$firstWeek = 5;
		}

		$threshold = gmmktime(
			0, // hour
			0, // minute
			0, // second
			3, // month; overflows to 3 or 4 depending on day
			$firstWeek * 7 + 1, // day
			2018 // year
		);

		return $timestamp >= $threshold;
	}

	public static function onBeforePageDisplay( OutputPage $out, Skin $skin ) {
		$repo = WikibaseRepo::getDefaultInstance();

		$lookup = $repo->getEntityNamespaceLookup();
		$title = $out->getTitle();
		if ( $title === null ) {
			return;
		}

		if ( !$lookup->isEntityNamespace( $title->getNamespace() ) ) {
			return;
		}
		if ( !$out->getUser()->isLoggedIn() ) {
			return;
		}
		if ( empty( $out->getJsConfigVars()['wbIsEditView'] ) ) {
			return;
		}

		if ( self::isGadgetEnabledForUserName( $out->getUser()->getName(), time() ) ) {
			$out->addModules( 'wikibase.quality.constraints.gadget' );
		}
	}

}
