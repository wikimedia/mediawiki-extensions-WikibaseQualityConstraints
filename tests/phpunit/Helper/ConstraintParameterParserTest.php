<?php

namespace WikibaseQuality\ConstraintReport\Tests\Helper;

use DataValues\MonolingualTextValue;
use DataValues\MultilingualTextValue;
use DataValues\StringValue;
use DataValues\TimeValue;
use DataValues\UnboundedQuantityValue;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Snak\PropertySomeValueSnak;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\Statement;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\Context;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\MainSnakContext;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterException;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\NowValue;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikibaseQuality\ConstraintReport\Tests\ConstraintParameters;
use WikibaseQuality\ConstraintReport\Tests\ResultAssertions;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ItemIdSnakValue;

/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser
 *
 * @group WikibaseQualityConstraints
 *
 * @author Lucas Werkmeister
 * @license GNU GPL v2+
 */
class ConstraintParameterParserTest extends \MediaWikiLangTestCase {

	use ConstraintParameters, ResultAssertions;

	/**
	 * @var Constraint
	 */
	private $constraint;

	protected function setUp() {
		parent::setUp();
		$this->constraint = new Constraint( 'constraint ID', new PropertyId( 'P1' ), 'constraint type Q-ID', [] );
	}

	/**
	 * @param string $itemId
	 * @return array
	 */
	private function serializeItemId( $itemId ) {
		return $this->getSnakSerializer()->serialize(
			new PropertyValueSnak(
				new PropertyId( 'P1' ),
				new EntityIdValue( new ItemId( $itemId ) )
			)
		);
	}

	/**
	 * @param string $propertyId
	 * @return array
	 */
	private function serializePropertyId( $propertyId ) {
		return $this->getSnakSerializer()->serialize(
			new PropertyValueSnak(
				new PropertyId( 'P1' ),
				new EntityIdValue( new PropertyId( $propertyId ) )
			)
		);
	}

	/**
	 * @param string $method
	 * @param array $arguments
	 * @param string $messageKey
	 * @see \WikibaseQuality\ConstraintReport\Tests\ResultAssertions::assertViolation
	 */
	private function assertThrowsConstraintParameterException( $method, array $arguments, $messageKey ) {
		try {
			call_user_func_array( [ $this->getConstraintParameterParser(), $method ], $arguments );
			$this->assertTrue( false,
				"$method should have thrown a ConstraintParameterException with message ⧼${messageKey}⧽." );
		} catch ( ConstraintParameterException $exception ) {
			$checkResult = new CheckResult(
				new MainSnakContext(
					new Item( new ItemId( 'Q1' ) ),
					new Statement( new PropertyNoValueSnak( new PropertyId( 'P1' ) ) )
				),
				$this->constraint,
				[],
				CheckResult::STATUS_VIOLATION,
				$exception->getMessage()
			);
			$this->assertViolation( $checkResult, $messageKey );
		}
	}

	public function testParseClassParameter() {
		$config = $this->getDefaultConfig();
		$classId = $config->get( 'WBQualityConstraintsClassId' );
		$parsed = $this->getConstraintParameterParser()->parseClassParameter(
			[ $classId => [ $this->serializeItemId( 'Q100' ), $this->serializeItemId( 'Q101' ) ] ],
			''
		);
		$this->assertEquals( [ 'Q100', 'Q101' ], $parsed );
	}

	public function testParseClassParameter_Missing() {
		$this->assertThrowsConstraintParameterException(
			'parseClassParameter',
			[ [], 'constraint' ],
			'wbqc-violation-message-parameter-needed'
		);
	}

	public function testParseClassParameter_NoValue() {
		$config = $this->getDefaultConfig();
		$classId = $config->get( 'WBQualityConstraintsClassId' );
		$this->assertThrowsConstraintParameterException(
			'parseClassParameter',
			[
				[
					$classId => [
						$this->getSnakSerializer()->serialize( new PropertyNoValueSnak( new PropertyId( $classId ) ) )
					]
				],
				'constraint'
			],
			'wbqc-violation-message-parameter-value'
		);
	}

	public function testParseClassParameter_StringValue() {
		$config = $this->getDefaultConfig();
		$classId = $config->get( 'WBQualityConstraintsClassId' );
		$this->assertThrowsConstraintParameterException(
			'parseClassParameter',
			[
				[
					$classId => [
						$this->getSnakSerializer()->serialize( new PropertyValueSnak(
							new PropertyId( $classId ),
							new StringValue( 'Q100' )
						) )
					]
				],
				'constraint'
			],
			'wbqc-violation-message-parameter-entity'
		);
	}

	public function testParseRelationParameter() {
		$config = $this->getDefaultConfig();
		$relationId = $config->get( 'WBQualityConstraintsRelationId' );
		$instanceOfId = $config->get( 'WBQualityConstraintsInstanceOfRelationId' );
		$parsed = $this->getConstraintParameterParser()->parseRelationParameter(
			[ $relationId => [ $this->serializeItemId( $instanceOfId ) ] ],
			''
		);
		$this->assertEquals( 'instance', $parsed );
	}

	public function testParseRelationParameter_Missing() {
		$this->assertThrowsConstraintParameterException(
			'parseRelationParameter',
			[ [], 'constraint' ],
			'wbqc-violation-message-parameter-needed'
		);
	}

