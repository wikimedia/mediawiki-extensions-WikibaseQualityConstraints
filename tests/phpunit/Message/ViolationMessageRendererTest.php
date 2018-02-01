<?php

namespace WikibaseQuality\ConstraintReport\Tests\Message;

use DataValues\StringValue;
use InvalidArgumentException;
use Message;
use ValueFormatters\StringFormatter;
use ValueFormatters\ValueFormatter;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\EntityId\EntityIdFormatter;
use Wikibase\DataModel\Services\EntityId\PlainEntityIdFormatter;
use Wikibase\Lib\UnDeserializableValueFormatter;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ItemIdSnakValue;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessage;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageRenderer;
use WikibaseQuality\ConstraintReport\Role;
use Wikimedia\TestingAccessWrapper;

/**
 * @group WikibaseQualityConstraints
 *
 * @author Lucas Werkmeister
 * @license GNU GPL v2+
 */
class ViolationMessageRendererTest extends \PHPUnit_Framework_TestCase {

	/**
	 * Create a new ViolationMessageRenderer
	 * with some constructor arguments defaulting to a simple base implementation.
	 *
	 * @param EntityIdFormatter|null $entityIdFormatter
	 * @param ValueFormatter|null $dataValueFormatter
	 * @return ViolationMessageRenderer
	 */
	private function newViolationMessageRenderer(
		EntityIdFormatter $entityIdFormatter = null,
		ValueFormatter $dataValueFormatter = null,
		$maxListLength = 10
	) {
		if ( $entityIdFormatter === null ) {
			$entityIdFormatter = new PlainEntityIdFormatter();
		}
		if ( $dataValueFormatter === null ) {
			$dataValueFormatter = new UnDeserializableValueFormatter();
		}
		return new ViolationMessageRenderer(
			$entityIdFormatter,
			$dataValueFormatter,
			$maxListLength
		);
	}

	/**
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageRenderer::__construct
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageRenderer::render
	 */
	public function testRender_string() {
		$message = 'A <em>pre-rendered</em> message.';
		$renderer = $this->newViolationMessageRenderer();

		$rendered = $renderer->render( $message );

		$this->assertSame( $message, $rendered );
	}

	/**
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageRenderer::render
	 */
	public function testRender_simpleMessage() {
		$messageKey = 'wbqc-violation-message-single-value';
		$message = new ViolationMessage( $messageKey );
		$renderer = $this->newViolationMessageRenderer();

		$rendered = $renderer->render( $message );

		$this->assertSame( wfMessage( $messageKey )->escaped(), $rendered );
	}

	/**
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageRenderer::render
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageRenderer::renderArgument
	 */
	public function testRender_entityId() {
		$messageKey = 'wbqc-violation-message-no-qualifiers';
		$entityId = new PropertyId( 'P1' );
		$message = ( new ViolationMessage( $messageKey ) )
			->withEntityId( $entityId );
		$renderer = $this->newViolationMessageRenderer();

		$rendered = $renderer->render( $message );

		$expected = wfMessage( $messageKey )
			->rawParams( 'P1' )
			->escaped();
		$this->assertSame( $expected, $rendered );
	}

	/**
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageRenderer::render
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageRenderer::renderArgument
	 */
	public function testRender_entityIdList() {
		$messageKey = 'wbqc-violation-message-unique-value';
		$entityIdList = [ new ItemId( 'Q1' ), new PropertyId( 'P2' ) ];
		$message = ( new ViolationMessage( $messageKey ) )
			->withEntityIdList( $entityIdList );
		$renderer = $this->newViolationMessageRenderer();

		$rendered = $renderer->render( $message );

		$expected = wfMessage( $messageKey )
			->numParams( 2 )
			->rawParams( '<ul><li>Q1</li><li>P2</li></ul>', 'Q1', 'P2' )
			->escaped();
		$this->assertSame( $expected, $rendered );
	}

	/**
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageRenderer::render
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageRenderer::renderArgument
	 */
	public function testRender_itemIdSnakValue() {
		$messageKey = 'wbqc-violation-message-conflicts-with-claim';
		$itemIdSnakValue = ItemIdSnakValue::someValue();
		$message = ( new ViolationMessage( $messageKey ) )
			->withEntityId( new PropertyId( 'P1' ) )
			->withEntityId( new PropertyId( 'P2' ) )
			->withItemIdSnakValue( $itemIdSnakValue );
		$renderer = $this->newViolationMessageRenderer();

		$rendered = $renderer->render( $message );

		$expected = wfMessage( $messageKey )
			->rawParams(
				'P1',
				'P2',
				'<span class="wikibase-snakview-variation-somevaluesnak">' .
					wfMessage( 'wikibase-snakview-snaktypeselector-somevalue' )->escaped() .
					'</span>'
			)
			->escaped();
		$this->assertSame( $expected, $rendered );
	}

