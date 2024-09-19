<?php

declare( strict_types = 1 );

namespace WikibaseQuality\ConstraintReport\Html;

use HtmlArmor;
use InvalidArgumentException;
use MediaWiki\Html\Html;
use Wikimedia\Assert\Assert;

/**
 * @author BP2014N1
 * @license GPL-2.0-or-later
 */
class HtmlTableHeaderBuilder {

	/**
	 * Html content of the header
	 *
	 * @var string|HtmlArmor
	 */
	private $content;

	/**
	 * Determines, whether the column should be sortable or not.
	 */
	private bool $isSortable;

	/**
	 * @param string|HtmlArmor $content
	 * @param bool $isSortable
	 *
	 * @throws InvalidArgumentException
	 */
	public function __construct( $content, bool $isSortable = false ) {
		Assert::parameterType( [ 'string', HtmlArmor::class ], $content, '$content' );

		$this->content = $content;
		$this->isSortable = $isSortable;
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
