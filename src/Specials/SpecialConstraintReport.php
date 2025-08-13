<?php

declare( strict_types = 1 );

namespace WikibaseQuality\ConstraintReport\Specials;

use HtmlArmor;
use InvalidArgumentException;
use MediaWiki\Config\Config;
use MediaWiki\Html\Html;
use MediaWiki\HTMLForm\Field\HTMLTextField;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\SpecialPage\SpecialPage;
use OOUI\IconWidget;
use OOUI\LabelWidget;
use UnexpectedValueException;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Entity\EntityIdParsingException;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\NumericPropertyId;
use Wikibase\DataModel\Services\EntityId\EntityIdFormatter;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\Lib\LanguageFallbackChainFactory;
use Wikibase\Lib\Store\EntityTitleLookup;
use Wikibase\Repo\EntityIdLabelFormatterFactory;
use Wikibase\View\EntityIdFormatterFactory;
use WikibaseQuality\ConstraintReport\ConstraintCheck\DelegatingConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageRenderer;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageRendererFactory;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikibaseQuality\ConstraintReport\Html\HtmlTableBuilder;
use WikibaseQuality\ConstraintReport\Html\HtmlTableCellBuilder;
use WikibaseQuality\ConstraintReport\Html\HtmlTableHeaderBuilder;
use Wikimedia\Stats\StatsFactory;

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
	private ViolationMessageRenderer $violationMessageRenderer;
	private Config $config;
	private StatsFactory $statsFactory;

	public static function factory(
		Config $config,
		StatsFactory $statsFactory,
		EntityIdFormatterFactory $entityIdHtmlLinkFormatterFactory,
		EntityIdLabelFormatterFactory $entityIdLabelFormatterFactory,
		EntityIdParser $entityIdParser,
		EntityTitleLookup $entityTitleLookup,
		LanguageFallbackChainFactory $languageFallbackChainFactory,
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
			$delegatingConstraintChecker,
			$violationMessageRendererFactory,
			$config,
			$statsFactory
		);
	}

	public function __construct(
		EntityLookup $entityLookup,
		EntityTitleLookup $entityTitleLookup,
		EntityIdLabelFormatterFactory $entityIdLabelFormatterFactory,
		EntityIdFormatterFactory $entityIdHtmlLinkFormatterFactory,
		EntityIdParser $entityIdParser,
		LanguageFallbackChainFactory $languageFallbackChainFactory,
		DelegatingConstraintChecker $constraintChecker,
		ViolationMessageRendererFactory $violationMessageRendererFactory,
		Config $config,
		StatsFactory $statsFactory
	) {
		parent::__construct( 'ConstraintReport', 'wbqc-check-constraints-uncached' );

		$this->entityLookup = $entityLookup;
		$this->entityTitleLookup = $entityTitleLookup;
		$this->entityIdParser = $entityIdParser;

		$language = $this->getLanguage();

		$this->entityIdLabelFormatter = $entityIdLabelFormatterFactory->getEntityIdFormatter(
			$language
		);

		$this->entityIdLinkFormatter = $entityIdHtmlLinkFormatterFactory->getEntityIdFormatter(
			$language
		);

		$this->constraintChecker = $constraintChecker;

		$this->violationMessageRenderer = $violationMessageRendererFactory->getViolationMessageRenderer(
			$language,
			$languageFallbackChainFactory->newFromLanguage( $language ),
			$this->getContext()
		);

		$this->config = $config;
		$this->statsFactory = $statsFactory;
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
	 * @inheritDoc
	 */
	public function getDescription() {
		return $this->msg( 'wbqc-constraintreport' );
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
		parent::execute( $subPage );

		$out = $this->getOutput();

		$postRequest = $this->getContext()->getRequest()->getVal( 'entityid' );
		if ( $postRequest ) {
			try {
				$entityId = $this->entityIdParser->parse( $postRequest );
				$out->redirect( $this->getPageTitle( $entityId->getSerialization() )->getLocalURL() );
				return;
			} catch ( EntityIdParsingException ) {
				// fall through, error is shown later
			}
		}

		$out->enableOOUI();
		$out->addModules( $this->getModules() );

		$this->setHeaders();

		$out->addHTML( $this->getExplanationText() );
		$this->buildEntityIdForm();

		if ( $postRequest ) {
			// must be an invalid entity ID (otherwise we would have redirected and returned above)
			$out->addHTML(
				$this->buildNotice( 'wbqc-constraintreport-invalid-entity-id', true )
			);
			return;
		}

		if ( !$subPage ) {
			return;
		}

		try {
			$entityId = $this->entityIdParser->parse( $subPage );
		} catch ( EntityIdParsingException ) {
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

		$this->statsFactory->getCounter( 'special_constraint_report_execute_check_total' )
			->increment();
		$results = $this->constraintChecker->checkAgainstConstraintsOnEntityId( $entityId );

		if ( !$results ) {
			$out->addHTML( $this->buildResultHeader( $entityId ) .
				$this->buildNotice( 'wbqc-constraintreport-empty-result' )
			);
			return;
		}

		$out->addHTML(
			$this->buildResultHeader( $entityId )
			. $this->buildSummary( $results )
			. $this->buildResultTable( $entityId, $results )
		);
	}

	/**
	 * Builds html form for entity id input
	 */
	private function buildEntityIdForm(): void {
		$formDescriptor = [
			'entityid' => [
				'class' => HTMLTextField::class,
				'section' => 'section',
				'name' => 'entityid',
				'label-message' => 'wbqc-constraintreport-form-entityid-label',
				'cssclass' => 'wbqc-constraintreport-form-entity-id',
				'placeholder' => $this->msg( 'wbqc-constraintreport-form-entityid-placeholder' )->text(),
				'required' => true,
			],
		];
		$htmlForm = HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext(),
			'wbqc-constraintreport-form'
		);
		$htmlForm->setSubmitText( $this->msg( 'wbqc-constraintreport-form-submit-label' )->text() );
		$htmlForm->setSubmitCallback( static fn () => false );
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
		$cssClasses = [ 'wbqc-constraintreport-notice' ];
		if ( $error ) {
			$cssClasses[] = ' wbqc-constraintreport-notice-error';
		}

		return Html::element(
			'p',
			[ 'class' => $cssClasses ],
			$this->msg( $messageKey )->text()
		);
	}

	/**
	 * @return string HTML
	 */
	private function getExplanationText(): string {
		return Html::rawElement(
			'div',
			[ 'class' => 'wbqc-explanation' ],
			Html::element(
				'p',
				[],
				$this->msg( 'wbqc-constraintreport-explanation-part-one' )->text()
			)
			. Html::element(
				'p',
				[],
				$this->msg( 'wbqc-constraintreport-explanation-part-two' )->text()
			)
		);
	}

	/**
	 * @param EntityId $entityId
	 * @param CheckResult[] $results
	 *
	 * @return string HTML
	 */
	private function buildResultTable( EntityId $entityId, array $results ): string {
		// Set table headers
		$table = new HtmlTableBuilder(
			[
				new HtmlTableHeaderBuilder(
					$this->msg( 'wbqc-constraintreport-result-table-header-status' )->text(),
					true
				),
				new HtmlTableHeaderBuilder(
					$this->msg( 'wbqc-constraintreport-result-table-header-property' )->text(),
					true
				),
				new HtmlTableHeaderBuilder(
					$this->msg( 'wbqc-constraintreport-result-table-header-message' )->text(),
					true
				),
				new HtmlTableHeaderBuilder(
					$this->msg( 'wbqc-constraintreport-result-table-header-constraint' )->text(),
					true
				),
			]
		);

		foreach ( $results as $result ) {
			$this->appendToResultTable( $table, $entityId, $result );
		}

		return $table->toHtml();
	}

	private function appendToResultTable(
		HtmlTableBuilder $table,
		EntityId $entityId,
		CheckResult $result
	): void {
		$message = $result->getMessage();
		if ( !$message ) {
			// no row for this result
			return;
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
		} catch ( InvalidArgumentException ) {
			$constraintTypeLabel = htmlspecialchars( $constraintTypeItemId );
		}
		$constraintColumn = $this->getClaimLink(
			$propertyId,
			new NumericPropertyId( $this->config->get( 'WBQualityConstraintsPropertyConstraintId' ) ),
			$constraintTypeLabel
		);

		// Append cells
		$table->appendRow(
			[
				new HtmlTableCellBuilder(
					new HtmlArmor( $statusColumn )
				),
				new HtmlTableCellBuilder(
					new HtmlArmor( $propertyColumn )
				),
				new HtmlTableCellBuilder(
					new HtmlArmor( $messageColumn )
				),
				new HtmlTableCellBuilder(
					new HtmlArmor( $constraintColumn )
				),
			]
		);
	}

	/**
	 * Returns html text of the result header
	 *
	 * @param EntityId $entityId
	 *
	 * @return string HTML
	 */
	protected function buildResultHeader( EntityId $entityId ): string {
		return Html::rawElement(
			'h3',
			[],
			$this->msg( 'wbqc-constraintreport-result-headline' )->escaped() .
				$this->msg( 'word-separator' )->escaped() .
				$this->entityIdLinkFormatter->formatEntityId( $entityId ) .
				$this->msg( 'word-separator' )->escaped() .
				$this->msg( 'parentheses', $entityId->getSerialization() )->escaped()
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
			$statuses[$status] ??= 0;
			$statuses[$status]++;
		}

		$statusElements = [];
		foreach ( $statuses as $status => $count ) {
			$statusElements[] = $this->formatStatus( $status ) .
				$this->msg( 'colon-separator' )->escaped() .
				htmlspecialchars( $this->getLanguage()->formatNum( $count ) );
		}

		return Html::rawElement( 'p', [],
			implode( $this->msg( 'comma-separator' )->escaped(), $statusElements )
		);
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
			$iconHtml = new IconWidget( $statusIcons[$status] ) .
				$this->msg( 'word-separator' )->escaped();
		} else {
			$iconHtml = '';
		}

		$labelWidget = new LabelWidget( [ 'label' => $this->msg( $messageName )->text() ] );

		return Html::rawElement(
			'span',
			[
				'class' => 'wbqc-status wbqc-status-' . $status,
			],
			$iconHtml . $labelWidget
		);
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
		$title = clone $this->entityTitleLookup->getTitleForId( $entityId );
		$title->setFragment( $propertyId->getSerialization() );

		return Html::rawElement( 'a',
			[
				'href' => $title->getLinkURL(),
				'target' => '_blank',
			],
			$text
		);
	}

}