	/**
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageRenderer::render
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageRenderer::renderArgument
	 */
	public function testRender_itemIdSnakValueList() {
		$messageKey = 'wbqc-violation-message-one-of';
		$valueList = [ ItemIdSnakValue::fromItemId( new ItemId( 'Q1' ) ) ];
		$message = ( new ViolationMessage( $messageKey ) )
			->withEntityId( new PropertyId( 'P1' ) )
			->withItemIdSnakValueList( $valueList );
		$renderer = $this->newViolationMessageRenderer();

		$rendered = $renderer->render( $message );

		$expected = wfMessage( $messageKey )
			->rawParams( 'P1' )
			->numParams( 1 )
			->rawParams( '<ul><li>Q1</li></ul>', 'Q1' )
			->escaped();
		$this->assertSame( $expected, $rendered );
	}

	/**
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageRenderer::render
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageRenderer::renderArgument
	 */
	public function testRender_dataValue() {
		$messageKey = 'wbqc-violation-message-range-quantity-rightopen';
		$dataValue = new StringValue( 'a string' );
		$message = ( new ViolationMessage( $messageKey ) )
			->withEntityId( new PropertyId( 'P1' ) )
			->withDataValue( $dataValue )
			->withDataValue( $dataValue );
		$renderer = $this->newViolationMessageRenderer( null, new StringFormatter() );

		$rendered = $renderer->render( $message );

		$expected = wfMessage( $messageKey )
			->rawParams( 'P1', 'a string', 'a string' )
			->escaped();
		$this->assertSame( $expected, $rendered );
	}

	/**
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageRenderer::render
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageRenderer::renderArgument
	 */
	public function testRender_dataValueType() {
		$messageKey = 'wbqc-violation-message-value-needed-of-type';
		$dataValueType = 'string';
		$message = ( new ViolationMessage( $messageKey ) )
			->withEntityId( new ItemId( 'Q1' ) )
			->withDataValueType( $dataValueType );
		$renderer = $this->newViolationMessageRenderer();

		$rendered = $renderer->render( $message );

		$expected = wfMessage( $messageKey )
			->rawParams( 'Q1', wfMessage( 'datatypes-type-string' )->escaped() )
			->escaped();
		$this->assertSame( $expected, $rendered );
	}

	/**
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageRenderer::render
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageRenderer::renderArgument
	 */
	public function testRender_unknownArgumentType() {
		$message = $this->getMockBuilder( ViolationMessage::class )
			->disableOriginalConstructor()
			->getMock();
		$message->method( 'getArguments' )
			->willReturn( [ [ 'type' => 'unknown', 'value' => null, 'role' => null ] ] );
		$renderer = $this->newViolationMessageRenderer();

		$this->setExpectedException( InvalidArgumentException::class );
		$renderer->render( $message );
	}

	/**
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageRenderer::renderList
	 */
	public function testRenderList() {
		$valueList = [ '<any value>', new StringValue( 'any kind of value' ) ];
		$role = null;
		$renderer = $this->newViolationMessageRenderer();
		$renderMock = $this->getMockBuilder( \stdClass::class )
			->setMethods( [ 'render' ] )
			->getMock();
		$renderMock->expects( $this->exactly( 2 ) )
			->method( 'render' )
			->withConsecutive( [ $valueList[0], $role ], [ $valueList[1], $role ] )
			->willReturnCallback( function ( $value, $role ) {
				if ( $value instanceof StringValue ) {
					return Message::rawParam( $value->getValue() );
				} else {
					return Message::rawParam( $value );
				}
			} );
		$renderFunction = [ $renderMock, 'render' ];

		$params = TestingAccessWrapper::newFromObject( $renderer )
			->renderList( $valueList, $role, $renderFunction );

		$this->assertSame(
			[
				Message::numParam( 2 ),
				Message::rawParam( '<ul><li><any value></li><li>any kind of value</li></ul>' ),
				Message::rawParam( '<any value>' ),
				Message::rawParam( 'any kind of value' ),
			],
			$params
		);
	}

