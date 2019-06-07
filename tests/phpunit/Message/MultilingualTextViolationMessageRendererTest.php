<?php

namespace WikibaseQuality\ConstraintReport\Tests\Message;

use Config;
use DataValues\MonolingualTextValue;
use DataValues\MultilingualTextValue;
use DataValues\StringValue;
use HashConfig;
use Language;
use Message;
use MessageLocalizer;
use MockMessageLocalizer;
use ValueFormatters\StringFormatter;
use ValueFormatters\ValueFormatter;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Services\EntityId\EntityIdFormatter;
use Wikibase\DataModel\Services\EntityId\PlainEntityIdFormatter;
use Wikibase\Lib\Formatters\UnDeserializableValueFormatter;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\MultilingualTextViolationMessageRenderer;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessage;
use WikibaseQuality\ConstraintReport\Role;
use Wikimedia\TestingAccessWrapper;

/**
 * @group WikibaseQualityConstraints
 *
 * @author Lucas Werkmeister
 * @license GPL-2.0-or-later
 */
class MultilingualTextViolationMessageRendererTest extends \MediaWikiTestCase {

	/**
	 * Create a new MultilingualTextViolationMessageRenderer
	 * with some constructor arguments defaulting to a simple base implementation.
	 *
	 * @param EntityIdFormatter|null $entityIdFormatter
	 * @param ValueFormatter|null $dataValueFormatter
	 * @param MessageLocalizer|null $messageLocalizer
	 * @param Config|null $config
	 * @param int $maxListLength
	 * @return MultilingualTextViolationMessageRenderer
	 */
	private function newMultilingualTextViolationMessageRenderer(
		EntityIdFormatter $entityIdFormatter = null,
		ValueFormatter $dataValueFormatter = null,
		MessageLocalizer $messageLocalizer = null,
		Config $config = null,
		$maxListLength = 10
	) {
		if ( $entityIdFormatter === null ) {
			$entityIdFormatter = new PlainEntityIdFormatter();
		}
		if ( $dataValueFormatter === null ) {
			$dataValueFormatter = new UnDeserializableValueFormatter();
		}
		if ( $messageLocalizer === null ) {
			$messageLocalizer = new MockMessageLocalizer();
		}
		if ( $config === null ) {
			$config = new HashConfig( [
				'WBQualityConstraintsConstraintCheckedOnMainValueId' => 'Q1',
				'WBQualityConstraintsConstraintCheckedOnQualifiersId' => 'Q2',
				'WBQualityConstraintsConstraintCheckedOnReferencesId' => 'Q3',
			] );
		}
		return new MultilingualTextViolationMessageRenderer(
			$entityIdFormatter,
			$dataValueFormatter,
			$messageLocalizer,
			$config,
			$maxListLength
		);
	}

	/**
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\MultilingualTextViolationMessageRenderer::render
	 */
	public function testRender_fallback() {
		$messageKey = 'wbqc-violation-message-format';
		$code = 'https?://[^/]+/.*';
		$message = ( new ViolationMessage( $messageKey ) )
			->withEntityId( new ItemId( 'Q1' ) )
			->withDataValue( new StringValue( 'ftp://mirror.example/' ) )
			->withInlineCode( $code );
		$renderer = $this->newMultilingualTextViolationMessageRenderer( null, new StringFormatter() );

		$rendered = $renderer->render( $message );

		$expected = '(wbqc-violation-message-format: Q1, ' .
			'ftp://mirror.example/, <code>https?://[^/]+/.*</code>)';
		$this->assertSame( $expected, $rendered );
	}

	/**
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\MultilingualTextViolationMessageRenderer::render
	 */
	public function testRender_multilingualText() {
		$messageKey = 'wbqc-violation-message-format-clarification';
		$monolingualText = new MonolingualTextValue( 'en', 'clarification' );
		$multilingualText = new MultilingualTextValue( [ $monolingualText ] );
		$message = ( new ViolationMessage( $messageKey ) )
			->withEntityId( new ItemId( 'Q1' ) )
			->withDataValue( new StringValue( 'ftp://mirror.example/' ) )
			->withInlineCode( 'https?://[^/]+/.*' )
			->withMultilingualText( $multilingualText );
		$renderer = $this->newMultilingualTextViolationMessageRenderer( null, new StringFormatter() );

		$rendered = $renderer->render( $message );

		$expected = '(wbqc-violation-message-format-clarification: Q1, ' .
			'ftp://mirror.example/, <code>https?://[^/]+/.*</code>, clarification)';
		$this->assertSame( $expected, $rendered );
	}

