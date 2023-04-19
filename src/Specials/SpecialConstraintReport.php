<?php

declare( strict_types = 1 );

namespace WikibaseQuality\ConstraintReport\Specials;

use Config;
use Html;
use HTMLForm;
use IBufferingStatsdDataFactory;
use InvalidArgumentException;
use OOUI\IconWidget;
use OOUI\LabelWidget;
use SpecialPage;
use UnexpectedValueException;
use ValueFormatters\FormatterOptions;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Entity\EntityIdParsingException;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\NumericPropertyId;
use Wikibase\DataModel\Services\EntityId\EntityIdFormatter;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\Lib\Formatters\OutputFormatValueFormatterFactory;
use Wikibase\Lib\Formatters\SnakFormatter;
use Wikibase\Lib\LanguageFallbackChainFactory;
use Wikibase\Lib\Store\EntityTitleLookup;
use Wikibase\Repo\EntityIdLabelFormatterFactory;
use Wikibase\View\EntityIdFormatterFactory;
use WikibaseQuality\ConstraintReport\ConstraintCheck\DelegatingConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageRenderer;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageRendererFactory;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikibaseQuality\ConstraintReport\ConstraintParameterRenderer;
use WikibaseQuality\ConstraintReport\Html\HtmlTableBuilder;
use WikibaseQuality\ConstraintReport\Html\HtmlTableCellBuilder;
use WikibaseQuality\ConstraintReport\Html\HtmlTableHeaderBuilder;

/**
 * Special page that displays all constraints that are defined on an Entity with additional information
 * (whether it complied or was a violation, which parameters the constraint has etc.).
 *
 * @author BP2014N1
 * @license GPL-2.0-or-later
 */
class SpecialConstraintReport extends SpecialPage {

	private EntityIdParser $entityIdParser;
	private EntityLookup $entityLookup;
	private EntityTitleLookup $entityTitleLookup;
	private EntityIdFormatter $entityIdLabelFormatter;
	private EntityIdFormatter $entityIdLinkFormatter;
	private DelegatingConstraintChecker $constraintChecker;
	private ConstraintParameterRenderer $constraintParameterRenderer;
	private ViolationMessageRenderer $violationMessageRenderer;
	private Config $config;
	private IBufferingStatsdDataFactory $dataFactory;

	public static function factory(
		Config $config,
		IBufferingStatsdDataFactory $dataFactory,
		EntityIdFormatterFactory $entityIdHtmlLinkFormatterFactory,
		EntityIdLabelFormatterFactory $entityIdLabelFormatterFactory,
		EntityIdParser $entityIdParser,
		EntityTitleLookup $entityTitleLookup,
		LanguageFallbackChainFactory $languageFallbackChainFactory,
		OutputFormatValueFormatterFactory $valueFormatterFactory,
		EntityLookup $entityLookup,
		DelegatingConstraintChecker $delegatingConstraintChecker,
		ViolationMessageRendererFactory $violationMessageRendererFactory
	): self {
		return new self(
			$entityLookup,
			$entityTitleLookup,
			$entityIdLabelFormatterFactory,
			$entityIdHtmlLinkFormatterFactory,
			$entityIdParser,
			$languageFallbackChainFactory,
			$valueFormatterFactory,
			$delegatingConstraintChecker,
			$violationMessageRendererFactory,
			$config,
			$dataFactory
		);
	}

	public function __construct(
		EntityLookup $entityLookup,
		EntityTitleLookup $entityTitleLookup,
		EntityIdLabelFormatterFactory $entityIdLabelFormatterFactory,
		EntityIdFormatterFactory $entityIdHtmlLinkFormatterFactory,
		EntityIdParser $entityIdParser,
		LanguageFallbackChainFactory $languageFallbackChainFactory,
		OutputFormatValueFormatterFactory $valueFormatterFactory,
		DelegatingConstraintChecker $constraintChecker,
		ViolationMessageRendererFactory $violationMessageRendererFactory,
		Config $config,
		IBufferingStatsdDataFactory $dataFactory
	) {
		parent::__construct( 'ConstraintReport' );

		$this->entityLookup = $entityLookup;
		$this->entityTitleLookup = $entityTitleLookup;
		$this->entityIdParser = $entityIdParser;

		$language = $this->getLanguage();

		$formatterOptions = new FormatterOptions();
		$formatterOptions->setOption( SnakFormatter::OPT_LANG, $language->getCode() );
		$dataValueFormatter = $valueFormatterFactory->getValueFormatter(
			SnakFormatter::FORMAT_HTML,
			$formatterOptions
		);

		$this->entityIdLabelFormatter = $entityIdLabelFormatterFactory->getEntityIdFormatter(
			$language
		);

		$this->entityIdLinkFormatter = $entityIdHtmlLinkFormatterFactory->getEntityIdFormatter(
			$language
		);

		$this->constraintChecker = $constraintChecker;

		$this->constraintParameterRenderer = new ConstraintParameterRenderer(
			$this->entityIdLabelFormatter,
			$dataValueFormatter,
			$this->getContext(),
			$config
		);
		$this->violationMessageRenderer = $violationMessageRendererFactory->getViolationMessageRenderer(
			$language,
			$languageFallbackChainFactory->newFromLanguage( $language ),
			$this->getContext()
		);

		$this->config = $config;
		$this->dataFactory = $dataFactory;
	}

