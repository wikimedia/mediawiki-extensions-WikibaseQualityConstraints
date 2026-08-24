<?php

namespace WikibaseQuality\ConstraintReport\Html;

use InvalidArgumentException;
use MediaWiki\Html\Html;

/**
 * @author BP2014N1
 * @license GPL-2.0-or-later
 */
class HtmlTableBuilder {

	/**
	 * @var HtmlTableHeaderBuilder[]
	 */
	private array $headers = [];

	/** @var HtmlTableCellBuilder[][] */
	private array $rows = [];

	private bool $isSortable = false;

	/**
	 * @param array<HtmlTableHeaderBuilder|string> $headers
	 */
	public function __construct( array $headers ) {
		foreach ( $headers as $header ) {
			$this->addHeader( $header );
		}
	}

	private function addHeader( HtmlTableHeaderBuilder|string $header ): void {
		if ( is_string( $header ) ) {
			$header = new HtmlTableHeaderBuilder( $header );
		}

		$this->headers[] = $header;

		if ( $header->getIsSortable() ) {
			$this->isSortable = true;
		}
	}

	/**
	 * @return HtmlTableHeaderBuilder[]
	 */
	public function getHeaders(): array {
		return $this->headers;
	}

	/**
	 * @return HtmlTableCellBuilder[][]
	 */
	public function getRows(): array {
		return $this->rows;
	}

	public function isSortable(): bool {
		return $this->isSortable;
	}

	/**
	 * Adds row with specified cells to table.
	 *
	 * @param array<HtmlTableCellBuilder|string> $cells
	 */
	public function appendRow( array $cells ): void {
		foreach ( $cells as $key => $cell ) {
			if ( is_string( $cell ) ) {
				$cells[$key] = new HtmlTableCellBuilder( $cell );
			} elseif ( !( $cell instanceof HtmlTableCellBuilder ) ) {
				throw new InvalidArgumentException( '$cells must be array of HtmlTableCell objects.' );
			}
		}

		$this->rows[] = $cells;
	}

	/**
	 * Adds rows with specified cells to table.
	 *
	 * @param array<HtmlTableCellBuilder|string>[] $rows
	 */
	public function appendRows( array $rows ): void {
		foreach ( $rows as $cells ) {
			if ( !is_array( $cells ) ) {
				throw new InvalidArgumentException( '$rows must be array of arrays of HtmlTableCell objects.' );
			}

			$this->appendRow( $cells );
		}
	}

	public function toHtml(): string {
		$headers = '';
		foreach ( $this->headers as $header ) {
			$headers .= $header->toHtml();
		}

		$rows = '';
		foreach ( $this->rows as $row ) {
			$rows .= Html::openElement( 'tr' );
			foreach ( $row as $cell ) {
				$rows .= $cell->toHtml();
			}
			$rows .= Html::closeElement( 'tr' );
		}

		return Html::rawElement( 'table', [
				'class' => [
					'wikitable',
					'sortable' => $this->isSortable,
				],
			],
			Html::rawElement( 'thead', [],
				Html::rawElement( 'tr', [], $headers )
			) .
			Html::rawElement( 'tbody', [], $rows )
		);
	}

}