	public function testParseRelationParameter_NoValue() {
		$config = $this->getDefaultConfig();
		$relationId = $config->get( 'WBQualityConstraintsRelationId' );
		$this->assertThrowsConstraintParameterException(
			'parseRelationParameter',
			[
				[
					$relationId => [
						$this->getSnakSerializer()->serialize( new PropertyNoValueSnak( new PropertyId( $relationId ) ) )
					]
				],
				'constraint'
			],
			'wbqc-violation-message-parameter-value'
		);
	}

	public function testParseRelationParameter_StringValue() {
		$config = $this->getDefaultConfig();
		$relationId = $config->get( 'WBQualityConstraintsRelationId' );
		$this->assertThrowsConstraintParameterException(
			'parseRelationParameter',
			[
				[
					$relationId => [
						$this->getSnakSerializer()->serialize( new PropertyValueSnak(
							new PropertyId( $relationId ),
							new StringValue( 'instance' )
						) )
					]
				],
				'constraint'
			],
			'wbqc-violation-message-parameter-entity'
		);
	}

	public function testParseRelationParameter_MultiValue() {
		$config = $this->getDefaultConfig();
		$relationId = $config->get( 'WBQualityConstraintsRelationId' );
		$instanceOfId = $config->get( 'WBQualityConstraintsInstanceOfRelationId' );
		$subclassOfId = $config->get( 'WBQualityConstraintsSubclassOfRelationId' );
		$this->assertThrowsConstraintParameterException(
			'parseRelationParameter',
			[
				[
					$relationId => [
						$this->serializeItemId( $instanceOfId ),
						$this->serializeItemId( $subclassOfId )
					]
				],
				'constraint'
			],
			'wbqc-violation-message-parameter-single'
		);
	}

	public function testParseRelationParameter_WrongValue() {
		$config = $this->getDefaultConfig();
		$relationId = $config->get( 'WBQualityConstraintsRelationId' );
		$this->assertThrowsConstraintParameterException(
			'parseRelationParameter',
			[
				[
					$relationId => [ $this->serializeItemId( 'Q1' ) ]
				],
				'constraint'
			],
			'wbqc-violation-message-parameter-oneof'
		);
	}

	public function testParsePropertyParameter() {
		$config = $this->getDefaultConfig();
		$propertyId = $config->get( 'WBQualityConstraintsPropertyId' );
		$parsed = $this->getConstraintParameterParser()->parsePropertyParameter(
			[ $propertyId => [ $this->serializePropertyId( 'P100' ) ] ],
			''
		);
		$this->assertEquals( new PropertyId( 'P100' ), $parsed );
	}

	public function testParsePropertyParameter_Missing() {
		$this->assertThrowsConstraintParameterException(
			'parsePropertyParameter',
			[ [], 'constraint' ],
			'wbqc-violation-message-parameter-needed'
		);
	}

	public function testParsePropertyParameter_NoValue() {
		$config = $this->getDefaultConfig();
		$propertyId = $config->get( 'WBQualityConstraintsPropertyId' );
		$this->assertThrowsConstraintParameterException(
			'parsePropertyParameter',
			[
				[
					$propertyId => [
						$this->getSnakSerializer()->serialize( new PropertyNoValueSnak( new PropertyId( $propertyId ) ) )
					]
				],
				'constraint'
			],
			'wbqc-violation-message-parameter-value'
		);
	}

	public function testParsePropertyParameter_StringValue() {
		$config = $this->getDefaultConfig();
		$propertyId = $config->get( 'WBQualityConstraintsPropertyId' );
		$this->assertThrowsConstraintParameterException(
			'parsePropertyParameter',
			[
				[
					$propertyId => [
						$this->getSnakSerializer()->serialize( new PropertyValueSnak(
							new PropertyId( $propertyId ),
							new StringValue( 'P1' )
						) )
					]
				],
				'constraint'
			],
			'wbqc-violation-message-parameter-property'
		);
	}

	public function testParsePropertyParameter_ItemId() {
		$config = $this->getDefaultConfig();
		$propertyId = $config->get( 'WBQualityConstraintsPropertyId' );
		$this->assertThrowsConstraintParameterException(
			'parsePropertyParameter',
			[
				[
					$propertyId => [
						$this->serializeItemId( 'Q100' )
					]
				],
				'constraint'
			],
			'wbqc-violation-message-parameter-property'
		);
	}

	public function testParsePropertyParameter_MultiValue() {
		$config = $this->getDefaultConfig();
		$propertyId = $config->get( 'WBQualityConstraintsPropertyId' );
		$this->assertThrowsConstraintParameterException(
			'parsePropertyParameter',
			[
				[
					$propertyId => [
						$this->serializePropertyId( 'P100' ),
						$this->serializePropertyId( 'P101' )
					]
				],
				'constraint'
			],
			'wbqc-violation-message-parameter-single'
		);
	}

	public function testParseItemsParameter() {
		$config = $this->getDefaultConfig();
		$qualifierId = $config->get( 'WBQualityConstraintsQualifierOfPropertyConstraintId' );
		$parsed = $this->getConstraintParameterParser()->parseItemsParameter(
			[
				$qualifierId => [
					$this->serializeItemId( 'Q100' ),
					$this->serializeItemId( 'Q101' ),
					$this->getSnakSerializer()->serialize( new PropertySomeValueSnak( new PropertyId( 'P1' ) ) ),
					$this->getSnakSerializer()->serialize( new PropertyNoValueSnak( new PropertyId( 'P1' ) ) )
				]
			],
			'',
			false
		);
		$expected = [
			ItemIdSnakValue::fromItemId( new ItemId( 'Q100' ) ),
			ItemIdSnakValue::fromItemId( new ItemId( 'Q101' ) ),
			ItemIdSnakValue::someValue(),
			ItemIdSnakValue::noValue()
		];
		$this->assertEquals( $expected, $parsed );
	}

