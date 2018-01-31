<?php

namespace WikibaseQuality\ConstraintReport\Tests\Message;

use Message;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\EntityId\EntityIdFormatter;
use Wikibase\DataModel\Services\EntityId\PlainEntityIdFormatter;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessage;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageRenderer;
use WikibaseQuality\ConstraintReport\Role;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageRenderer
 *
 * @group WikibaseQualityConstraints
 *
 * @author Lucas Werkmeister
 * @license GNU GPL v2+
 */
class ViolationMessageRendererTest extends \PHPUnit_Framework_TestCase {

	public function testRender_string() {
		$message = 'A <em>pre-rendered</em> message.';
		$renderer = new ViolationMessageRenderer( new PlainEntityIdFormatter() );

		$rendered = $renderer->render( $message );

		$this->assertSame( $message, $rendered );
	}

	public function testRender_simpleMessage() {
		$messageKey = 'wbqc-violation-message-single-value';
		$message = new ViolationMessage( $messageKey );
		$renderer = new ViolationMessageRenderer( new PlainEntityIdFormatter() );

		$rendered = $renderer->render( $message );

		$this->assertSame( wfMessage( $messageKey )->escaped(), $rendered );
	}

	public function testRender_entityId() {
		$messageKey = 'wbqc-violation-message-no-qualifiers';
		$entityId = new PropertyId( 'P1' );
		$message = ( new ViolationMessage( $messageKey ) )
			->withEntityId( $entityId );
		$renderer = new ViolationMessageRenderer( new PlainEntityIdFormatter() );

		$rendered = $renderer->render( $message );

		$expected = wfMessage( $messageKey )
			->rawParams( 'P1' )
			->escaped();
		$this->assertSame( $expected, $rendered );
	}

	public function testRender_entityIdList() {
		$messageKey = 'wbqc-violation-message-unique-value';
		$entityIdList = [ new ItemId( 'Q1' ), new PropertyId( 'P2' ) ];
		$message = ( new ViolationMessage( $messageKey ) )
			->withEntityIdList( $entityIdList );
		$renderer = new ViolationMessageRenderer( new PlainEntityIdFormatter() );

		$rendered = $renderer->render( $message );

		$expected = wfMessage( $messageKey )
			->numParams( 2 )
			->rawParams( '<ul><li>Q1</li><li>P2</li></ul>', 'Q1', 'P2' )
			->escaped();
		$this->assertSame( $expected, $rendered );
	}

	public function testRenderEntityId() {
		$entityId = new ItemId( 'Q1' );
		$role = null;
		$entityIdFormatter = $this->getMock( EntityIdFormatter::class );
		$entityIdFormatter->expects( $this->once() )
			->method( 'formatEntityId' )
			->with( $entityId )
			->willReturn( '<test property>' );
		$renderer = new ViolationMessageRenderer( $entityIdFormatter );

		$params = TestingAccessWrapper::newFromObject( $renderer )
			->renderEntityId( $entityId, $role );

		$this->assertSame(
			Message::rawParam( '<test property>' ),
			$params
		);
	}

	public function testRenderEntityId_withRole() {
		$entityId = new PropertyId( 'P1' );
		$role = Role::PREDICATE;
		$entityIdFormatter = $this->getMock( EntityIdFormatter::class );
		$entityIdFormatter
			->method( 'formatEntityId' )
			->willReturn( '<test property>' );
		$renderer = new ViolationMessageRenderer( $entityIdFormatter );

		$params = TestingAccessWrapper::newFromObject( $renderer )
			->renderEntityId( $entityId, $role );

		$this->assertSame(
			Message::rawParam( '<span class="wbqc-role wbqc-role-predicate"><test property></span>' ),
			$params
		);
	}

	public function testRenderEntityIdList() {
		$entityIdList = [ new ItemId( 'Q1' ), new PropertyId( 'P2' ) ];
		$role = null;
		$entityIdFormatter = $this->getMock( EntityIdFormatter::class );
		$entityIdFormatter->expects( $this->exactly( 2 ) )
			->method( 'formatEntityId' )
			->willReturnCallback( [ new PlainEntityIdFormatter(), 'formatEntityId' ] );
		$renderer = new ViolationMessageRenderer( $entityIdFormatter );

		$params = TestingAccessWrapper::newFromObject( $renderer )
			->renderEntityIdList( $entityIdList, $role );

		$this->assertSame(
			[
				Message::numParam( 2 ),
				Message::rawParam( '<ul><li>Q1</li><li>P2</li></ul>' ),
				Message::rawParam( 'Q1' ),
				Message::rawParam( 'P2' ),
			],
			$params
		);
	}

	public function testRenderEntityIdList_empty() {
		$entityIdList = [];
		$role = null;
		$entityIdFormatter = $this->getMock( EntityIdFormatter::class );
		$entityIdFormatter->expects( $this->never() )
			->method( 'formatEntityId' );
		$renderer = new ViolationMessageRenderer( $entityIdFormatter );

		$params = TestingAccessWrapper::newFromObject( $renderer )
			->renderEntityIdList( $entityIdList, $role );

		$this->assertSame(
			[
				Message::numParam( 0 ),
				Message::rawParam( '<ul></ul>' ),
			],
			$params
		);
	}

	public function testRenderEntityIdList_tooLong() {
		$entityIdList = [ new ItemId( 'Q1' ), new PropertyId( 'P2' ), new ItemId( 'Q3' ) ];
		$role = null;
		$renderer = new ViolationMessageRenderer(
			new PlainEntityIdFormatter(),
			2
		);

		$params = TestingAccessWrapper::newFromObject( $renderer )
			->renderEntityIdList( $entityIdList, $role );

		$this->assertSame(
			[
				Message::numParam( 2 ),
				Message::rawParam( '<ul><li>Q1</li><li>P2</li><li>...</li></ul>' ),
				Message::rawParam( 'Q1' ),
				Message::rawParam( 'P2' ),
			],
			$params
		);
	}

	public function testRenderEntityIdList_withRole() {
		$entityIdList = [ new ItemId( 'Q1' ) ];
		$role = Role::OBJECT;
		$entityIdFormatter = $this->getMock( EntityIdFormatter::class );
		$entityIdFormatter
			->method( 'formatEntityId' )
			->willReturn( '<test item>' );
		$renderer = new ViolationMessageRenderer( $entityIdFormatter );

		$params = TestingAccessWrapper::newFromObject( $renderer )
			->renderEntityIdList( $entityIdList, $role );

		$this->assertSame(
			[
				Message::numParam( 1 ),
				Message::rawParam( '<ul><li><span class="wbqc-role wbqc-role-object"><test item></span></li></ul>' ),
				Message::rawParam( '<span class="wbqc-role wbqc-role-object"><test item></span>' ),
			],
			$params
		);
	}

}
