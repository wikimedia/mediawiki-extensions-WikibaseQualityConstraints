<?php

declare( strict_types = 1 );

namespace WikibaseQuality\ConstraintReport\Html;

use MediaWiki\Html\Html;
use Wikimedia\HtmlArmor\HtmlArmor;

/**
 * @author BP2014N1
 * @license GPL-2.0-or-later
 */
class HtmlTableHeaderBuilder {

	/**
	 * @param string|HtmlArmor $content Html content of the header
	 * @param bool $isSortable Determines, whether the column should be sortable or not.
	 */
	public function __construct(
		private readonly string|HtmlArmor $content,
		private readonly bool $isSortable = false,
	) {
	}

	/**
	 * @return string HTML
	 */
	public function getContent(): string {
		return HtmlArmor::getHtml( $this->content );
	}

	public function getIsSortable(): bool {
		return $this->isSortable;
	}

	/**
	 * Returns header as html.
	 *
	 * @return string HTML
	 */
	public function toHtml(): string {
		$attributes = [ 'role' => 'columnheader button' ];

		if ( !$this->isSortable ) {
			$attributes['class'] = 'unsortable';
		}

		return Html::rawElement( 'th', $attributes, $this->getContent() );
	}

}
