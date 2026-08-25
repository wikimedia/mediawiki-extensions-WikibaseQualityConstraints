<?php

declare( strict_types = 1 );

namespace WikibaseQuality\ConstraintReport\Specials;

use InvalidArgumentException;
use MediaWiki\Config\Config;
use MediaWiki\Html\Html;
use MediaWiki\Language\Language;
use MediaWiki\Language\MessageLocalizer;
use MediaWiki\Message\Message;
use OOUI\IconWidget;
use OOUI\LabelWidget;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\NumericPropertyId;
use Wikibase\DataModel\Services\EntityId\EntityIdFormatter;
use Wikibase\Lib\Store\EntityTitleLookup;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageRenderer;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikibaseQuality\ConstraintReport\Html\HtmlTableBuilder;
use WikibaseQuality\ConstraintReport\Html\HtmlTableCellBuilder;
use WikibaseQuality\ConstraintReport\Html\HtmlTableHeaderBuilder;
use Wikimedia\HtmlArmor\HtmlArmor;

/**
 * @license GPL-2.0-or-later
 */
class ConstraintReportTableBuilder implements MessageLocalizer {

	public function __construct(
		private readonly Language $language,
		private readonly MessageLocalizer $messageLocalizer,
		private readonly EntityTitleLookup $entityTitleLookup,
		private readonly EntityIdFormatter $entityIdLabelFormatter,
		private readonly EntityIdFormatter $entityIdLinkFormatter,
		private readonly ViolationMessageRenderer $violationMessageRenderer,
		private readonly Config $config,
	) {
	}

	/** @inheritDoc */
	public function msg( $key, ...$params ): Message {
		return $this->messageLocalizer->msg( $key, ...$params );
	}

	/**
	 * Returns html text of the result header
	 *
	 * @param EntityId $entityId
	 *
	 * @return string HTML
	 */
	public function buildResultHeader( EntityId $entityId ): string {
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
	public function buildSummary( array $results ): string {
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
				htmlspecialchars( $this->language->formatNum( $count ) );
		}

		return Html::rawElement( 'p', [],
			implode( $this->msg( 'comma-separator' )->escaped(), $statusElements )
		);
	}

	/**
	 * @param EntityId $entityId
	 * @param CheckResult[] $results
	 *
	 * @return string HTML
	 */
	public function buildResultTable( EntityId $entityId, array $results ): string {
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