	/**
	 * Returns array of modules that should be added
	 *
	 * @return string[]
	 */
	private function getModules(): array {
		return [
			'SpecialConstraintReportPage',
			'wikibase.quality.constraints.icon',
			'wikibase.alltargets',
		];
	}

	/**
	 * @see SpecialPage::getGroupName
	 *
	 * @return string
	 */
	protected function getGroupName() {
		return 'wikibase';
	}

	/**
	 * @see SpecialPage::getDescription
	 *
	 * @return string
	 */
	public function getDescription() {
		return $this->msg( 'wbqc-constraintreport' )->text();
	}

	/**
	 * @see SpecialPage::execute
	 *
	 * @param string|null $subPage
	 *
	 * @throws InvalidArgumentException
	 * @throws EntityIdParsingException
	 * @throws UnexpectedValueException
	 */
	public function execute( $subPage ) {
		$out = $this->getOutput();

		$postRequest = $this->getContext()->getRequest()->getVal( 'entityid' );
		if ( $postRequest ) {
			$out->redirect( $this->getPageTitle( strtoupper( $postRequest ) )->getLocalURL() );
			return;
		}

		$out->enableOOUI();
		$out->addModules( $this->getModules() );

		$this->setHeaders();

		$out->addHTML( $this->getExplanationText() );
		$this->buildEntityIdForm();

		if ( !$subPage ) {
			return;
		}

		if ( !is_string( $subPage ) ) {
			throw new InvalidArgumentException( '$subPage must be string.' );
		}

		try {
			$entityId = $this->entityIdParser->parse( $subPage );
		} catch ( EntityIdParsingException $e ) {
			$out->addHTML(
				$this->buildNotice( 'wbqc-constraintreport-invalid-entity-id', true )
			);
			return;
		}

		if ( !$this->entityLookup->hasEntity( $entityId ) ) {
			$out->addHTML(
				$this->buildNotice( 'wbqc-constraintreport-not-existent-entity', true )
			);
			return;
		}

		$this->dataFactory->increment(
			'wikibase.quality.constraints.specials.specialConstraintReport.executeCheck'
		);
		$results = $this->constraintChecker->checkAgainstConstraintsOnEntityId( $entityId );

		if ( $results !== [] ) {
			$out->addHTML(
				$this->buildResultHeader( $entityId )
				. $this->buildSummary( $results )
				. $this->buildResultTable( $entityId, $results )
			);
		} else {
			$out->addHTML(
				$this->buildResultHeader( $entityId )
				. $this->buildNotice( 'wbqc-constraintreport-empty-result' )
			);
		}
	}