	public function testParseItemsParameter_Required() {
		$this->assertThrowsConstraintParameterException(
			'parseItemsParameter',
			[
				[], 'constraint', true
			],
			'wbqc-violation-message-parameter-needed'
		);
	}

	public function testParseItemsParameter_NotRequired() {
		$parsed = $this->getConstraintParameterParser()->parseItemsParameter( [], 'constraint', false );
		$this->assertEquals( [], $parsed );
	}

	public function testParseItemsParameter_StringValue() {
		$config = $this->getDefaultConfig();
		$qualifierId = $config->get( 'WBQualityConstraintsQualifierOfPropertyConstraintId' );
		$this->assertThrowsConstraintParameterException(
			'parseItemsParameter',
			[
				[
					$qualifierId => [
						$this->getSnakSerializer()->serialize( new PropertyValueSnak(
							new PropertyId( 'P1' ),
							new StringValue( 'Q100' )
						) )
					]
				],
				'constraint',
				true
			],
			'wbqc-violation-message-parameter-item'
		);
	}

	public function testParseItemsParameter_PropertyId() {
		$config = $this->getDefaultConfig();
		$qualifierId = $config->get( 'WBQualityConstraintsQualifierOfPropertyConstraintId' );
		$this->assertThrowsConstraintParameterException(
			'parseItemsParameter',
			[
				[
					$qualifierId => [
						$this->getSnakSerializer()->serialize( new PropertyValueSnak(
							new PropertyId( 'P1' ),
							new EntityIdValue( new PropertyId( 'P100' ) )
						) )
					]
				],
				'constraint',
				true
			],
			'wbqc-violation-message-parameter-item'
		);
	}

	public function testParsePropertiesParameter() {
		$config = $this->getDefaultConfig();
		$propertyId = $config->get( 'WBQualityConstraintsPropertyId' );
		$parsed = $this->getConstraintParameterParser()->parsePropertiesParameter(
			[ $propertyId => [ $this->serializePropertyId( 'P100' ), $this->serializePropertyId( 'P101' ) ] ],
			''
		);
		$this->assertEquals( [ new PropertyId( 'P100' ), new PropertyId( 'P101' ) ], $parsed );
	}

	public function testParsePropertiesParameter_Missing() {
		$this->assertThrowsConstraintParameterException(
			'parsePropertiesParameter',
			[ [], 'constraint' ],
			'wbqc-violation-message-parameter-needed'
		);
	}

	public function testParsePropertiesParameter_NoValue() {
		$config = $this->getDefaultConfig();
		$propertyId = $config->get( 'WBQualityConstraintsPropertyId' );
		$parsed = $this->getConstraintParameterParser()->parsePropertiesParameter(
			[ $propertyId => [ $this->getSnakSerializer()->serialize( new PropertyNoValueSnak( new PropertyId( $propertyId ) ) ) ] ],
			''
		);
		$this->assertEquals( [], $parsed );
	}

	public function testParseRangeParameter_Quantity_Bounded() {
		$config = $this->getDefaultConfig();
		$minimumId = $config->get( 'WBQualityConstraintsMinimumQuantityId' );
		$maximumId = $config->get( 'WBQualityConstraintsMaximumQuantityId' );
		$propertyId = new PropertyId( 'P1' );
		$min = UnboundedQuantityValue::newFromNumber( 13.37 );
		$max = UnboundedQuantityValue::newFromNumber( 42 );

		$parsed = $this->getConstraintParameterParser()->parseRangeParameter(
			[
				$minimumId => [ $this->getSnakSerializer()->serialize( new PropertyValueSnak( $propertyId, $min ) ) ],
				$maximumId => [ $this->getSnakSerializer()->serialize( new PropertyValueSnak( $propertyId, $max ) ) ]
			],
			'',
			'quantity'
		);

		$this->assertEquals( [ $min, $max ], $parsed );
	}

	public function testParseRangeParameter_Quantity_LeftOpen() {
		$config = $this->getDefaultConfig();
		$minimumId = $config->get( 'WBQualityConstraintsMinimumQuantityId' );
		$maximumId = $config->get( 'WBQualityConstraintsMaximumQuantityId' );
		$propertyId = new PropertyId( 'P1' );
		$max = UnboundedQuantityValue::newFromNumber( 42 );

		$parsed = $this->getConstraintParameterParser()->parseRangeParameter(
			[
				$minimumId => [ $this->getSnakSerializer()->serialize( new PropertyNoValueSnak( $propertyId ) ) ],
				$maximumId => [ $this->getSnakSerializer()->serialize( new PropertyValueSnak( $propertyId, $max ) ) ]
			],
			'',
			'quantity'
		);

		$this->assertEquals( [ null, $max ], $parsed );
	}

	public function testParseRangeParameter_Quantity_RightOpen() {
		$config = $this->getDefaultConfig();
		$minimumId = $config->get( 'WBQualityConstraintsMinimumQuantityId' );
		$maximumId = $config->get( 'WBQualityConstraintsMaximumQuantityId' );
		$propertyId = new PropertyId( 'P1' );
		$min = UnboundedQuantityValue::newFromNumber( 13.37 );

		$parsed = $this->getConstraintParameterParser()->parseRangeParameter(
			[
				$minimumId => [ $this->getSnakSerializer()->serialize( new PropertyValueSnak( $propertyId, $min ) ) ],
				$maximumId => [ $this->getSnakSerializer()->serialize( new PropertyNoValueSnak( $propertyId ) ) ]
			],
			'',
			'quantity'
		);

		$this->assertEquals( [ $min, null ], $parsed );
	}

