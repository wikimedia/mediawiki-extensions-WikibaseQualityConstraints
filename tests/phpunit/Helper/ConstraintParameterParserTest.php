<?php

namespace WikibaseQuality\ConstraintReport\Test\Helper;

use DataValues\StringValue;
use DataValues\TimeValue;
use DataValues\UnboundedQuantityValue;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Serializers\SnakSerializer;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Snak\PropertySomeValueSnak;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\Repo\WikibaseRepo;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterException;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikibaseQuality\ConstraintReport\Tests\ConstraintParameters;
use WikibaseQuality\ConstraintReport\Tests\ResultAssertions;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ItemIdSnakValue;

/**
 * @covers \WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser
 *
 * @group WikibaseQualityConstraints
 *
 * @author Lucas Werkmeister
 * @license GNU GPL v2+
 */
class ConstraintParameterParserTest extends \MediaWikiLangTestCase {

	use ConstraintParameters, ResultAssertions;

	/**
	 * @var SnakSerializer
	 */
	private $snakSerializer;

	/**
	 * @var Constraint
	 */
	private $constraint;

	protected function setUp() {
		parent::setUp();
		$this->snakSerializer = WikibaseRepo::getDefaultInstance()->getBaseDataModelSerializerFactory()->newSnakSerializer();
		$this->constraint = new Constraint( 'constraint ID', new PropertyId( 'P1' ), 'constraint type Q-ID', [] );
	}