	/**
	 * Builds html form for entity id input
	 */
	private function buildEntityIdForm(): void {
		$formDescriptor = [
			'entityid' => [
				'class' => 'HTMLTextField',
				'section' => 'section',
				'name' => 'entityid',
				'label-message' => 'wbqc-constraintreport-form-entityid-label',
				'cssclass' => 'wbqc-constraintreport-form-entity-id',
				'placeholder' => $this->msg( 'wbqc-constraintreport-form-entityid-placeholder' )->escaped(),
			],
		];
		$htmlForm = HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext(), 'wbqc-constraintreport-form' );
		$htmlForm->setSubmitText( $this->msg( 'wbqc-constraintreport-form-submit-label' )->escaped() );
		$htmlForm->setSubmitCallback( static function () {
			return false;
		} );
		$htmlForm->setMethod( 'post' );
		$htmlForm->show();
	}

	/**
	 * Builds notice with given message. Optionally notice can be handles as error by settings $error to true
	 *
	 * @param string $messageKey
	 * @param bool $error
	 *
	 * @throws InvalidArgumentException
	 *
	 * @return string HTML
	 */
	private function buildNotice( string $messageKey, bool $error = false ): string {
		$cssClasses = 'wbqc-constraintreport-notice';
		if ( $error ) {
			$cssClasses .= ' wbqc-constraintreport-notice-error';
		}

		return Html::rawElement(
				'p',
				[
					'class' => $cssClasses,
				],
				$this->msg( $messageKey )->escaped()
			);
	}

	/**
	 * @return string HTML
	 */
	private function getExplanationText(): string {
		return Html::rawElement(
			'div',
			[ 'class' => 'wbqc-explanation' ],
			Html::rawElement(
				'p',
				[],
				$this->msg( 'wbqc-constraintreport-explanation-part-one' )->escaped()
			)
			. Html::rawElement(
				'p',
				[],
				$this->msg( 'wbqc-constraintreport-explanation-part-two' )->escaped()
			)
		);
	}

	/**
	 * @param EntityId $entityId
	 * @param CheckResult[] $results
	 *
	 * @return string HTML
	 * @suppress SecurityCheck-DoubleEscaped
	 */
	private function buildResultTable( EntityId $entityId, array $results ): string {
		// Set table headers
		$table = new HtmlTableBuilder(
			[
				new HtmlTableHeaderBuilder(
					$this->msg( 'wbqc-constraintreport-result-table-header-status' )->escaped(),
					true
				),
				new HtmlTableHeaderBuilder(
					$this->msg( 'wbqc-constraintreport-result-table-header-property' )->escaped(),
					true
				),
				new HtmlTableHeaderBuilder(
					$this->msg( 'wbqc-constraintreport-result-table-header-message' )->escaped(),
					true
				),
				new HtmlTableHeaderBuilder(
					$this->msg( 'wbqc-constraintreport-result-table-header-constraint' )->escaped(),
					true
				),
			]
		);

		foreach ( $results as $result ) {
			$table = $this->appendToResultTable( $table, $entityId, $result );
		}

		return $table->toHtml();
	}

	private function appendToResultTable(
		HtmlTableBuilder $table,
		EntityId $entityId,
		CheckResult $result
	): HtmlTableBuilder {
		$message = $result->getMessage();
		if ( $message === null ) {
			// no row for this result
			return $table;
		}

		// Status column
		$statusColumn = $this->formatStatus( $result->getStatus() );

		// Property column
		$propertyId = new NumericPropertyId( $result->getContextCursor()->getSnakPropertyId() );
		$propertyColumn = $this->getClaimLink(
			$entityId,
			$propertyId,
			$this->entityIdLabelFormatter->formatEntityId( $propertyId )
		);

		// Message column
		$messageColumn = $this->violationMessageRenderer->render( $message );

		// Constraint column
		$constraintTypeItemId = $result->getConstraint()->getConstraintTypeItemId();
		try {
			$constraintTypeLabel = $this->entityIdLabelFormatter->formatEntityId( new ItemId( $constraintTypeItemId ) );
		} catch ( InvalidArgumentException $e ) {
			$constraintTypeLabel = htmlspecialchars( $constraintTypeItemId );
		}
		$constraintLink = $this->getClaimLink(
			$propertyId,
			new NumericPropertyId( $this->config->get( 'WBQualityConstraintsPropertyConstraintId' ) ),
			$constraintTypeLabel
		);
		$constraintColumn = $this->buildExpandableElement(
			$constraintLink,
			$this->constraintParameterRenderer->formatParameters( $result->getParameters() ),
			'[...]'
		);

		// Append cells
		$table->appendRow(
			[
				new HtmlTableCellBuilder(
					$statusColumn,
					[],
					true
				),
				new HtmlTableCellBuilder(
					$propertyColumn,
					[],
					true
				),
				new HtmlTableCellBuilder(
					$messageColumn,
					[],
					true
				),
				new HtmlTableCellBuilder(
					$constraintColumn,
					[],
					true
				),
			]
		);

		return $table;
	}

	/**
	 * Returns html text of the result header
	 *
	 * @param EntityId $entityId
	 *
	 * @return string HTML
	 */
	protected function buildResultHeader( EntityId $entityId ): string {
		$entityLink = sprintf( '%s (%s)',
							   $this->entityIdLinkFormatter->formatEntityId( $entityId ),
							   htmlspecialchars( $entityId->getSerialization() ) );

		return Html::rawElement(
			'h3',
			[],
			sprintf( '%s %s', $this->msg( 'wbqc-constraintreport-result-headline' )->escaped(), $entityLink )
		);
	}

	/**
	 * Builds summary from given results
	 *
	 * @param CheckResult[] $results
	 *
	 * @return string HTML
	 */
	protected function buildSummary( array $results ): string {
		$statuses = [];
		foreach ( $results as $result ) {
			$status = strtolower( $result->getStatus() );
			$statuses[$status] = isset( $statuses[$status] ) ? $statuses[$status] + 1 : 1;
		}

		$statusElements = [];
		foreach ( $statuses as $status => $count ) {
			if ( $count > 0 ) {
				$statusElements[] =
					$this->formatStatus( $status )
					. ': '
					. $count;
			}
		}

		return Html::rawElement( 'p', [], implode( ', ', $statusElements ) );
	}

	/**
	 * Builds a html div element with given content and a tooltip with given tooltip content
	 * If $tooltipContent is null, no tooltip will be created
	 *
	 * @param string $content
	 * @param string|null $expandableContent
	 * @param string $indicator
	 *
	 * @throws InvalidArgumentException
	 *
	 * @return string HTML
	 */
	protected function buildExpandableElement(
		string $content,
		?string $expandableContent,
		string $indicator
	): string {
		if ( empty( $expandableContent ) ) {
			return $content;
		}

		$tooltipIndicator = Html::element(
			'span',
			[
				'class' => 'wbqc-expandable-content-indicator wbqc-indicator',
			],
			$indicator
		);

		$wrappedExpandableContent = Html::element(
			'div',
			[
				'class' => 'wbqc-expandable-content',
			],
			$expandableContent
		);

		return sprintf( '%s %s %s', $content, $tooltipIndicator, $wrappedExpandableContent );
	}

	/**
	 * Formats given status to html
	 *
	 * @param string $status
	 *
	 * @throws InvalidArgumentException
	 *
	 * @return string HTML
	 */
	private function formatStatus( string $status ): string {
		$messageName = "wbqc-constraintreport-status-" . strtolower( $status );
		$statusIcons = [
			CheckResult::STATUS_SUGGESTION => [
				'icon' => 'suggestion-constraint-violation',
			],
			CheckResult::STATUS_WARNING => [
				'icon' => 'non-mandatory-constraint-violation',
			],
			CheckResult::STATUS_VIOLATION => [
				'icon' => 'mandatory-constraint-violation',
			],
			CheckResult::STATUS_BAD_PARAMETERS => [
				'icon' => 'alert',
				'flags' => 'warning',
			],
		];

		if ( array_key_exists( $status, $statusIcons ) ) {
			$iconWidget = new IconWidget( $statusIcons[$status] );
			$iconHtml = $iconWidget->toString() . ' ';
		} else {
			$iconHtml = '';
		}

		$labelWidget = new LabelWidget( [
			'label' => $this->msg( $messageName )->text(),
		] );
		$labelHtml = $labelWidget->toString();

		$formattedStatus =
			Html::rawElement(
				'span',
				[
					'class' => 'wbqc-status wbqc-status-' . $status,
				],
				$iconHtml . $labelHtml
			);

		return $formattedStatus;
	}

	/**
	 * Returns html link to given entity with anchor to specified property.
	 *
	 * @param EntityId $entityId
	 * @param NumericPropertyId $propertyId
	 * @param string $text HTML
	 *
	 * @return string HTML
	 */
	private function getClaimLink(
		EntityId $entityId,
		NumericPropertyId $propertyId,
		string $text
	): string {
		return Html::rawElement(
			'a',
			[
				'href' => $this->getClaimUrl( $entityId, $propertyId ),
				'target' => '_blank',
			],
			$text
		);
	}

	/**
	 * Returns url of given entity with anchor to specified property.
	 */
	private function getClaimUrl(
		EntityId $entityId,
		NumericPropertyId $propertyId
	): string {
		$title = $this->entityTitleLookup->getTitleForId( $entityId );
		$entityUrl = sprintf( '%s#%s', $title->getLocalURL(), $propertyId->getSerialization() );

		return $entityUrl;
	}

}
