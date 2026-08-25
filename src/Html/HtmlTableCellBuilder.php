<?php

namespace WikibaseQuality\ConstraintReport\Html;

use MediaWiki\Html\Html;
use Wikimedia\HtmlArmor\HtmlArmor;

/**
 * @author BP2014N1
 * @license GPL-2.0-or-later
 */
class HtmlTableCellBuilder {

	/**
	 * @param string|HtmlArmor $content Html content of the cell.
	 * @param array $attributes
	 */
	public function __construct(
		private readonly string|HtmlArmor $content,
		private readonly array $attributes = [],
	) {
	}

	/**
	 * @return string HTML
	 */
	public function getContent() {
		return HtmlArmor::getHtml( $this->content );
	}

	/**
	 * @return array
	 */
	public function getAttributes() {
		return $this->attributes;
	}

	/**
	 * @return string HTML
	 */
	public function toHtml() {
		return Html::rawElement( 'td', $this->getAttributes(), $this->getContent() );
	}

}
