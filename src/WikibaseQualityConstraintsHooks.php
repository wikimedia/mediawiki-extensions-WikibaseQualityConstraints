<?php

namespace WikibaseQuality\ConstraintReport;

use BetaFeatures;
use Config;
use DatabaseUpdater;
use ExtensionRegistry;
use JobQueueGroup;
use JobSpecification;
use MediaWiki\MediaWikiServices;
use OutputPage;
use Skin;
use User;
use Wikibase\Change;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\EntityChange;
use Wikibase\Lib\Changes\EntityDiffChangedAspects;
use Wikibase\Repo\WikibaseRepo;
use WikibaseQuality\ConstraintReport\Api\ResultsCache;
use WikibaseQuality\ConstraintReport\Job\CheckConstraintsJob;
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
		$updater->addExtensionTable(
			'wbqc_constraints',
			__DIR__ . '/../sql/create_wbqc_constraints.sql'
		);
		$updater->addExtensionField(
			'wbqc_constraints',
			'constraint_id',
			__DIR__ . '/../sql/patch-wbqc_constraints-constraint_id.sql'
		);
		$updater->addExtensionIndex(
			'wbqc_constraints',
			'wbqc_constraints_guid_uniq',
			__DIR__ . '/../sql/patch-wbqc_constraints-wbqc_constraints_guid_uniq.sql'
		);
	}

	public static function onWikibaseChange( Change $change ) {
		if ( !( $change instanceof EntityChange ) ) {
			return;
		}

		/** @var EntityChange $change */
		$config = MediaWikiServices::getInstance()->getMainConfig();

		// If jobs are enabled and the results would be stored in some way run a job.
		if (
			$config->get( 'WBQualityConstraintsEnableConstraintsCheckJobs' ) &&
			$config->get( 'WBQualityConstraintsCacheCheckConstraintsResults' ) &&
			self::isSelectedForJobRunBasedOnPercentage()
		) {
			$params = [ 'entityId' => $change->getEntityId()->getSerialization() ];
			JobQueueGroup::singleton()->push(
				new JobSpecification( CheckConstraintsJob::COMMAND, $params )
			);
		}

		if ( $config->get( 'WBQualityConstraintsEnableConstraintsImportFromStatements' ) &&
			self::isConstraintStatementsChange( $config, $change )
		) {
			$params = [ 'propertyId' => $change->getEntityId()->getSerialization() ];
			JobQueueGroup::singleton()->push(
				new JobSpecification( 'constraintsTableUpdate', $params )
			);
		}
	}

	private static function isSelectedForJobRunBasedOnPercentage() {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$percentage = $config->get( 'WBQualityConstraintsEnableConstraintsCheckJobsRatio' );

		return mt_rand( 1, 100 ) <= $percentage;
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
			if ( $entityId !== null ) {
				$resultsCache = ResultsCache::getDefaultInstance();
				$resultsCache->delete( $entityId );
			}
		}
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
		if ( empty( $out->getJsConfigVars()['wbIsEditView'] ) ) {
			return;
		}

		$out->addModules( 'wikibase.quality.constraints.suggestions' );

		if ( !$out->getUser()->isLoggedIn() ) {
			return;
		}

		$out->addModules( 'wikibase.quality.constraints.gadget' );
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/GetBetaFeaturePreferences
	 *
	 * @param User $user
	 * @param array[] &$prefs
	 */
	public static function onGetBetaFeaturePreferences( User $user, array &$prefs ) {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$extensionAssetsPath = $config->get( 'ExtensionAssetsPath' );
		if ( $config->get( 'WBQualityConstraintsSuggestionsBetaFeature' ) ) {
			$prefs['constraint-suggestions'] = [
					'label-message' => 'wbqc-beta-feature-label-message',
					'desc-message' => 'wbqc-beta-feature-description-message',
					'screenshot' => [
							'ltr' => "$extensionAssetsPath/WikibaseQualityConstraints/resources/ConstraintSuggestions-beta-features-ltr.svg",
							'rtl' => "$extensionAssetsPath/WikibaseQualityConstraints/resources/ConstraintSuggestions-beta-features-rtl.svg",
					],
					'info-link'
					=> 'https://www.mediawiki.org/wiki/Special:MyLanguage/Help:Constraints_suggestions',
					'discussion-link'
					=> 'https://www.mediawiki.org/wiki/Help_talk:Constraints_suggestions',
					'requirements' => [
							'javascript' => true,
					],
			];
		}
	}

	/**
	 * Hook: MakeGlobalVariablesScript
	 * @param array &$vars
	 * @param OutputPage $out
	 */
	public static function addVariables( &$vars, OutputPage $out ) {
		$config = MediaWikiServices::getInstance()->getMainConfig();

		$vars['wbQualityConstraintsPropertyConstraintId'] = $config->get( 'WBQualityConstraintsPropertyConstraintId' );
		$vars['wbQualityConstraintsOneOfConstraintId'] = $config->get( 'WBQualityConstraintsOneOfConstraintId' );
		$vars['wbQualityConstraintsAllowedQualifierConstraintId'] = $config->get( 'WBQualityConstraintsAllowedQualifiersConstraintId' );
		$vars['wbQualityConstraintsPropertyId'] = $config->get( 'WBQualityConstraintsPropertyId' );
		$vars['wbQualityConstraintsQualifierOfPropertyConstraintId'] = $config->get( 'WBQualityConstraintsQualifierOfPropertyConstraintId' );

		$vars['wbQualityConstraintsSuggestionsGloballyEnabled'] = false;

		if ( $config->get( 'WBQualityConstraintsSuggestionsBetaFeature' ) &&
			ExtensionRegistry::getInstance()->isLoaded( 'BetaFeatures' ) &&
			BetaFeatures::isFeatureEnabled( $out->getUser(), 'constraint-suggestions' )
			) {
			$vars['wbQualityConstraintsSuggestionsGloballyEnabled'] = true;
		}
	}

}