	/**
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageRenderer::renderList
	 */
	public function testRenderList_empty() {
		$valueList = [];
		$role = null;
		$renderer = $this->newViolationMessageRenderer();
		$renderMock = $this->getMockBuilder( \stdClass::class )
			->setMethods( [ 'render' ] )
			->getMock();
		$renderMock->expects( $this->never() )
			->method( 'render' );
		$renderFunction = [ $renderMock, 'render' ];

		$params = TestingAccessWrapper::newFromObject( $renderer )
			->renderList( $valueList, $role, $renderFunction );

		$this->assertSame(
			[
				Message::numParam( 0 ),
				Message::rawParam( '<ul></ul>' ),
			],
			$params
		);
	}

	/**
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageRenderer::renderList
	 */
	public function testRenderList_tooLong() {
		$valueList = [ 'Q1', 'P2', 'Q3' ];
		$role = null;
		$renderer = $this->newViolationMessageRenderer( null, null, 2 );
		$renderMock = $this->getMockBuilder( \stdClass::class )
			->setMethods( [ 'render' ] )
			->getMock();
		$renderMock->expects( $this->exactly( 2 ) )
			->method( 'render' )
			->withConsecutive( [ $valueList[0], $role ], [ $valueList[1], $role ] )
			->willReturnCallback( function ( $value, $role ) {
				return Message::rawParam( $value );
			} );
		$renderFunction = [ $renderMock, 'render' ];

		$params = TestingAccessWrapper::newFromObject( $renderer )
			->renderList( $valueList, $role, $renderFunction );

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

	/**
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageRenderer::renderList
	 */
	public function testRenderList_withRole() {
		$valueList = [ '<test item>' ];
		$role = Role::OBJECT;
		$renderer = $this->newViolationMessageRenderer();
		$renderMock = $this->getMockBuilder( \stdClass::class )
			->setMethods( [ 'render' ] )
			->getMock();
		$renderMock->expects( $this->once() )
			->method( 'render' )
			->with( $valueList[0], $role )
			->willReturn( Message::rawParam(
				'<span class="wbqc-role wbqc-role-object">' . $valueList[0] . '</span>'
			) );
		$renderFunction = [ $renderMock, 'render' ];

		$params = TestingAccessWrapper::newFromObject( $renderer )
			->renderList( $valueList, $role, $renderFunction );

		$this->assertSame(
			[
				Message::numParam( 1 ),
				Message::rawParam( '<ul><li><span class="wbqc-role wbqc-role-object"><test item></span></li></ul>' ),
				Message::rawParam( '<span class="wbqc-role wbqc-role-object"><test item></span>' ),
			],
			$params
		);
	}

	/**
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageRenderer::renderEntityId
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageRenderer::addRole
	 */
	public function testRenderEntityId() {
		$entityId = new ItemId( 'Q1' );
		$role = null;
		$entityIdFormatter = $this->getMock( EntityIdFormatter::class );
		$entityIdFormatter->expects( $this->once() )
			->method( 'formatEntityId' )
			->with( $entityId )
			->willReturn( '<test property>' );
		$renderer = $this->newViolationMessageRenderer( $entityIdFormatter );

		$params = TestingAccessWrapper::newFromObject( $renderer )
			->renderEntityId( $entityId, $role );

		$this->assertSame(
			Message::rawParam( '<test property>' ),
			$params
		);
	}

	/**
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageRenderer::renderEntityId
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageRenderer::addRole
	 */
	public function testRenderEntityId_withRole() {
		$entityId = new PropertyId( 'P1' );
		$role = Role::PREDICATE;
		$entityIdFormatter = $this->getMock( EntityIdFormatter::class );
		$entityIdFormatter
			->method( 'formatEntityId' )
			->willReturn( '<test property>' );
		$renderer = $this->newViolationMessageRenderer( $entityIdFormatter );

		$params = TestingAccessWrapper::newFromObject( $renderer )
			->renderEntityId( $entityId, $role );

		$this->assertSame(
			Message::rawParam( '<span class="wbqc-role wbqc-role-predicate"><test property></span>' ),
			$params
		);
	}

	/**
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageRenderer::renderEntityIdList
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageRenderer::addRole
	 */
	public function testRenderEntityIdList() {
		$entityIdList = [ new ItemId( 'Q1' ), new PropertyId( 'P2' ) ];
		$role = null;
		$entityIdFormatter = $this->getMock( EntityIdFormatter::class );
		$entityIdFormatter->expects( $this->exactly( 2 ) )
			->method( 'formatEntityId' )
			->willReturnCallback( [ new PlainEntityIdFormatter(), 'formatEntityId' ] );
		$renderer = $this->newViolationMessageRenderer( $entityIdFormatter );

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

	/**
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageRenderer::renderEntityIdList
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageRenderer::addRole
	 */
	public function testRenderEntityIdList_withRole() {
		$entityIdList = [ new ItemId( 'Q1' ) ];
		$role = Role::OBJECT;
		$entityIdFormatter = $this->getMock( EntityIdFormatter::class );
		$entityIdFormatter
			->method( 'formatEntityId' )
			->willReturn( '<test item>' );
		$renderer = $this->newViolationMessageRenderer( $entityIdFormatter );

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

	/**
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageRenderer::renderItemIdSnakValue
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageRenderer::addRole
	 */
	public function testRenderItemIdSnakValue_itemId() {
		$itemId = new ItemId( 'Q1' );
		$itemIdSnakValue = ItemIdSnakValue::fromItemId( $itemId );
		$role = null;
		$entityIdFormatter = $this->getMock( EntityIdFormatter::class );
		$entityIdFormatter->expects( $this->once() )
			->method( 'formatEntityId' )
			->with( $itemId )
			->willReturn( '<test item>' );
		$renderer = $this->newViolationMessageRenderer( $entityIdFormatter );

		$params = TestingAccessWrapper::newFromObject( $renderer )
			->renderItemIdSnakValue( $itemIdSnakValue, $role );

		$this->assertSame(
			Message::rawParam( '<test item>' ),
			$params
		);
	}

	/**
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageRenderer::renderItemIdSnakValue
	 */
	public function testRenderItemIdSnakValue_someValue() {
		$itemIdSnakValue = ItemIdSnakValue::someValue();
		$role = null;
		$entityIdFormatter = $this->getMock( EntityIdFormatter::class );
		$entityIdFormatter
			->expects( $this->never() )
			->method( 'formatEntityId' );
		$renderer = $this->newViolationMessageRenderer( $entityIdFormatter );

		$params = TestingAccessWrapper::newFromObject( $renderer )
			->renderItemIdSnakValue( $itemIdSnakValue, $role );

		$this->assertSame(
			Message::rawParam(
				'<span class="wikibase-snakview-variation-somevaluesnak">' .
					wfMessage( 'wikibase-snakview-snaktypeselector-somevalue' )->escaped() .
					'</span>'
			),
			$params
		);
	}

	/**
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageRenderer::renderItemIdSnakValue
	 */
	public function testRenderItemIdSnakValue_noValue() {
		$itemIdSnakValue = ItemIdSnakValue::noValue();
		$role = null;
		$entityIdFormatter = $this->getMock( EntityIdFormatter::class );
		$entityIdFormatter
			->expects( $this->never() )
			->method( 'formatEntityId' );
		$renderer = $this->newViolationMessageRenderer( $entityIdFormatter );

		$params = TestingAccessWrapper::newFromObject( $renderer )
			->renderItemIdSnakValue( $itemIdSnakValue, $role );

		$this->assertSame(
			Message::rawParam(
				'<span class="wikibase-snakview-variation-novaluesnak">' .
				wfMessage( 'wikibase-snakview-snaktypeselector-novalue' )->escaped() .
				'</span>'
			),
			$params
		);
	}

	/**
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageRenderer::renderItemIdSnakValue
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageRenderer::addRole
	 */
	public function testRenderItemIdSnakValue_withRole() {
		$itemId = new ItemId( 'Q1' );
		$itemIdSnakValue = ItemIdSnakValue::fromItemId( $itemId );
		$role = Role::OBJECT;
		$entityIdFormatter = $this->getMock( EntityIdFormatter::class );
		$entityIdFormatter
			->method( 'formatEntityId' )
			->willReturn( '<test item>' );
		$renderer = $this->newViolationMessageRenderer( $entityIdFormatter );

		$params = TestingAccessWrapper::newFromObject( $renderer )
			->renderItemIdSnakValue( $itemIdSnakValue, $role );

		$this->assertSame(
			Message::rawParam( '<span class="wbqc-role wbqc-role-object"><test item></span>' ),
			$params
		);
	}

	/**
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageRenderer::renderItemIdSnakValueList
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageRenderer::addRole
	 */
	public function testRenderItemIdSnakValueList() {
		$valueList = [
			ItemIdSnakValue::fromItemId( new ItemId( 'Q1' ) ),
			ItemIdSnakValue::fromItemId( new ItemId( 'Q2' ) ),
		];
		$role = null;
		$entityIdFormatter = $this->getMock( EntityIdFormatter::class );
		$entityIdFormatter->expects( $this->exactly( 2 ) )
			->method( 'formatEntityId' )
			->willReturnCallback( [ new PlainEntityIdFormatter(), 'formatEntityId' ] );
		$renderer = $this->newViolationMessageRenderer( $entityIdFormatter );

		$params = TestingAccessWrapper::newFromObject( $renderer )
			->renderItemIdSnakValueList( $valueList, $role );

		$this->assertSame(
			[
				Message::numParam( 2 ),
				Message::rawParam( '<ul><li>Q1</li><li>Q2</li></ul>' ),
				Message::rawParam( 'Q1' ),
				Message::rawParam( 'Q2' ),
			],
			$params
		);
	}

	/**
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageRenderer::renderItemIdSnakValueList
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageRenderer::addRole
	 */
	public function testRenderItemIdSnakValueList_withRole() {
		$valueList = [ ItemIdSnakValue::fromItemId( new ItemId( 'Q1' ) ) ];
		$role = Role::OBJECT;
		$entityIdFormatter = $this->getMock( EntityIdFormatter::class );
		$entityIdFormatter
			->method( 'formatEntityId' )
			->willReturn( '<test item>' );
		$renderer = $this->newViolationMessageRenderer( $entityIdFormatter );

		$params = TestingAccessWrapper::newFromObject( $renderer )
			->renderItemIdSnakValueList( $valueList, $role );

		$this->assertSame(
			[
				Message::numParam( 1 ),
				Message::rawParam( '<ul><li><span class="wbqc-role wbqc-role-object"><test item></span></li></ul>' ),
				Message::rawParam( '<span class="wbqc-role wbqc-role-object"><test item></span>' ),
			],
			$params
		);
	}

	/**
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageRenderer::renderDataValue
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageRenderer::addRole
	 */
	public function testRenderDataValue() {
		$dataValue = new StringValue( 'a&nbsp;string' );
		$role = null;
		$dataValueFormatter = $this->getMock( ValueFormatter::class );
		$dataValueFormatter->expects( $this->once() )
			->method( 'format' )
			->with( $dataValue )
			->willReturn( '<a&amp;nbsp;string>' );
		$renderer = $this->newViolationMessageRenderer( null, $dataValueFormatter );

		$params = TestingAccessWrapper::newFromObject( $renderer )
			->renderDataValue( $dataValue, $role );

		$this->assertSame(
			Message::rawParam( '<a&amp;nbsp;string>' ),
			$params
		);
	}

	/**
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageRenderer::renderDataValue
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageRenderer::addRole
	 */
	public function testRenderDataValue_withRole() {
		$dataValue = new StringValue( 'test' );
		$role = Role::OBJECT;
		$dataValueFormatter = $this->getMock( ValueFormatter::class );
		$dataValueFormatter
			->method( 'format' )
			->willReturn( 'test' );
		$renderer = $this->newViolationMessageRenderer( null, $dataValueFormatter );

		$params = TestingAccessWrapper::newFromObject( $renderer )
			->renderDataValue( $dataValue, $role );

		$this->assertSame(
			Message::rawParam( '<span class="wbqc-role wbqc-role-object">test</span>' ),
			$params
		);
	}

	/**
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageRenderer::renderDataValueType
	 */
	public function testRenderDataValueType_string() {
		$dataValueType = 'string';
		$role = null;
		$renderer = $this->newViolationMessageRenderer();

		$params = TestingAccessWrapper::newFromObject( $renderer )
			->renderDataValueType( $dataValueType, $role );

		$this->assertSame(
			Message::rawParam( wfMessage( 'datatypes-type-string' )->escaped() ),
			$params
		);
	}

	/**
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageRenderer::renderDataValueType
	 */
	public function testRenderDataValueType_entityId() {
		$dataValueType = 'wikibase-entityid';
		$role = null;
		$renderer = $this->newViolationMessageRenderer();

		$params = TestingAccessWrapper::newFromObject( $renderer )
			->renderDataValueType( $dataValueType, $role );

		$this->assertSame(
			Message::rawParam( wfMessage( 'wbqc-dataValueType-wikibase-entityid' )->escaped() ),
			$params
		);
	}

}
