<?php

namespace WikibaseQuality\ConstraintReport\Tests;

use DataValues\StringValue;
use HashConfig;
use ValueFormatters\StringFormatter;
use ValueFormatters\ValueFormatter;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\EntityId\EntityIdFormatter;
use Wikibase\DataModel\Services\EntityId\PlainEntityIdFormatter;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\Context;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ItemIdSnakValue;
use WikibaseQuality\ConstraintReport\ConstraintParameterRenderer;
use WikibaseQuality\ConstraintReport\Role;

/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintParameterRenderer
 *
 * @group WikibaseQualityConstraints
 *
 * @author Lucas Werkmeister
 * @license GNU GPL v2+
 */
class ConstraintParameterRendererTest extends \PHPUnit_Framework_TestCase {

	use DefaultConfig;

	public function testFormatByRole_Null() {
		$role = null;
		$value = 'foo <bar "&quot;baz';

		$formatted = ConstraintParameterRenderer::formatByRole( $role, $value );

		$this->assertSame( $value, $formatted );
	}

	public function testFormatByRole_Subject() {
		$role = Role::SUBJECT;
		$value = 'foo <bar "&quot;baz';

		$formatted = ConstraintParameterRenderer::formatByRole( $role, $value );

		$this->assertSame(
			'<span class="wbqc-role wbqc-role-subject">'. $value . '</span>',
			$formatted
		);
	}

	public function testFormatDataValue() {
		$role = Role::OBJECT;
		$value = new StringValue( 'a test string' );
		$valueFormatter = $this->getMock( ValueFormatter::class );
		$valueFormatter->expects( $this->once() )
			->method( 'format' )
			->with( $value )
			->willReturn( '<a test string>' );
		$constraintParameterRenderer = new ConstraintParameterRenderer(
			new PlainEntityIdFormatter(),
			$valueFormatter,
			$this->getDefaultConfig()
		);

		$formatted = $constraintParameterRenderer->formatDataValue( $value, $role );

		$this->assertSame(
			'<span class="wbqc-role wbqc-role-object">' .
				'<a test string>' .
			'</span>',
			$formatted
		);
	}

	public function testFormatEntityId() {
		$role = Role::PREDICATE;
		$value = new PropertyId( 'P1234' );
		$entityIdFormatter = $this->getMock( EntityIdFormatter::class );
		$entityIdFormatter->expects( $this->once() )
			->method( 'formatEntityId' )
			->with( $value )
			->willReturn( '<some property>' );
		$constraintParameterRenderer = new ConstraintParameterRenderer(
			$entityIdFormatter,
			new StringFormatter(),
			$this->getDefaultConfig()
		);

		$formatted = $constraintParameterRenderer->formatEntityId( $value, $role );

		$this->assertSame(
			'<span class="wbqc-role wbqc-role-predicate">' .
				'<some property>' .
			'</span>',
			$formatted
		);
	}

	public function testFormatPropertyId_PropertyId() {
		$value = new PropertyId( 'P1234' );
		$constraintParameterRenderer = $this->getMockBuilder( ConstraintParameterRenderer::class )
			->disableOriginalConstructor()
			->setMethods( [ 'formatEntityId' ] )
			->getMock();
		$constraintParameterRenderer->expects( $this->once() )
			->method( 'formatEntityId' )
			->with( $value )
			->willReturn( 'some property' );

		$formatted = $constraintParameterRenderer->formatPropertyId( $value );

		$this->assertSame( 'some property', $formatted );
	}

	public function testFormatPropertyId_PropertyIdSerialization() {
		$value = 'P1234';
		$constraintParameterRenderer = $this->getMockBuilder( ConstraintParameterRenderer::class )
			->disableOriginalConstructor()
			->setMethods( [ 'formatEntityId' ] )
			->getMock();
		$constraintParameterRenderer->expects( $this->once() )
			->method( 'formatEntityId' )
			->willReturnCallback( function ( PropertyId $propertyId ) use ( $value ) {
				$this->assertSame( $value, $propertyId->getSerialization() );
				return 'some property';
			} );

		$formatted = $constraintParameterRenderer->formatPropertyId( $value );

		$this->assertSame( 'some property', $formatted );
	}