	/**
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\MultilingualTextViolationMessageRenderer::render
	 */
	public function testRender_multilingualText_fallback() {
		$messageKey = 'wbqc-violation-message-format-clarification';
		$monolingualText1 = new MonolingualTextValue( 'en', 'clarification' );
		$monolingualText2 = new MonolingualTextValue( 'de', 'Erklärung' );
		$multilingualText = new MultilingualTextValue( [ $monolingualText1, $monolingualText2 ] );
		$message = ( new ViolationMessage( $messageKey ) )
			->withEntityId( new ItemId( 'Q1' ) )
			->withDataValue( new StringValue( 'ftp://mirror.example/' ) )
			->withInlineCode( 'https?://[^/]+/.*' )
			->withMultilingualText( $multilingualText );
		$renderer = $this->newMultilingualTextViolationMessageRenderer( null, new StringFormatter() );

		$this->setMwGlobals( [ 'wgLang' => Language::factory( 'de-at' ) ] );
		$rendered = $renderer->render( $message );

		$expected = '(wbqc-violation-message-format-clarification: Q1, ' .
			'ftp://mirror.example/, <code>https?://[^/]+/.*</code>, Erklärung)';
		$this->assertSame( $expected, $rendered );
	}

	/**
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\MultilingualTextViolationMessageRenderer::render
	 */
	public function testRender_multilingualText_noFallback() {
		$messageKey = 'wbqc-violation-message-format-clarification';
		$alternativeMessageKey = 'wbqc-violation-message-format';
		$monolingualText = new MonolingualTextValue( 'de', 'Erklärung' );
		$multilingualText = new MultilingualTextValue( [ $monolingualText ] );
		$message = ( new ViolationMessage( $messageKey ) )
			->withEntityId( new ItemId( 'Q1' ) )
			->withDataValue( new StringValue( 'ftp://mirror.example/' ) )
			->withInlineCode( 'https?://[^/]+/.*' )
			->withMultilingualText( $multilingualText );
		$renderer = $this->newMultilingualTextViolationMessageRenderer( null, new StringFormatter() );

		$this->setMwGlobals( [ 'wgLang' => Language::factory( 'pt' ) ] );
		$rendered = $renderer->render( $message );

		$expected = '(wbqc-violation-message-format: Q1, ' .
			'ftp://mirror.example/, <code>https?://[^/]+/.*</code>)';
		$this->assertSame( $expected, $rendered );
	}

	/**
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\MultilingualTextViolationMessageRenderer::renderMultilingualText
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\MultilingualTextViolationMessageRenderer::addRole
	 */
	public function testRenderMultilingualText_English() {
		$text = new MultilingualTextValue( [ new MonolingualTextValue( 'en', 'explanation' ) ] );
		$role = null;
		$renderer = $this->newMultilingualTextViolationMessageRenderer( null, new StringFormatter() );

		$this->setMwGlobals( [ 'wgLang' => Language::factory( 'en' ) ] );
		$params = TestingAccessWrapper::newFromObject( $renderer )
			->renderMultilingualText( $text, $role );

		$this->assertSame(
			[ Message::rawParam( 'explanation' ) ],
			$params
		);
	}

	/**
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\MultilingualTextViolationMessageRenderer::renderMultilingualText
	 */
	public function testRenderMultilingualText_German() {
		$text = new MultilingualTextValue( [ new MonolingualTextValue( 'de', 'Erklärung' ) ] );
		$role = null;
		$renderer = $this->newMultilingualTextViolationMessageRenderer( null, new StringFormatter() );

		$this->setMwGlobals( [ 'wgLang' => Language::factory( 'de' ) ] );
		$params = TestingAccessWrapper::newFromObject( $renderer )
			->renderMultilingualText( $text, $role );

		$this->assertSame(
			[ Message::rawParam( 'Erklärung' ) ],
			$params
		);
	}