	public function testParseRangeParameter_Quantity_FullyOpen() {
		$config = $this->getDefaultConfig();
		$minimumId = $config->get( 'WBQualityConstraintsMinimumQuantityId' );
		$maximumId = $config->get( 'WBQualityConstraintsMaximumQuantityId' );
		$propertyId = new PropertyId( 'P1' );

		$this->assertThrowsConstraintParameterException(
			'parseRangeParameter',
			[
				[
					$minimumId => [ $this->getSnakSerializer()->serialize( new PropertyNoValueSnak( $propertyId ) ) ],
					$maximumId => [ $this->getSnakSerializer()->serialize( new PropertyNoValueSnak( $propertyId ) ) ]
				],
				'',
				'quantity'
			],
			'wbqc-violation-message-range-parameters-same'
		);
	}

	public function testParseRangeParameter_Quantity_SomeValue() {
		$config = $this->getDefaultConfig();
		$minimumId = $config->get( 'WBQualityConstraintsMinimumQuantityId' );
		$maximumId = $config->get( 'WBQualityConstraintsMaximumQuantityId' );
		$propertyId = new PropertyId( 'P1' );

		$this->assertThrowsConstraintParameterException(
			'parseRangeParameter',
			[
				[
					$minimumId => [ $this->getSnakSerializer()->serialize( new PropertySomeValueSnak( $propertyId ) ) ],
					$maximumId => [ $this->getSnakSerializer()->serialize( new PropertySomeValueSnak( $propertyId ) ) ]
				],
				'constraint',
				'quantity'
			],
			'wbqc-violation-message-parameter-value-or-novalue'
		);
	}

	public function testParseRangeParameter_Quantity_Same() {
		$config = $this->getDefaultConfig();
		$minimumId = $config->get( 'WBQualityConstraintsMinimumQuantityId' );
		$maximumId = $config->get( 'WBQualityConstraintsMaximumQuantityId' );
		$propertyId = new PropertyId( 'P1' );
		$quantity = UnboundedQuantityValue::newFromNumber( 13.37 );

		$this->assertThrowsConstraintParameterException(
			'parseRangeParameter',
			[
				[
					$minimumId => [ $this->getSnakSerializer()->serialize( new PropertyValueSnak( $propertyId, $quantity ) ) ],
					$maximumId => [ $this->getSnakSerializer()->serialize( new PropertyValueSnak( $propertyId, $quantity ) ) ]
				],
				'constraint',
				'quantity'
			],
			'wbqc-violation-message-range-parameters-same'
		);
	}

	public function testParseRangeParameter_Time_Bounded() {
		$config = $this->getDefaultConfig();
		$minimumId = $config->get( 'WBQualityConstraintsMinimumDateId' );
		$maximumId = $config->get( 'WBQualityConstraintsMaximumDateId' );
		$propertyId = new PropertyId( 'P1' );
		$calendar = TimeValue::CALENDAR_GREGORIAN;
		$min = new TimeValue( '+1789-05-08T00:00:00Z', 0, 0, 0, TimeValue::PRECISION_YEAR, $calendar );
		$max = new TimeValue( '+1955-02-05T00:00:00Z', 0, 0, 0, TimeValue::PRECISION_YEAR, $calendar );

		$parsed = $this->getConstraintParameterParser()->parseRangeParameter(
			[
				$minimumId => [ $this->getSnakSerializer()->serialize( new PropertyValueSnak( $propertyId, $min ) ) ],
				$maximumId => [ $this->getSnakSerializer()->serialize( new PropertyValueSnak( $propertyId, $max ) ) ]
			],
			'',
			'time'
		);

		$this->assertEquals( [ $min, $max ], $parsed );
	}

	public function testParseRangeParameter_Time_Past() {
		$config = $this->getDefaultConfig();
		$minimumId = $config->get( 'WBQualityConstraintsMinimumDateId' );
		$maximumId = $config->get( 'WBQualityConstraintsMaximumDateId' );
		$propertyId = new PropertyId( 'P1' );

		$parsed = $this->getConstraintParameterParser()->parseRangeParameter(
			[
				$minimumId => [ $this->getSnakSerializer()->serialize( new PropertyNoValueSnak( $propertyId ) ) ],
				$maximumId => [ $this->getSnakSerializer()->serialize( new PropertySomeValueSnak( $propertyId ) ) ]
			],
			'',
			'time'
		);

		$this->assertEquals( [ null, new NowValue() ], $parsed );
	}

	public function testParseRangeParameter_Time_Future() {
		$config = $this->getDefaultConfig();
		$minimumId = $config->get( 'WBQualityConstraintsMinimumDateId' );
		$maximumId = $config->get( 'WBQualityConstraintsMaximumDateId' );
		$propertyId = new PropertyId( 'P1' );

		$parsed = $this->getConstraintParameterParser()->parseRangeParameter(
			[
				$minimumId => [ $this->getSnakSerializer()->serialize( new PropertySomeValueSnak( $propertyId ) ) ],
				$maximumId => [ $this->getSnakSerializer()->serialize( new PropertyNoValueSnak( $propertyId ) ) ]
			],
			'',
			'time'
		);

		$this->assertEquals( [ new NowValue(), null ], $parsed );
	}