	/**
	 * @param string $itemId
	 * @return array
	 */
	private function serializeItemId( $itemId ) {
		return $this->snakSerializer->serialize(
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
		return $this->snakSerializer->serialize(
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
				new ItemId( 'Q1' ),
				new Statement( new PropertyNoValueSnak( new PropertyId( 'P1' ) ) ),
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

	public function testParseClassParameterFromTemplate() {
		$parsed = $this->getConstraintParameterParser()->parseClassParameter(
			[ 'class' => 'Q100,Q101' ],
			''
		);
		$this->assertEquals( [ 'Q100', 'Q101' ], $parsed );
	}

	public function testParseClassParameterMissing() {
		$this->assertThrowsConstraintParameterException(
			'parseClassParameter',
			[ [], 'constraint' ],
			'wbqc-violation-message-parameter-needed'
		);
	}

	public function testParseClassParameterNoValue() {
		$config = $this->getDefaultConfig();
		$classId = $config->get( 'WBQualityConstraintsClassId' );
		$this->assertThrowsConstraintParameterException(
			'parseClassParameter',
			[
				[
					$classId => [
						$this->snakSerializer->serialize( new PropertyNoValueSnak( new PropertyId( $classId ) ) )
					]
				],
				'constraint'
			],
			'wbqc-violation-message-parameter-value'
		);
	}

	public function testParseClassParameterStringValue() {
		$config = $this->getDefaultConfig();
		$classId = $config->get( 'WBQualityConstraintsClassId' );
		$this->assertThrowsConstraintParameterException(
			'parseClassParameter',
			[
				[
					$classId => [
						$this->snakSerializer->serialize( new PropertyValueSnak(
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

	public function testParseRelationParameterFromTemplate() {
		$parsed = $this->getConstraintParameterParser()->parseRelationParameter(
			[ 'relation' => 'instance' ],
			''
		);
		$this->assertEquals( 'instance', $parsed );
	}

	public function testParseRelationParameterMissing() {
		$this->assertThrowsConstraintParameterException(
			'parseRelationParameter',
			[ [], 'constraint' ],
			'wbqc-violation-message-parameter-needed'
		);
	}

	public function testParseRelationParameterNoValue() {
		$config = $this->getDefaultConfig();
		$relationId = $config->get( 'WBQualityConstraintsRelationId' );
		$this->assertThrowsConstraintParameterException(
			'parseRelationParameter',
			[
				[
					$relationId => [
						$this->snakSerializer->serialize( new PropertyNoValueSnak( new PropertyId( $relationId ) ) )
					]
				],
				'constraint'
			],
			'wbqc-violation-message-parameter-value'
		);
	}

	public function testParseRelationParameterStringValue() {
		$config = $this->getDefaultConfig();
		$relationId = $config->get( 'WBQualityConstraintsRelationId' );
		$this->assertThrowsConstraintParameterException(
			'parseRelationParameter',
			[
				[
					$relationId => [
						$this->snakSerializer->serialize( new PropertyValueSnak(
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

	public function testParseRelationParameterMultiValue() {
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

	public function testParseRelationParameterWrongValue() {
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

	public function testParsePropertyParameterFromTemplate() {
		$parsed = $this->getConstraintParameterParser()->parsePropertyParameter(
			[ 'property' => 'P100' ],
			''
		);
		$this->assertEquals( new PropertyId( 'P100' ), $parsed );
	}

	public function testParsePropertyParameterMissing() {
		$this->assertThrowsConstraintParameterException(
			'parsePropertyParameter',
			[ [], 'constraint' ],
			'wbqc-violation-message-parameter-needed'
		);
	}

	public function testParsePropertyParameterNoValue() {
		$config = $this->getDefaultConfig();
		$propertyId = $config->get( 'WBQualityConstraintsPropertyId' );
		$this->assertThrowsConstraintParameterException(
			'parsePropertyParameter',
			[
				[
					$propertyId => [
						$this->snakSerializer->serialize( new PropertyNoValueSnak( new PropertyId( $propertyId ) ) )
					]
				],
				'constraint'
			],
			'wbqc-violation-message-parameter-value'
		);
	}

	public function testParsePropertyParameterStringValue() {
		$config = $this->getDefaultConfig();
		$propertyId = $config->get( 'WBQualityConstraintsPropertyId' );
		$this->assertThrowsConstraintParameterException(
			'parsePropertyParameter',
			[
				[
					$propertyId => [
						$this->snakSerializer->serialize( new PropertyValueSnak(
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

	public function testParsePropertyParameterItemId() {
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

	public function testParsePropertyParameterMultiValue() {
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
					$this->snakSerializer->serialize( new PropertySomeValueSnak( new PropertyId( 'P1' ) ) ),
					$this->snakSerializer->serialize( new PropertyNoValueSnak( new PropertyId( 'P1' ) ) )
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

	public function testParseItemsParameterRequired() {
		$this->assertThrowsConstraintParameterException(
			'parseItemsParameter',
			[
				[], 'constraint', true
			],
			'wbqc-violation-message-parameter-needed'
		);
	}

	public function testParseItemsParameterNotRequired() {
		$parsed = $this->getConstraintParameterParser()->parseItemsParameter( [], 'constraint', false );
		$this->assertEquals( [], $parsed );
	}

	public function testParseItemsParameterFromTemplate() {
		$parsed = $this->getConstraintParameterParser()->parseItemsParameter(
			[ 'item' => 'Q100,Q101,somevalue,novalue' ],
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

	public function testParseItemsParameterStringValue() {
		$config = $this->getDefaultConfig();
		$qualifierId = $config->get( 'WBQualityConstraintsQualifierOfPropertyConstraintId' );
		$this->assertThrowsConstraintParameterException(
			'parseItemsParameter',
			[
				[
					$qualifierId => [
						$this->snakSerializer->serialize( new PropertyValueSnak(
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

	public function testParseItemsParameterPropertyId() {
		$config = $this->getDefaultConfig();
		$qualifierId = $config->get( 'WBQualityConstraintsQualifierOfPropertyConstraintId' );
		$this->assertThrowsConstraintParameterException(
			'parseItemsParameter',
			[
				[
					$qualifierId => [
						$this->snakSerializer->serialize( new PropertyValueSnak(
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

	public function testParsePropertiesParameterFromTemplate() {
		$parsed = $this->getConstraintParameterParser()->parsePropertiesParameter(
			[ 'property' => 'p100,p101' ],
			''
		);
		$this->assertEquals( [ new PropertyId( 'P100' ), new PropertyId( 'P101' ) ], $parsed );
	}

	public function testParsePropertiesParameterMissing() {
		$this->assertThrowsConstraintParameterException(
			'parsePropertiesParameter',
			[ [], 'constraint' ],
			'wbqc-violation-message-parameter-needed'
		);
	}

	public function testParsePropertiesParameterNoValue() {
		$config = $this->getDefaultConfig();
		$propertyId = $config->get( 'WBQualityConstraintsPropertyId' );
		$parsed = $this->getConstraintParameterParser()->parsePropertiesParameter(
			[ $propertyId => [ $this->snakSerializer->serialize( new PropertyNoValueSnak( new PropertyId( $propertyId ) ) ) ] ],
			''
		);
		$this->assertEquals( [], $parsed );
	}

	public function testParseRangeParameterQuantityBounded() {
		$config = $this->getDefaultConfig();
		$minimumId = $config->get( 'WBQualityConstraintsMinimumQuantityId' );
		$maximumId = $config->get( 'WBQualityConstraintsMaximumQuantityId' );
		$propertyId = new PropertyId( 'P1' );
		$min = UnboundedQuantityValue::newFromNumber( 13.37 );
		$max = UnboundedQuantityValue::newFromNumber( 42 );

		$parsed = $this->getConstraintParameterParser()->parseRangeParameter(
			[
				$minimumId => [ $this->snakSerializer->serialize( new PropertyValueSnak( $propertyId, $min ) ) ],
				$maximumId => [ $this->snakSerializer->serialize( new PropertyValueSnak( $propertyId, $max ) ) ]
			],
			'',
			'quantity'
		);

		$this->assertEquals( [ $min, $max ], $parsed );
	}

	public function testParseRangeParameterQuantityLeftOpen() {
		$config = $this->getDefaultConfig();
		$minimumId = $config->get( 'WBQualityConstraintsMinimumQuantityId' );
		$maximumId = $config->get( 'WBQualityConstraintsMaximumQuantityId' );
		$propertyId = new PropertyId( 'P1' );
		$max = UnboundedQuantityValue::newFromNumber( 42 );

		$parsed = $this->getConstraintParameterParser()->parseRangeParameter(
			[
				$minimumId => [ $this->snakSerializer->serialize( new PropertyNoValueSnak( $propertyId ) ) ],
				$maximumId => [ $this->snakSerializer->serialize( new PropertyValueSnak( $propertyId, $max ) ) ]
			],
			'',
			'quantity'
		);

		$this->assertEquals( [ null, $max ], $parsed );
	}

	public function testParseRangeParameterQuantityRightOpen() {
		$config = $this->getDefaultConfig();
		$minimumId = $config->get( 'WBQualityConstraintsMinimumQuantityId' );
		$maximumId = $config->get( 'WBQualityConstraintsMaximumQuantityId' );
		$propertyId = new PropertyId( 'P1' );
		$min = UnboundedQuantityValue::newFromNumber( 13.37 );

		$parsed = $this->getConstraintParameterParser()->parseRangeParameter(
			[
				$minimumId => [ $this->snakSerializer->serialize( new PropertyValueSnak( $propertyId, $min ) ) ],
				$maximumId => [ $this->snakSerializer->serialize( new PropertyNoValueSnak( $propertyId ) ) ]
			],
			'',
			'quantity'
		);

		$this->assertEquals( [ $min, null ], $parsed );
	}

	public function testParseRangeParameterQuantityFullyOpen() {
		$config = $this->getDefaultConfig();
		$minimumId = $config->get( 'WBQualityConstraintsMinimumQuantityId' );
		$maximumId = $config->get( 'WBQualityConstraintsMaximumQuantityId' );
		$propertyId = new PropertyId( 'P1' );

		$parsed = $this->getConstraintParameterParser()->parseRangeParameter(
			[
				$minimumId => [ $this->snakSerializer->serialize( new PropertyNoValueSnak( $propertyId ) ) ],
				$maximumId => [ $this->snakSerializer->serialize( new PropertyNoValueSnak( $propertyId ) ) ]
			],
			'',
			'quantity'
		);

		$this->assertSame( [ null, null ], $parsed );
	}

	public function testParseRangeParameterQuantityFromTemplate() {
		$min = UnboundedQuantityValue::newFromNumber( 13.37 );
		$max = UnboundedQuantityValue::newFromNumber( 42 );

		$parsed = $this->getConstraintParameterParser()->parseRangeParameter(
			[
				'minimum_quantity' => '13.37',
				'maximum_quantity' => '42'
			],
			'',
			'quantity'
		);

		$this->assertEquals( [ $min, $max ], $parsed );
	}

	public function testParseRangeParameterQuantitySomeValue() {
		$config = $this->getDefaultConfig();
		$minimumId = $config->get( 'WBQualityConstraintsMinimumQuantityId' );
		$maximumId = $config->get( 'WBQualityConstraintsMaximumQuantityId' );
		$propertyId = new PropertyId( 'P1' );

		$this->assertThrowsConstraintParameterException(
			'parseRangeParameter',
			[
				[
					$minimumId => [ $this->snakSerializer->serialize( new PropertySomeValueSnak( $propertyId ) ) ],
					$maximumId => [ $this->snakSerializer->serialize( new PropertySomeValueSnak( $propertyId ) ) ]
				],
				'constraint',
				'quantity'
			],
			'wbqc-violation-message-parameter-value-or-novalue'
		);
	}

	public function testParseRangeParameterTimeBounded() {
		$config = $this->getDefaultConfig();
		$minimumId = $config->get( 'WBQualityConstraintsMinimumDateId' );
		$maximumId = $config->get( 'WBQualityConstraintsMaximumDateId' );
		$propertyId = new PropertyId( 'P1' );
		$calendar = 'http://www.wikidata.org/entity/Q1985727';
		$min = new TimeValue( '+1789-05-08T00:00:00Z', 0, 0, 0, TimeValue::PRECISION_YEAR, $calendar );
		$max = new TimeValue( '+1955-02-05T00:00:00Z', 0, 0, 0, TimeValue::PRECISION_YEAR, $calendar );

		$parsed = $this->getConstraintParameterParser()->parseRangeParameter(
			[
				$minimumId => [ $this->snakSerializer->serialize( new PropertyValueSnak( $propertyId, $min ) ) ],
				$maximumId => [ $this->snakSerializer->serialize( new PropertyValueSnak( $propertyId, $max ) ) ]
			],
			'',
			'time'
		);

		$this->assertEquals( [ $min, $max ], $parsed );
	}

	public function testParseRangeParameterTimeFromTemplate() {
		$calendar = 'http://www.wikidata.org/entity/Q1985727';
		$min = new TimeValue( '+1753-00-00T00:00:00Z', 0, 0, 0, TimeValue::PRECISION_YEAR, $calendar );
		$max = new TimeValue( gmdate( '+Y-m-d\T00:00:00\Z' ), 0, 0, 0, TimeValue::PRECISION_DAY, $calendar );

		$parsed = $this->getConstraintParameterParser()->parseRangeParameter(
			[
				'minimum_quantity' => '1753',
				'maximum_quantity' => 'now'
			],
			'',
			'time'
		);

		$this->assertEquals( [ $min, $max ], $parsed );
	}

	public function testParseRangeParameterMissingParameters() {
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
			[ $namespaceId => [ $this->snakSerializer->serialize( $snak ) ] ],
			''
		);

		$this->assertEquals( 'File', $parsed );
	}

	public function testParseNamespaceParameterMissing() {
		$parsed = $this->getConstraintParameterParser()->parseNamespaceParameter(
			[],
			''
		);

		$this->assertEquals( '', $parsed );
	}

	public function testParseNamespaceParameterFromTemplate() {
		$parsed = $this->getConstraintParameterParser()->parseNamespaceParameter(
			[ 'namespace' => 'File' ],
			''
		);

		$this->assertEquals( 'File', $parsed );
	}

	public function testParseNamespaceParameterItemId() {
		$namespaceId = $this->getDefaultConfig()->get( 'WBQualityConstraintsNamespaceId' );
		$value = new EntityIdValue( new ItemId( 'Q1' ) );
		$snak = new PropertyValueSnak( new PropertyId( 'P1' ), $value );

		$this->assertThrowsConstraintParameterException(
			'parseNamespaceParameter',
			[
				[ $namespaceId => [ $this->snakSerializer->serialize( $snak ) ] ],
				'constraint'
			],
			'wbqc-violation-message-parameter-string'
		);
	}

	public function testParseNamespaceParameterMultiple() {
		$namespaceId = $this->getDefaultConfig()->get( 'WBQualityConstraintsNamespaceId' );
		$value1 = new StringValue( 'File' );
		$snak1 = new PropertyValueSnak( new PropertyId( 'P1' ), $value1 );
		$value2 = new StringValue( 'Category' );
		$snak2 = new PropertyValueSnak( new PropertyId( 'P1' ), $value2 );

		$this->assertThrowsConstraintParameterException(
			'parseNamespaceParameter',
			[
				[ $namespaceId => [
					$this->snakSerializer->serialize( $snak1 ),
					$this->snakSerializer->serialize( $snak2 )
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
			[ $formatId => [ $this->snakSerializer->serialize( $snak ) ] ],
			''
		);

		$this->assertEquals( '\d\.(\d{1,2}|-{1})\.(\d{1,2}|-{1})\.(\d{1,3}|-{1})', $parsed );
	}

	public function testParseFormatParameterMissing() {
		$this->assertThrowsConstraintParameterException(
			'parseFormatParameter',
			[
				[],
				'constraint'
			],
			'wbqc-violation-message-parameter-needed'
		);
	}

	public function testParseFormatParameterFromTemplate() {
		$parsed = $this->getConstraintParameterParser()->parseFormatParameter(
			[ 'pattern' => '\d\.(\d{1,2}|-{1})\.(\d{1,2}|-{1})\.(\d{1,3}|-{1})' ],
			''
		);

		$this->assertEquals( '\d\.(\d{1,2}|-{1})\.(\d{1,2}|-{1})\.(\d{1,3}|-{1})', $parsed );
	}

	public function testParseFormatParameterFromTemplateHtmlEscaped() {
		$parsed = $this->getConstraintParameterParser()->parseFormatParameter(
			[ 'pattern' => '&lt;code>[1-9]\d{0,6}&lt;/code>' ], // pattern from https://www.wikidata.org/wiki/Property_talk:P1553
			''
		);

		$this->assertEquals( '[1-9]\d{0,6}', $parsed );
	}

	public function testParseFormatParameterItemId() {
		$formatId = $this->getDefaultConfig()->get( 'WBQualityConstraintsFormatAsARegularExpressionId' );
		$value = new EntityIdValue( new ItemId( 'Q1' ) );
		$snak = new PropertyValueSnak( new PropertyId( 'P1' ), $value );

		$this->assertThrowsConstraintParameterException(
			'parseFormatParameter',
			[
				[ $formatId => [ $this->snakSerializer->serialize( $snak ) ] ],
				'constraint'
			],
			'wbqc-violation-message-parameter-string'
		);
	}

	public function testParseFormatParameterMultiple() {
		$formatId = $this->getDefaultConfig()->get( 'WBQualityConstraintsFormatAsARegularExpressionId' );
		$value1 = new StringValue( '\d\.(\d{1,2}|-{1})\.(\d{1,2}|-{1})\.(\d{1,3}|-{1})' );
		$snak1 = new PropertyValueSnak( new PropertyId( 'P1' ), $value1 );
		$value2 = new StringValue( '\d+' );
		$snak2 = new PropertyValueSnak( new PropertyId( 'P1' ), $value2 );

		$this->assertThrowsConstraintParameterException(
			'parseFormatParameter',
			[
				[ $formatId => [
					$this->snakSerializer->serialize( $snak1 ),
					$this->snakSerializer->serialize( $snak2 )
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
				$this->snakSerializer->serialize( $snak1 ),
				$this->snakSerializer->serialize( $snak2 ),
			] ]
		);

		$this->assertEquals( [ $entityId1, $entityId2 ], $parsed );
	}

	public function testParseExceptionParameterMissing() {
		$parsed = $this->getConstraintParameterParser()->parseExceptionParameter(
			[]
		);

		$this->assertEquals( [], $parsed );
	}

	public function testParseExceptionParameterFromTemplate() {
		$parsed = $this->getConstraintParameterParser()->parseExceptionParameter(
			[ 'known_exception' => 'Q100,P100' ]
		);

		$this->assertEquals( [ new ItemId( 'Q100' ), new PropertyId( 'P100' ) ], $parsed );
	}

	public function testParseExceptionParameterFromTemplateLowercase() {
		$parsed = $this->getConstraintParameterParser()->parseExceptionParameter(
			[ 'known_exception' => 'q100,p100' ]
		);

		$this->assertEquals( [ new ItemId( 'Q100' ), new PropertyId( 'P100' ) ], $parsed );
	}

	public function testParseExceptionParameterString() {
		$exceptionId = $this->getDefaultConfig()->get( 'WBQualityConstraintsExceptionToConstraintId' );
		$snak = new PropertyValueSnak( new PropertyId( $exceptionId ), new StringValue( 'Q100' ) );

		$this->assertThrowsConstraintParameterException(
			'parseExceptionParameter',
			[ [ $exceptionId => [ $this->snakSerializer->serialize( $snak ) ] ] ],
			'wbqc-violation-message-parameter-entity'
		);
	}

	public function testParseExceptionParameterFromTemplateInvalid() {
		$this->assertThrowsConstraintParameterException(
			'parseExceptionParameter',
			[ [ 'known_exception' => 'Douglas Adams' ] ],
			'wbqc-violation-message-parameter-entity'
		);
	}

}