	public function testFormatPropertyId_OtherString() {
		$value = 'some property';
		$constraintParameterRenderer = $this->getMockBuilder( ConstraintParameterRenderer::class )
			->disableOriginalConstructor()
			->setMethods( [ 'formatEntityId' ] )
			->getMock();
		$constraintParameterRenderer->expects( $this->never() )->method( 'formatEntityId' );

		$formatted = $constraintParameterRenderer->formatPropertyId( $value );

		$this->assertSame( 'some property', $formatted );
	}

	public function testFormatItemId_ItemId() {
		$value = new ItemId( 'Q1234' );
		$constraintParameterRenderer = $this->getMockBuilder( ConstraintParameterRenderer::class )
			->disableOriginalConstructor()
			->setMethods( [ 'formatEntityId' ] )
			->getMock();
		$constraintParameterRenderer->expects( $this->once() )
			->method( 'formatEntityId' )
			->with( $value )
			->willReturn( 'some item' );

		$formatted = $constraintParameterRenderer->formatItemId( $value );

		$this->assertSame( 'some item', $formatted );
	}

	public function testFormatItemId_ItemIdSerialization() {
		$value = 'Q1234';
		$constraintParameterRenderer = $this->getMockBuilder( ConstraintParameterRenderer::class )
			->disableOriginalConstructor()
			->setMethods( [ 'formatEntityId' ] )
			->getMock();
		$constraintParameterRenderer->expects( $this->once() )
			->method( 'formatEntityId' )
			->willReturnCallback( function ( ItemId $itemId ) use ( $value ) {
				$this->assertSame( $value, $itemId->getSerialization() );
				return 'some item';
			} );

		$formatted = $constraintParameterRenderer->formatItemId( $value );

		$this->assertSame( 'some item', $formatted );
	}

	public function testFormatItemId_OtherString() {
		$value = 'some item';
		$constraintParameterRenderer = $this->getMockBuilder( ConstraintParameterRenderer::class )
			->disableOriginalConstructor()
			->setMethods( [ 'formatEntityId' ] )
			->getMock();
		$constraintParameterRenderer->expects( $this->never() )->method( 'formatEntityId' );

		$formatted = $constraintParameterRenderer->formatItemId( $value );

		$this->assertSame( 'some item', $formatted );
	}

	public function testFormatItemIdSnakValue_Value() {
		$itemId = new ItemId( 'Q1234' );
		$value = ItemIdSnakValue::fromItemId( $itemId );
		$constraintParameterRenderer = $this->getMockBuilder( ConstraintParameterRenderer::class )
			->disableOriginalConstructor()
			->setMethods( [ 'formatEntityId' ] )
			->getMock();
		$constraintParameterRenderer->expects( $this->once() )
			->method( 'formatEntityId' )
			->with( $itemId )
			->willReturn( 'some item' );

		$formatted = $constraintParameterRenderer->formatItemIdSnakValue( $value );

		$this->assertSame( 'some item', $formatted );
	}

	public function testFormatItemIdSnakValue_SomeValue() {
		$value = ItemIdSnakValue::someValue();
		$constraintParameterRenderer = $this->getMockBuilder( ConstraintParameterRenderer::class )
			->disableOriginalConstructor()
			->setMethods( [ 'formatEntityId' ] )
			->getMock();
		$constraintParameterRenderer->expects( $this->never() )->method( 'formatEntityId' );

		$formatted = $constraintParameterRenderer->formatItemIdSnakValue( $value );

		$this->assertContains( 'somevalue', $formatted );
	}

	public function testFormatItemIdSnakValue_NoValue() {
		$value = ItemIdSnakValue::noValue();
		$constraintParameterRenderer = $this->getMockBuilder( ConstraintParameterRenderer::class )
			->disableOriginalConstructor()
			->setMethods( [ 'formatEntityId' ] )
			->getMock();
		$constraintParameterRenderer->expects( $this->never() )->method( 'formatEntityId' );

		$formatted = $constraintParameterRenderer->formatItemIdSnakValue( $value );

		$this->assertContains( 'novalue', $formatted );
	}