	public function testParseRangeParameter_Time_BothNow() {
		$config = $this->getDefaultConfig();
		$minimumId = $config->get( 'WBQualityConstraintsMinimumDateId' );
		$maximumId = $config->get( 'WBQualityConstraintsMaximumDateId' );
		$propertyId = new PropertyId( 'P1' );

		$this->assertThrowsConstraintParameterException(
			'parseRangeParameter',
			[
				[
					$minimumId => [ $this->getSnakSerializer()->serialize( new PropertySomeValueSnak( $propertyId ) ) ],
					$maximumId => [ $this->getSnakSerializer()->serialize( new PropertySomeValueSnak( $propertyId ) ) ]
				],
				'',
				'time'
			],
			'wbqc-violation-message-range-parameters-same'
		);
	}

	public function testParseRangeParameter_Time_Wikidata() {
		// range: from the inception of Wikidata until now
		// (NowValue uses the same date internally, but that should not result in a “same range endpoints” error)
		$config = $this->getDefaultConfig();
		$minimumId = $config->get( 'WBQualityConstraintsMinimumDateId' );
		$maximumId = $config->get( 'WBQualityConstraintsMaximumDateId' );
		$propertyId = new PropertyId( 'P1' );
		$wikidataInception = new TimeValue(
			'+2012-10-29T00:00:00Z',
			0,
			0,
			0,
			TimeValue::PRECISION_SECOND,
			TimeValue::CALENDAR_GREGORIAN
		);

		$parsed = $this->getConstraintParameterParser()->parseRangeParameter(
			[
				$minimumId => [ $this->getSnakSerializer()->serialize( new PropertyValueSnak( $propertyId, $wikidataInception ) ) ],
				$maximumId => [ $this->getSnakSerializer()->serialize( new PropertySomeValueSnak( $propertyId ) ) ]
			],
			'',
			'time'
		);

		$this->assertEquals( [ $wikidataInception, new NowValue() ], $parsed );
	}

	public function testParseRangeParameter_Quantity_OneYear() {
		$config = $this->getDefaultConfig();
		$minimumId = $config->get( 'WBQualityConstraintsMinimumQuantityId' );
		$maximumId = $config->get( 'WBQualityConstraintsMaximumQuantityId' );
		$yearUnit = $config->get( 'WBQualityConstraintsYearUnit' );
		$min = UnboundedQuantityValue::newFromNumber( 0, 'other unit than ' . $yearUnit );
		$max = UnboundedQuantityValue::newFromNumber( 150, $yearUnit );
		$minSnak = new PropertyValueSnak( new PropertyId( $minimumId ), $min );
		$maxSnak = new PropertyValueSnak( new PropertyId( $maximumId ), $max );

		$this->assertThrowsConstraintParameterException(
			'parseRangeParameter',
			[
				[
					$minimumId => [ $this->getSnakSerializer()->serialize( $minSnak ) ],
					$maximumId => [ $this->getSnakSerializer()->serialize( $maxSnak ) ]
				],
				'constraint',
				'quantity'
			],
			'wbqc-violation-message-range-parameters-one-year'
		);
	}

	public function testParseRangeParameterQuantity_OneYear_LeftOpen() {
		$config = $this->getDefaultConfig();
		$minimumId = $config->get( 'WBQualityConstraintsMinimumQuantityId' );
		$maximumId = $config->get( 'WBQualityConstraintsMaximumQuantityId' );
		$yearUnit = $config->get( 'WBQualityConstraintsYearUnit' );
		$max = UnboundedQuantityValue::newFromNumber( 150, $yearUnit );
		$minSnak = new PropertyNoValueSnak( new PropertyId( $minimumId ) );
		$maxSnak = new PropertyValueSnak( new PropertyId( $maximumId ), $max );

		$parsed = $this->getConstraintParameterParser()->parseRangeParameter(
			[
				$minimumId => [ $this->getSnakSerializer()->serialize( $minSnak ) ],
				$maximumId => [ $this->getSnakSerializer()->serialize( $maxSnak ) ]
			],
			'constraint',
			'quantity'
		);

		$this->assertEquals( [ null, $max ], $parsed );
	}

	public function testParseRangeParameter_MissingParameters() {
		foreach ( [ 'quantity', 'time' ] as $type ) {
			$this->assertThrowsConstraintParameterException(
				'parseRangeParameter',
				[
					[],
					'constraint',
					$type
				],
				'wbqc-violation-message-range-parameters-needed'
			);
		}
	}

	public function testParseNamespaceParameter() {
		$namespaceId = $this->getDefaultConfig()->get( 'WBQualityConstraintsNamespaceId' );
		$value = new StringValue( 'File' );
		$snak = new PropertyValueSnak( new PropertyId( 'P1' ), $value );

		$parsed = $this->getConstraintParameterParser()->parseNamespaceParameter(
			[ $namespaceId => [ $this->getSnakSerializer()->serialize( $snak ) ] ],
			''
		);

		$this->assertEquals( 'File', $parsed );
	}

	public function testParseNamespaceParameter_Missing() {
		$parsed = $this->getConstraintParameterParser()->parseNamespaceParameter(
			[],
			''
		);

		$this->assertEquals( '', $parsed );
	}

	public function testParseNamespaceParameter_ItemId() {
		$namespaceId = $this->getDefaultConfig()->get( 'WBQualityConstraintsNamespaceId' );
		$value = new EntityIdValue( new ItemId( 'Q1' ) );
		$snak = new PropertyValueSnak( new PropertyId( 'P1' ), $value );

		$this->assertThrowsConstraintParameterException(
			'parseNamespaceParameter',
			[
				[ $namespaceId => [ $this->getSnakSerializer()->serialize( $snak ) ] ],
				'constraint'
			],
			'wbqc-violation-message-parameter-string'
		);
	}

