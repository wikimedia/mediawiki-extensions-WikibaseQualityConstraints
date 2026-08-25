<?php

declare( strict_types = 1 );

namespace WikibaseQuality\ConstraintReport\Api;

use MediaWiki\Language\Language;
use MediaWiki\Language\MessageLocalizer;
use Wikibase\Lib\LanguageFallbackChainFactory;
use Wikibase\Lib\Store\EntityTitleLookup;
use Wikibase\Repo\EntityIdLabelFormatterFactory;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageRendererFactory;

/**
 * @license GPL-2.0-or-later
 */
class CheckResultsRendererFactory {

	public function __construct(
		private readonly EntityTitleLookup $entityTitleLookup,
		private readonly EntityIdLabelFormatterFactory $entityIdLabelFormatterFactory,
		private readonly LanguageFallbackChainFactory $languageFallbackChainFactory,
		private readonly ViolationMessageRendererFactory $violationMessageRendererFactory,
	) {
	}

	public function getCheckResultsRenderer(
		Language $userLanguage,
		MessageLocalizer $messageLocalizer
	): CheckResultsRenderer {
		$languageFallbackChain = $this->languageFallbackChainFactory->newFromLanguage( $userLanguage );

		return new CheckResultsRenderer(
			$this->entityTitleLookup,
			$this->entityIdLabelFormatterFactory
				->getEntityIdFormatter( $userLanguage ),
			$languageFallbackChain,
			$this->violationMessageRendererFactory
				->getViolationMessageRenderer(
					$userLanguage,
					$languageFallbackChain,
					$messageLocalizer
				)
		);
	}

}
