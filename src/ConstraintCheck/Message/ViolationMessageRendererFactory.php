<?php

declare( strict_types = 1 );

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Message;

use MediaWiki\Config\Config;
use MediaWiki\Language\Language;
use MediaWiki\Language\LanguageNameUtils;
use MediaWiki\Language\MessageLocalizer;
use ValueFormatters\FormatterOptions;
use Wikibase\Lib\Formatters\OutputFormatValueFormatterFactory;
use Wikibase\Lib\Formatters\SnakFormatter;
use Wikibase\Lib\TermLanguageFallbackChain;
use Wikibase\View\EntityIdFormatterFactory;

/**
 * @license GPL-2.0-or-later
 */
class ViolationMessageRendererFactory {

	public function __construct(
		private readonly Config $config,
		private readonly LanguageNameUtils $languageNameUtils,
		private readonly EntityIdFormatterFactory $entityIdHtmlLinkFormatterFactory,
		private readonly OutputFormatValueFormatterFactory $valueFormatterFactory,
	) {
	}

	public function getViolationMessageRenderer(
		Language $userLanguage,
		TermLanguageFallbackChain $languageFallbackChain,
		MessageLocalizer $messageLocalizer
	): ViolationMessageRenderer {
		$userLanguageCode = $userLanguage->getCode();
		$formatterOptions = new FormatterOptions();
		$formatterOptions->setOption( SnakFormatter::OPT_LANG, $userLanguageCode );
		return new MultilingualTextViolationMessageRenderer(
			$this->entityIdHtmlLinkFormatterFactory
				->getEntityIdFormatter( $userLanguage ),
			$this->valueFormatterFactory
				->getValueFormatter( SnakFormatter::FORMAT_HTML, $formatterOptions ),
			$this->languageNameUtils,
			$userLanguageCode,
			$languageFallbackChain,
			$messageLocalizer,
			$this->config
		);
	}

}