	public function testParseNamespaceParameter_Multiple() {
		$namespaceId = $this->getDefaultConfig()->get( 'WBQualityConstraintsNamespaceId' );
		$value1 = new StringValue( 'File' );
		$snak1 = new PropertyValueSnak( new PropertyId( 'P1' ), $value1 );
		$value2 = new StringValue( 'Category' );
		$snak2 = new PropertyValueSnak( new PropertyId( 'P1' ), $value2 );

		$this->assertThrowsConstraintParameterException(
			'parseNamespaceParameter',
			[
				[ $namespaceId => [
					$this->getSnakSerializer()->serialize( $snak1 ),
					$this->getSnakSerializer()->serialize( $snak2 )
				] ],
				'constraint'
			],
			'wbqc-violation-message-parameter-single'
		);
	}

	public function testParseFormatParameter() {
		$formatId = $this->getDefaultConfig()->get( 'WBQualityConstraintsFormatAsARegularExpressionId' );
		$value = new StringValue( '\d\.(\d{1,2}|-{1})\.(\d{1,2}|-{1})\.(\d{1,3}|-{1})' );
		$snak = new PropertyValueSnak( new PropertyId( 'P1' ), $value );

		$parsed = $this->getConstraintParameterParser()->parseFormatParameter(
			[ $formatId => [ $this->getSnakSerializer()->serialize( $snak ) ] ],
			''
		);

		$this->assertEquals( '\d\.(\d{1,2}|-{1})\.(\d{1,2}|-{1})\.(\d{1,3}|-{1})', $parsed );
	}

	public function testParseFormatParameter_Missing() {
		$this->assertThrowsConstraintParameterException(
			'parseFormatParameter',
			[
				[],
				'constraint'
			],
			'wbqc-violation-message-parameter-needed'
		);
	}

	public function testParseFormatParameter_ItemId() {
		$formatId = $this->getDefaultConfig()->get( 'WBQualityConstraintsFormatAsARegularExpressionId' );
		$value = new EntityIdValue( new ItemId( 'Q1' ) );
		$snak = new PropertyValueSnak( new PropertyId( 'P1' ), $value );

		$this->assertThrowsConstraintParameterException(
			'parseFormatParameter',
			[
				[ $formatId => [ $this->getSnakSerializer()->serialize( $snak ) ] ],
				'constraint'
			],
			'wbqc-violation-message-parameter-string'
		);
	}

	public function testParseFormatParameter_Multiple() {
		$formatId = $this->getDefaultConfig()->get( 'WBQualityConstraintsFormatAsARegularExpressionId' );
		$value1 = new StringValue( '\d\.(\d{1,2}|-{1})\.(\d{1,2}|-{1})\.(\d{1,3}|-{1})' );
		$snak1 = new PropertyValueSnak( new PropertyId( 'P1' ), $value1 );
		$value2 = new StringValue( '\d+' );
		$snak2 = new PropertyValueSnak( new PropertyId( 'P1' ), $value2 );

		$this->assertThrowsConstraintParameterException(
			'parseFormatParameter',
			[
				[ $formatId => [
					$this->getSnakSerializer()->serialize( $snak1 ),
					$this->getSnakSerializer()->serialize( $snak2 )
				] ],
				'constraint'
			],
			'wbqc-violation-message-parameter-single'
		);
	}

	public function testParseExceptionParameter() {
		$exceptionId = $this->getDefaultConfig()->get( 'WBQualityConstraintsExceptionToConstraintId' );
		$entityId1 = new ItemId( 'Q100' );
		$entityId2 = new PropertyId( 'P100' );
		$snak1 = new PropertyValueSnak( new PropertyId( $exceptionId ), new EntityIdValue( $entityId1 ) );
		$snak2 = new PropertyValueSnak( new PropertyId( $exceptionId ), new EntityIdValue( $entityId2 ) );

		$parsed = $this->getConstraintParameterParser()->parseExceptionParameter(
			[ $exceptionId => [
				$this->getSnakSerializer()->serialize( $snak1 ),
				$this->getSnakSerializer()->serialize( $snak2 ),
			] ]
		);

		$this->assertEquals( [ $entityId1, $entityId2 ], $parsed );
	}

	public function testParseExceptionParameter_Missing() {
		$parsed = $this->getConstraintParameterParser()->parseExceptionParameter(
			[]
		);

		$this->assertEquals( [], $parsed );
	}

	public function testParseExceptionParameter_String() {
		$exceptionId = $this->getDefaultConfig()->get( 'WBQualityConstraintsExceptionToConstraintId' );
		$snak = new PropertyValueSnak( new PropertyId( $exceptionId ), new StringValue( 'Q100' ) );

		$this->assertThrowsConstraintParameterException(
			'parseExceptionParameter',
			[ [ $exceptionId => [ $this->getSnakSerializer()->serialize( $snak ) ] ] ],
			'wbqc-violation-message-parameter-entity'
		);
	}

	public function testParseExceptionParameter_TooLong() {
		$this->assertThrowsConstraintParameterException(
			'parseExceptionParameter',
			[ [ '@error' => [ 'toolong' => true ] ] ],
			'wbqc-violation-message-parameters-error-toolong'
		);
	}