	/**
	 * @dataProvider provideConstraintScopes
	 */
	public function testFormatConstraintScope( $contextType, $itemIdSerialization, $returnValue ) {
		$constraintParameterRenderer = $this->getMockBuilder( ConstraintParameterRenderer::class )
			->setConstructorArgs( [
				new PlainEntityIdFormatter(),
				new StringFormatter(),
				new HashConfig( [
					'WBQualityConstraintsConstraintCheckedOnMainValueId' => 'Q1',
					'WBQualityConstraintsConstraintCheckedOnQualifiersId' => 'Q2',
					'WBQualityConstraintsConstraintCheckedOnReferencesId' => 'Q3',
				] )
			] )
			->setMethods( [ 'formatItemId' ] )
			->getMock();
		$constraintParameterRenderer->expects( $this->once() )
			->method( 'formatItemId' )
			->with( $itemIdSerialization, Role::CONSTRAINT_PARAMETER_VALUE )
			->willReturn( $returnValue );

		$formatted = $constraintParameterRenderer->formatConstraintScope(
			$contextType,
			Role::CONSTRAINT_PARAMETER_VALUE
		);

		$this->assertSame( $returnValue, $formatted );
	}

	public function provideConstraintScopes() {
		return [
			[ Context::TYPE_STATEMENT, 'Q1', 'statement scope' ],
			[ Context::TYPE_QUALIFIER, 'Q2', 'qualifier scope' ],
			[ Context::TYPE_REFERENCE, 'Q3', 'reference scope' ],
		];
	}

	public function testFormatPropertyIdList_Empty() {
		$constraintParameterRenderer = new ConstraintParameterRenderer(
			new PlainEntityIdFormatter(),
			new StringFormatter(),
			$this->getDefaultConfig()
		);

		$formatted = $constraintParameterRenderer->formatPropertyIdList( [] );

		$this->assertConstraintReportParameterList( [], $formatted );
	}

	public function testFormatPropertyIdList_TwoPropertyIds() {
		$constraintParameterRenderer = new ConstraintParameterRenderer(
			new PlainEntityIdFormatter(),
			new StringFormatter(),
			$this->getDefaultConfig()
		);

		$formatted = $constraintParameterRenderer->formatPropertyIdList( [
			new PropertyId( 'P1' ),
			new PropertyId( 'P2' ),
		] );

		$this->assertConstraintReportParameterList( [ 'P1', 'P2' ], $formatted );
	}

	public function testFormatPropertyIdList_TwentyPropertyIds() {
		$constraintParameterRenderer = new ConstraintParameterRenderer(
			new PlainEntityIdFormatter(),
			new StringFormatter(),
			$this->getDefaultConfig()
		);

		$formatted = $constraintParameterRenderer->formatPropertyIdList(
			array_map(
				function ( $i ) {
					return new PropertyId( 'P' . $i );
				},
				range( 1, 20 )
			)
		);

		$this->assertConstraintReportParameterList(
			[ 'P1', 'P2', 'P3', 'P4', 'P5', 'P6', 'P7', 'P8', 'P9', 'P10', '...' ],
			$formatted
		);
	}

	public function testFormatItemIdList_Empty() {
		$constraintParameterRenderer = new ConstraintParameterRenderer(
			new PlainEntityIdFormatter(),
			new StringFormatter(),
			$this->getDefaultConfig()
		);

		$formatted = $constraintParameterRenderer->formatItemIdList( [] );

		$this->assertConstraintReportParameterList( [], $formatted );
	}

	public function testFormatItemIdList_TwoItemIds() {
		$constraintParameterRenderer = new ConstraintParameterRenderer(
			new PlainEntityIdFormatter(),
			new StringFormatter(),
			$this->getDefaultConfig()
		);

		$formatted = $constraintParameterRenderer->formatItemIdList( [
			new ItemId( 'Q1' ),
			new ItemId( 'Q2' ),
		] );

		$this->assertConstraintReportParameterList( [ 'Q1', 'Q2' ], $formatted );
	}

