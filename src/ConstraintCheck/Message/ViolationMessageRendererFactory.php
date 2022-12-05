<?php

declare( strict_types = 1 );

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Message;

use Config;
use Language;
use MediaWiki\Languages\LanguageNameUtils;
use MessageLocalizer;
use ValueFormatters\FormatterOptions;
use Wikibase\Lib\Formatters\OutputFormatValueFormatterFactory;
use Wikibase\Lib\Formatters\SnakFormatter;
use Wikibase\View\EntityIdFormatterFactory;

/**
 * @license GPL-2.0-or-later
 */
class ViolationMessageRendererFactory {

	private Config $config;
	private LanguageNameUtils $languageNameUtils;
	private MessageLocalizer $messageLocalizer;
	private EntityIdFormatterFactory $entityIdHtmlLinkFormatterFactory;
	private OutputFormatValueFormatterFactory $valueFormatterFactory;

	public function __construct(
		Config $config,
		LanguageNameUtils $languageNameUtils,
		MessageLocalizer $messageLocalizer,
		EntityIdFormatterFactory $entityIdHtmlLinkFormatterFactory,
		OutputFormatValueFormatterFactory $valueFormatterFactory
	) {
		$this->config = $config;
		$this->languageNameUtils = $languageNameUtils;
		$this->messageLocalizer = $messageLocalizer;
		$this->entityIdHtmlLinkFormatterFactory = $entityIdHtmlLinkFormatterFactory;
		$this->valueFormatterFactory = $valueFormatterFactory;
	}

	public function getViolationMessageRenderer( Language $language ): ViolationMessageRenderer {
		$formatterOptions = new FormatterOptions();
		$formatterOptions->setOption( SnakFormatter::OPT_LANG, $language->getCode() );
		return new MultilingualTextViolationMessageRenderer(
			$this->entityIdHtmlLinkFormatterFactory
				->getEntityIdFormatter( $language ),
			$this->valueFormatterFactory
				->getValueFormatter( SnakFormatter::FORMAT_HTML, $formatterOptions ),
			$this->languageNameUtils,
			$this->messageLocalizer,
			$this->config
		);
	}

}
