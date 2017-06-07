<?php

namespace WikibaseQuality\ConstraintReport\Test\Helper;

use DataValues\StringValue;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Serializers\SnakSerializer;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Snak\PropertySomeValueSnak;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\Repo\WikibaseRepo;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterException;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintStatementParameterParser;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikibaseQuality\ConstraintReport\Tests\ConstraintParameters;
use WikibaseQuality\ConstraintReport\Tests\ResultAssertions;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ItemIdSnakValue;

/**
 * @covers \WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintStatementParameterParser
 *
 * @group WikibaseQualityConstraints
 *
 * @author Lucas Werkmeister
 * @license GNU GPL v2+
 */
class ConstraintStatementParameterParserTest extends \MediaWikiLangTestCase {

	use ConstraintParameters, ResultAssertions;

	/**
	 * @var SnakSerializer
	 */
	private $snakSerializer;

	protected function setUp() {
		parent::setUp();
		$this->snakSerializer = WikibaseRepo::getDefaultInstance()->getSerializerFactory()->newSnakSerializer();
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
				'constraint type Q-ID',
				'constraint ID',
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

}