	public function testFormatItemIdList_TwentyItemIds() {
		$constraintParameterRenderer = new ConstraintParameterRenderer(
			new PlainEntityIdFormatter(),
			new StringFormatter(),
			$this->getDefaultConfig()
		);

		$formatted = $constraintParameterRenderer->formatItemIdList(
			array_map(
				function ( $i ) {
					return new ItemId( 'Q' . $i );
				},
				range( 1, 20 )
			)
		);

		$this->assertConstraintReportParameterList(
			[ 'Q1', 'Q2', 'Q3', 'Q4', 'Q5', 'Q6', 'Q7', 'Q8', 'Q9', 'Q10', '...' ],
			$formatted
		);
	}

	public function testFormatEntityIdList_Empty() {
		$constraintParameterRenderer = new ConstraintParameterRenderer(
			new PlainEntityIdFormatter(),
			new StringFormatter(),
			$this->getDefaultConfig()
		);

		$formatted = $constraintParameterRenderer->formatEntityIdList( [] );

		$this->assertConstraintReportParameterList( [], $formatted );
	}

	public function testFormatEntityIdList_PropertyIdItemIdAndNull() {
		$constraintParameterRenderer = new ConstraintParameterRenderer(
			new PlainEntityIdFormatter(),
			new StringFormatter(),
			$this->getDefaultConfig()
		);

		$formatted = $constraintParameterRenderer->formatEntityIdList( [
			new PropertyId( 'P1' ),
			new ItemId( 'Q2' ),
			null
		] );

		$this->assertConstraintReportParameterList( [ 'P1', 'Q2' ], $formatted );
	}

	public function testFormatEntityIdList_TwentyItemIds() {
		$constraintParameterRenderer = new ConstraintParameterRenderer(
			new PlainEntityIdFormatter(),
			new StringFormatter(),
			$this->getDefaultConfig()
		);

		$formatted = $constraintParameterRenderer->formatEntityIdList(
			array_map(
				function ( $i ) {
					return new ItemId( 'Q' . $i );
				},
				range( 1, 20 )
			)
		);

		$this->assertConstraintReportParameterList(
			[ 'Q1', 'Q2', 'Q3', 'Q4', 'Q5', 'Q6', 'Q7', 'Q8', 'Q9', 'Q10', '...' ],
			$formatted
		);
	}

	public function testFormatItemIdSnakValueList_Empty() {
		$constraintParameterRenderer = new ConstraintParameterRenderer(
			new PlainEntityIdFormatter(),
			new StringFormatter(),
			$this->getDefaultConfig()
		);

		$formatted = $constraintParameterRenderer->formatItemIdSnakValueList( [] );

		$this->assertConstraintReportParameterList( [], $formatted );
	}

	public function testFormatItemIdSnakValueList_ValueSomeValueAndNoValue() {
		$constraintParameterRenderer = new ConstraintParameterRenderer(
			new PlainEntityIdFormatter(),
			new StringFormatter(),
			$this->getDefaultConfig()
		);

		$formatted = $constraintParameterRenderer->formatItemIdSnakValueList( [
			ItemIdSnakValue::fromItemId( new ItemId( 'Q1' ) ),
			ItemIdSnakValue::someValue(),
			ItemIdSnakValue::noValue(),
		] );

		$expectedSomeValue = $constraintParameterRenderer->formatItemIdSnakValue(
			ItemIdSnakValue::someValue()
		);
		$expectedNoValue = $constraintParameterRenderer->formatItemIdSnakValue(
			ItemIdSnakValue::noValue()
		);
		$this->assertConstraintReportParameterList(
			[ 'Q1', $expectedSomeValue, $expectedNoValue ],
			$formatted
		);
	}

