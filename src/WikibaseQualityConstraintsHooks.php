<?php

namespace WikibaseQuality\ConstraintReport;

use MediaWiki\MediaWikiServices;
use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MediaWiki\Page\Hook\ArticlePurgeHook;
use MediaWiki\Registration\ExtensionRegistry;
use Wikibase\Repo\WikibaseRepo;
use WikibaseQuality\ConstraintReport\Api\ResultsCache;

/**
 * Container for hook callbacks registered in extension.json.
 *
 * @license GPL-2.0-or-later
 */
final class WikibaseQualityConstraintsHooks implements
	ArticlePurgeHook,
	BeforePageDisplayHook
{

	/** @inheritDoc */
	public function onArticlePurge( $wikiPage ) {
		$entityContentFactory = WikibaseRepo::getEntityContentFactory();
		if ( $entityContentFactory->isEntityContentModel( $wikiPage->getContentModel() ) ) {
			$entityIdLookup = WikibaseRepo::getEntityIdLookup();
			$entityId = $entityIdLookup->getEntityIdForTitle( $wikiPage->getTitle() );
			if ( $entityId !== null ) {
				$resultsCache = ResultsCache::getDefaultInstance();
				$resultsCache->delete( $entityId );
			}
		}
	}

	/** @inheritDoc */
	public function onBeforePageDisplay( $out, $skin ): void {
		$lookup = WikibaseRepo::getEntityNamespaceLookup();
		$title = $out->getTitle();
		if ( $title === null ) {
			return;
		}

		if ( !$lookup->isNamespaceWithEntities( $title->getNamespace() ) ) {
			return;
		}

		$jsConfigVars = $out->getJsConfigVars();
		if ( empty( $jsConfigVars['wbIsEditView'] ) || empty( $jsConfigVars['wbEntityId'] ) ) {
			return;
		}

		$services = MediaWikiServices::getInstance();

		$isMobileView = ExtensionRegistry::getInstance()->isLoaded( 'MobileFrontend' ) &&
			$services->getService( 'MobileFrontend.Context' )->shouldDisplayMobileView();
		if ( $isMobileView ) {
			return;
		}

		$out->addModules( 'wikibase.quality.constraints.suggestions' );

		if ( $out->getUser()->isAllowed( 'wbqc-check-constraints' ) ) {
			$out->addModules( 'wikibase.quality.constraints.gadget' );
		}
	}

}
