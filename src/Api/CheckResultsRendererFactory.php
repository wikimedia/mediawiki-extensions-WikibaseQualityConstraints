<?php

declare( strict_types = 1 );

namespace WikibaseQuality\ConstraintReport\Api;

use Language;
use MessageLocalizer;
use Wikibase\Lib\LanguageFallbackChainFactory;
use Wikibase\Lib\Store\EntityTitleLookup;
use Wikibase\Repo\EntityIdLabelFormatterFactory;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageRendererFactory;

/**
 * @license GPL-2.0-or-later
 */
class CheckResultsRendererFactory {

	private EntityTitleLookup $entityTitleLookup;
	private EntityIdLabelFormatterFactory $entityIdLabelFormatterFactory;
	private LanguageFallbackChainFactory $languageFallbackChainFactory;
	private ViolationMessageRendererFactory $violationMessageRendererFactory;

	public function __construct(
		EntityTitleLookup $entityTitleLookup,
		EntityIdLabelFormatterFactory $entityIdLabelFormatterFactory,
		LanguageFallbackChainFactory $languageFallbackChainFactory,
		ViolationMessageRendererFactory $violationMessageRendererFactory
	) {
		$this->entityTitleLookup = $entityTitleLookup;
		$this->entityIdLabelFormatterFactory = $entityIdLabelFormatterFactory;
		$this->languageFallbackChainFactory = $languageFallbackChainFactory;
		$this->violationMessageRendererFactory = $violationMessageRendererFactory;
	}

	public function getCheckResultsRenderer(
		Language $userLanguage,
		MessageLocalizer $messageLocalizer
	): CheckResultsRenderer {
		return new CheckResultsRenderer(
			$this->entityTitleLookup,
			$this->entityIdLabelFormatterFactory
				->getEntityIdFormatter( $userLanguage ),
			$this->violationMessageRendererFactory
				->getViolationMessageRenderer(
					$userLanguage,
					$this->languageFallbackChainFactory->newFromLanguage( $userLanguage ),
					$messageLocalizer
				)
		);
	}

}