	public function testFormatItemIdSnakValueList_TwentyItemIdValues() {
		$constraintParameterRenderer = new ConstraintParameterRenderer(
			new PlainEntityIdFormatter(),
			new StringFormatter(),
			$this->getDefaultConfig()
		);

		$formatted = $constraintParameterRenderer->formatItemIdSnakValueList(
			array_map(
				function ( $i ) {
					return ItemIdSnakValue::fromItemId( new ItemId( 'Q' . $i ) );
				},
				range( 1, 20 )
			)
		);

		$this->assertConstraintReportParameterList(
			[ 'Q1', 'Q2', 'Q3', 'Q4', 'Q5', 'Q6', 'Q7', 'Q8', 'Q9', 'Q10', '...' ],
			$formatted
		);
	}

	public function testFormatConstraintScopeList_Empty() {
		$constraintParameterRenderer = new ConstraintParameterRenderer(
			new PlainEntityIdFormatter(),
			new StringFormatter(),
			$this->getDefaultConfig()
		);

		$formatted = $constraintParameterRenderer->formatConstraintScopeList( [] );

		$this->assertConstraintReportParameterList( [], $formatted );
	}

	public function testFormatConstraintScopeList_QualifierAndReferenceScope() {
		$entityIdFormatter = $this->getMock( EntityIdFormatter::class );
		$entityIdFormatter->method( 'formatEntityId' )
			->willReturnCallback( function ( $entityIdSerialization ) {
				switch ( $entityIdSerialization ) {
					case 'Q1':
						return 'statement';
					case 'Q2':
						return 'qualifier';
					case 'Q3':
						return 'reference';
					default:
						return 'unknown';
				}
			} );
		$constraintParameterRenderer = new ConstraintParameterRenderer(
			$entityIdFormatter,
			new StringFormatter(),
			new HashConfig( [
				'WBQualityConstraintsConstraintCheckedOnMainValueId' => 'Q1',
				'WBQualityConstraintsConstraintCheckedOnQualifiersId' => 'Q2',
				'WBQualityConstraintsConstraintCheckedOnReferencesId' => 'Q3',
			] )
		);

		$formatted = $constraintParameterRenderer->formatConstraintScopeList( [
			Context::TYPE_QUALIFIER,
			Context::TYPE_REFERENCE,
		] );

		$this->assertConstraintReportParameterList(
			[ 'qualifier', 'reference' ],
			$formatted
		);
	}

	public function testFormatConstraintScopeList_TwentyStatementScopes() {
		$entityIdFormatter = $this->getMock( EntityIdFormatter::class );
		$entityIdFormatter->method( 'formatEntityId' )
			->willReturnCallback( function ( $entityIdSerialization ) {
				switch ( $entityIdSerialization ) {
					case 'Q1':
						return 'statement';
					case 'Q2':
						return 'qualifier';
					case 'Q3':
						return 'reference';
					default:
						return 'unknown';
				}
			} );
		$constraintParameterRenderer = new ConstraintParameterRenderer(
			$entityIdFormatter,
			new StringFormatter(),
			new HashConfig( [
				'WBQualityConstraintsConstraintCheckedOnMainValueId' => 'Q1',
				'WBQualityConstraintsConstraintCheckedOnQualifiersId' => 'Q2',
				'WBQualityConstraintsConstraintCheckedOnReferencesId' => 'Q3',
			] )
		);

		$formatted = $constraintParameterRenderer->formatConstraintScopeList(
			array_fill( 0, 20, Context::TYPE_STATEMENT )
		);

		$this->assertConstraintReportParameterList(
			array_fill( 0, 10, 'statement' ) + [ 11 => '...' ],
			$formatted
		);
	}

	/**
	 * @param string[] $expected
	 * @param string $actual
	 */
	private function assertConstraintReportParameterList( array $expected, $actual ) {
		$htmlList = '<ul>' . implode( '', array_map( function ( $item ) {
			return "<li>$item</li>";
		}, $expected ) ) . '</ul>';
		array_unshift( $expected, $htmlList );
		$this->assertSame( $expected, $actual );
	}

}
