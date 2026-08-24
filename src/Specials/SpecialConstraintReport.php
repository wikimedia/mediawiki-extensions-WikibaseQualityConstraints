<?php

declare( strict_types = 1 );

namespace WikibaseQuality\ConstraintReport\Specials;

use InvalidArgumentException;
use MediaWiki\Html\Html;
use MediaWiki\HTMLForm\Field\HTMLTextField;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\SpecialPage\SpecialPage;
use UnexpectedValueException;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Entity\EntityIdParsingException;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\Lib\LanguageFallbackChainFactory;
use Wikibase\Lib\Store\EntityTitleLookup;
use Wikibase\Repo\EntityIdLabelFormatterFactory;
use Wikibase\View\EntityIdFormatterFactory;
use WikibaseQuality\ConstraintReport\ConstraintCheck\DelegatingConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageRendererFactory;
use Wikimedia\Stats\StatsFactory;

/**
 * Special page that displays all constraints that are defined on an Entity with additional information
 * (whether it complied or was a violation, which parameters the constraint has etc.).
 *
 * @author BP2014N1
 * @license GPL-2.0-or-later
 */
class SpecialConstraintReport extends SpecialPage {

	public function __construct(
		private readonly StatsFactory $statsFactory,
		private readonly EntityIdFormatterFactory $entityIdHtmlLinkFormatterFactory,
		private readonly EntityIdLabelFormatterFactory $entityIdLabelFormatterFactory,
		private readonly EntityIdParser $entityIdParser,
		private readonly EntityTitleLookup $entityTitleLookup,
		private readonly LanguageFallbackChainFactory $languageFallbackChainFactory,
		private readonly EntityLookup $entityLookup,
		private readonly DelegatingConstraintChecker $constraintChecker,
		private readonly ViolationMessageRendererFactory $violationMessageRendererFactory,
	) {
		parent::__construct( 'ConstraintReport' );
	}

	/** @inheritDoc */
	public function getRestriction(): string {
		return 'wbqc-check-constraints-uncached';
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

		$constraintReportTableBuilder = $this->getConstraintReportTableBuilder();
		if ( !$results ) {
			$out->addHTML( $constraintReportTableBuilder->buildResultHeader( $entityId ) .
				$this->buildNotice( 'wbqc-constraintreport-empty-result' )
			);
			return;
		}

		$out->addHTML(
			$constraintReportTableBuilder->buildResultHeader( $entityId )
			. $constraintReportTableBuilder->buildSummary( $results )
			. $constraintReportTableBuilder->buildResultTable( $entityId, $results )
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

	private function getConstraintReportTableBuilder(): ConstraintReportTableBuilder {
		$language = $this->getLanguage();

		$entityIdLabelFormatter = $this->entityIdLabelFormatterFactory->getEntityIdFormatter(
			$language
		);

		$entityIdLinkFormatter = $this->entityIdHtmlLinkFormatterFactory->getEntityIdFormatter(
			$language
		);

		$violationMessageRenderer = $this->violationMessageRendererFactory->getViolationMessageRenderer(
			$language,
			$this->languageFallbackChainFactory->newFromLanguage( $language ),
			$this->getContext()
		);

		return new ConstraintReportTableBuilder(
			$this->getLanguage(),
			$this,
			$this->entityTitleLookup,
			$entityIdLabelFormatter,
			$entityIdLinkFormatter,
			$violationMessageRenderer,
			$this->getConfig(),
		);
	}

}