	/**
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\MultilingualTextViolationMessageRenderer::renderMultilingualText
	 */
	public function testRenderMultilingualText_AustrianGermanFallback() {
		$text = new MultilingualTextValue( [ new MonolingualTextValue( 'de', 'Erklärung' ) ] );
		$role = null;
		$renderer = $this->newMultilingualTextViolationMessageRenderer( null, new StringFormatter() );

		$this->setMwGlobals( [ 'wgLang' => Language::factory( 'de-at' ) ] );
		$params = TestingAccessWrapper::newFromObject( $renderer )
			->renderMultilingualText( $text, $role );

		$this->assertSame(
			[ Message::rawParam( 'Erklärung' ) ],
			$params
		);
	}

	/**
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\MultilingualTextViolationMessageRenderer::renderMultilingualText
	 */
	public function testRenderMultilingualText_KlingonFallback() {
		$text = new MultilingualTextValue( [ new MonolingualTextValue( 'en', 'explanation' ) ] );
		$role = null;
		$renderer = $this->newMultilingualTextViolationMessageRenderer( null, new StringFormatter() );

		$this->setMwGlobals( [ 'wgLang' => Language::factory( 'tlh' ) ] );
		$params = TestingAccessWrapper::newFromObject( $renderer )
			->renderMultilingualText( $text, $role );

		$this->assertSame(
			[ Message::rawParam( 'explanation' ) ],
			$params
		);
	}

	/**
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\MultilingualTextViolationMessageRenderer::renderMultilingualText
	 */
	public function testRenderMultilingualText_multipleLanguages() {
		$text = new MultilingualTextValue( [
			new MonolingualTextValue( 'en', 'explanation' ),
			new MonolingualTextValue( 'de', 'Erklärung' ),
			new MonolingualTextValue( 'pt', 'explicação' ),
		] );
		$role = null;
		$renderer = $this->newMultilingualTextViolationMessageRenderer( null, new StringFormatter() );

		$this->setMwGlobals( [ 'wgLang' => Language::factory( 'pt' ) ] );
		$params = TestingAccessWrapper::newFromObject( $renderer )
			->renderMultilingualText( $text, $role );

		$this->assertSame(
			[ Message::rawParam( 'explicação' ) ],
			$params
		);
	}

	/**
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\MultilingualTextViolationMessageRenderer::renderMultilingualText
	 */
	public function testRenderMultilingualText_noFallback() {
		$text = new MultilingualTextValue( [ new MonolingualTextValue( 'de', 'Erklärung' ) ] );
		$role = null;
		$renderer = $this->newMultilingualTextViolationMessageRenderer( null, new StringFormatter() );

		$this->setMwGlobals( [ 'wgLang' => Language::factory( 'pt' ) ] );
		$params = TestingAccessWrapper::newFromObject( $renderer )
			->renderMultilingualText( $text, $role );

		$this->assertNull( $params );
	}

	/**
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\MultilingualTextViolationMessageRenderer::renderMultilingualText
	 */
	public function testRenderMultilingualText_noLanguages() {
		$text = new MultilingualTextValue( [] );
		$role = null;
		$renderer = $this->newMultilingualTextViolationMessageRenderer( null, new StringFormatter() );

		$params = TestingAccessWrapper::newFromObject( $renderer )
			->renderMultilingualText( $text, $role );

		$this->assertNull( $params );
	}

	/**
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\MultilingualTextViolationMessageRenderer::renderMultilingualText
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\MultilingualTextViolationMessageRenderer::addRole
	 */
	public function testRenderMultilingualText_withRole() {
		$text = new MultilingualTextValue( [ new MonolingualTextValue( 'en', 'explanation' ) ] );
		$role = Role::CONSTRAINT_PARAMETER_VALUE;
		$renderer = $this->newMultilingualTextViolationMessageRenderer( null, new StringFormatter() );

		$this->setMwGlobals( [ 'wgLang' => Language::factory( 'en' ) ] );
		$params = TestingAccessWrapper::newFromObject( $renderer )
			->renderMultilingualText( $text, $role );

		$this->assertSame(
			[ Message::rawParam(
				'<span class="wbqc-role wbqc-role-constraint-parameter-value">' .
					'explanation' .
					'</span>'
			) ],
			$params
		);
	}

}