	public function testParseConstraintStatusParameter() {
		$constraintStatusId = $this->getDefaultConfig()->get( 'WBQualityConstraintsConstraintStatusId' );
		$mandatoryId = $this->getDefaultConfig()->get( 'WBQualityConstraintsMandatoryConstraintId' );
		$snak = new PropertyValueSnak( new PropertyId( $constraintStatusId ), new EntityIdValue( new ItemId( $mandatoryId ) ) );

		$parsed = $this->getConstraintParameterParser()->parseConstraintStatusParameter(
			[ $constraintStatusId => [ $this->getSnakSerializer()->serialize( $snak ) ] ]
		);

		$this->assertEquals( 'mandatory', $parsed );
	}

	public function testParseConstraintStatusParameter_Missing() {
		$parsed = $this->getConstraintParameterParser()->parseConstraintStatusParameter(
			[]
		);

		$this->assertNull( $parsed );
	}

	public function testParseConstraintStatusParameter_Invalid() {
		$constraintStatusId = $this->getDefaultConfig()->get( 'WBQualityConstraintsConstraintStatusId' );
		$snak = new PropertyValueSnak( new PropertyId( $constraintStatusId ), new EntityIdValue( new ItemId( 'Q1' ) ) );

		$this->assertThrowsConstraintParameterException(
			'parseConstraintStatusParameter',
			[ [ $constraintStatusId => [ $this->getSnakSerializer()->serialize( $snak ) ] ] ],
			'wbqc-violation-message-parameter-oneof'
		);
	}

	public function testParseConstraintStatusParameter_UnknownError() {
		$this->assertThrowsConstraintParameterException(
			'parseExceptionParameter',
			[ [ '@error' => [] ] ],
			'wbqc-violation-message-parameters-error-unknown'
		);
	}

	public function testParseSyntaxClarificationParameter_SingleClarification() {
		$syntaxClarificationId = $this->getDefaultConfig()->get( 'WBQualityConstraintsSyntaxClarificationId' );
		$value = new MonolingualTextValue( 'en', 'explanation' );
		$snak = new PropertyValueSnak( new PropertyId( $syntaxClarificationId ), $value );

		$parsed = $this->getConstraintParameterParser()->parseSyntaxClarificationParameter(
			[ $syntaxClarificationId => [ $this->getSnakSerializer()->serialize( $snak ) ] ]
		);

		$this->assertEquals(
			new MultilingualTextValue( [ $value ] ),
			$parsed
		);
	}

	public function testParseSyntaxClarificationParameter_MultipleClarifications() {
		$syntaxClarificationId = $this->getDefaultConfig()->get( 'WBQualityConstraintsSyntaxClarificationId' );
		$value1 = new MonolingualTextValue( 'en', 'explanation' );
		$snak1 = new PropertyValueSnak( new PropertyId( $syntaxClarificationId ), $value1 );
		$value2 = new MonolingualTextValue( 'de', 'Erklärung' );
		$snak2 = new PropertyValueSnak( new PropertyId( $syntaxClarificationId ), $value2 );
		$value3 = new MonolingualTextValue( 'pt', 'explicação' );
		$snak3 = new PropertyValueSnak( new PropertyId( $syntaxClarificationId ), $value3 );

		$parsed = $this->getConstraintParameterParser()->parseSyntaxClarificationParameter(
			[ $syntaxClarificationId => [
				$this->getSnakSerializer()->serialize( $snak1 ),
				$this->getSnakSerializer()->serialize( $snak2 ),
				$this->getSnakSerializer()->serialize( $snak3 ),
			] ]
		);

		$this->assertEquals(
			new MultilingualTextValue( [ $value1, $value2, $value3 ] ),
			$parsed
		);
	}

	public function testParseSyntaxClarificationParameter_NoClarifications() {
		$parsed = $this->getConstraintParameterParser()->parseSyntaxClarificationParameter(
			[]
		);

		$this->assertEquals( new MultilingualTextValue( [] ), $parsed );
	}

	public function testParseSyntaxClarificationParameter_Invalid_MultipleValuesForLanguage() {
		$syntaxClarificationId = $this->getDefaultConfig()->get( 'WBQualityConstraintsSyntaxClarificationId' );
		$value1 = new MonolingualTextValue( 'en', 'explanation' );
		$snak1 = new PropertyValueSnak( new PropertyId( $syntaxClarificationId ), $value1 );
		$value2 = new MonolingualTextValue( 'en', 'better explanation' );
		$snak2 = new PropertyValueSnak( new PropertyId( $syntaxClarificationId ), $value2 );

		$this->assertThrowsConstraintParameterException(
			'parseSyntaxClarificationParameter',
			[
				[ $syntaxClarificationId => [
					$this->getSnakSerializer()->serialize( $snak1 ),
					$this->getSnakSerializer()->serialize( $snak2 ),
				] ]
			],
			'wbqc-violation-message-parameter-single-per-language'
		);
	}

	public function testParseSyntaxClarificationParameter_Invalid_String() {
		$syntaxClarificationId = $this->getDefaultConfig()->get( 'WBQualityConstraintsSyntaxClarificationId' );
		$value = new StringValue( 'explanation' );
		$snak = new PropertyValueSnak( new PropertyId( $syntaxClarificationId ), $value );

		$this->assertThrowsConstraintParameterException(
			'parseSyntaxClarificationParameter',
			[
				[ $syntaxClarificationId => [
					$this->getSnakSerializer()->serialize( $snak )
				] ]
			],
			'wbqc-violation-message-parameter-monolingualtext'
		);
	}

	public function testParseSyntaxClarificationParameter_Invalid_Novalue() {
		$syntaxClarificationId = $this->getDefaultConfig()->get( 'WBQualityConstraintsSyntaxClarificationId' );
		$snak = new PropertyNoValueSnak( new PropertyId( $syntaxClarificationId ) );

		$this->assertThrowsConstraintParameterException(
			'parseSyntaxClarificationParameter',
			[
				[ $syntaxClarificationId => [
					$this->getSnakSerializer()->serialize( $snak )
				] ]
			],
			'wbqc-violation-message-parameter-value'
		);
	}

	public function testParseConstraintScopeParameter_MainSnak() {
		$constraintScopeId = $this->getDefaultConfig()->get( 'WBQualityConstraintsConstraintScopeId' );
		$mainSnakId = new ItemId( $this->getDefaultConfig()->get( 'WBQualityConstraintsConstraintCheckedOnMainValueId' ) );
		$snak = new PropertyValueSnak( new PropertyId( $constraintScopeId ), new EntityIdValue( $mainSnakId ) );

		$parsed = $this->getConstraintParameterParser()->parseConstraintScopeParameter(
			[ $constraintScopeId => [
				$this->getSnakSerializer()->serialize( $snak ),
			] ],
			''
		);

		$this->assertSame( [ Context::TYPE_STATEMENT ], $parsed );
	}

	public function testParseConstraintScopeParameter_NotMainSnak() {
		$constraintScopeId = $this->getDefaultConfig()->get( 'WBQualityConstraintsConstraintScopeId' );
		$qualifiersId = new ItemId( $this->getDefaultConfig()->get( 'WBQualityConstraintsConstraintCheckedOnQualifiersId' ) );
		$referencesId = new ItemId( $this->getDefaultConfig()->get( 'WBQualityConstraintsConstraintCheckedOnReferencesId' ) );
		$snak1 = new PropertyValueSnak( new PropertyId( $constraintScopeId ), new EntityIdValue( $qualifiersId ) );
		$snak2 = new PropertyValueSnak( new PropertyId( $constraintScopeId ), new EntityIdValue( $referencesId ) );

		$parsed = $this->getConstraintParameterParser()->parseConstraintScopeParameter(
			[ $constraintScopeId => [
				$this->getSnakSerializer()->serialize( $snak1 ),
				$this->getSnakSerializer()->serialize( $snak2 ),
			] ],
			''
		);

		$this->assertSame( [ Context::TYPE_QUALIFIER, Context::TYPE_REFERENCE ], $parsed );
	}

	public function testParseConstraintScopeParameter_Missing() {
		$parsed = $this->getConstraintParameterParser()->parseConstraintScopeParameter( [], '' );

		$this->assertNull( $parsed );
	}

	public function testParseConstraintParameter_ValidScope() {
		$constraintScopeId = $this->getDefaultConfig()->get( 'WBQualityConstraintsConstraintScopeId' );
		$qualifiersId = new ItemId( $this->getDefaultConfig()->get( 'WBQualityConstraintsConstraintCheckedOnQualifiersId' ) );
		$snak = new PropertyValueSnak( new PropertyId( $constraintScopeId ), new EntityIdValue( $qualifiersId ) );

		$parsed = $this->getConstraintParameterParser()->parseConstraintScopeParameter(
			[ $constraintScopeId => [
				$this->getSnakSerializer()->serialize( $snak ),
			] ],
			'',
			[ Context::TYPE_STATEMENT, Context::TYPE_QUALIFIER ]
		);

		$this->assertSame( [ Context::TYPE_QUALIFIER ], $parsed );
	}

	public function testParseConstraintParameter_InvalidScope() {
		$constraintScopeId = $this->getDefaultConfig()->get( 'WBQualityConstraintsConstraintScopeId' );
		$referencesId = new ItemId( $this->getDefaultConfig()->get( 'WBQualityConstraintsConstraintCheckedOnReferencesId' ) );
		$snak = new PropertyValueSnak( new PropertyId( $constraintScopeId ), new EntityIdValue( $referencesId ) );

		$this->assertThrowsConstraintParameterException(
			'parseConstraintScopeParameter',
			[
				[ $constraintScopeId => [
					$this->getSnakSerializer()->serialize( $snak ),
				] ],
				'',
				[ Context::TYPE_STATEMENT, Context::TYPE_QUALIFIER ]
			],
			'wbqc-violation-message-invalid-scope'
		);
	}

	public function testParseConstraintScopeParameter_UnknownScope() {
		$constraintScopeId = $this->getDefaultConfig()->get( 'WBQualityConstraintsConstraintScopeId' );
		$qualifiersId = new ItemId( $this->getDefaultConfig()->get( 'WBQualityConstraintsConstraintCheckedOnQualifiersId' ) );
		$otherScopeId = new ItemId( 'Q1' );
		$snak1 = new PropertyValueSnak( new PropertyId( $constraintScopeId ), new EntityIdValue( $qualifiersId ) );
		$snak2 = new PropertyValueSnak( new PropertyId( $constraintScopeId ), new EntityIdValue( $otherScopeId ) );

		$this->assertThrowsConstraintParameterException(
			'parseConstraintScopeParameter',
			[
				[ $constraintScopeId => [
					$this->getSnakSerializer()->serialize( $snak1 ),
					$this->getSnakSerializer()->serialize( $snak2 ),
				] ],
				'constraint'
			],
			'wbqc-violation-message-parameter-oneof'
		);
	}

}
