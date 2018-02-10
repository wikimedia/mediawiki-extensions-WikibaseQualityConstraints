<?php

namespace WikibaseQuality\ConstraintReport\Tests\Message;

use InvalidArgumentException;
use Wikibase\DataModel\Entity\BasicEntityIdParser;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ItemIdSnakValue;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessage;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageDeserializer;
use WikibaseQuality\ConstraintReport\Role;
use Wikimedia\TestingAccessWrapper;

/**
 * @group WikibaseQualityConstraints
 *
 * @author Lucas Werkmeister
 * @license GNU GPL v2+
 */
class ViolationMessageDeserializerTest extends \PHPUnit_Framework_TestCase {

	private function getViolationMessageDeserializer(
		EntityIdParser $entityIdParser = null
	) {
		if ( $entityIdParser === null ) {
			$entityIdParser = new BasicEntityIdParser();
		}

		return new ViolationMessageDeserializer( $entityIdParser );
	}

	/**
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageDeserializer::deserialize
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageDeserializer::unabbreviateViolationMessageKey
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageDeserializer::__construct
	 */
	public function testDeserialize_noArguments() {
		$serialized = [ 'k' => 'single-value', 'a' => [] ];
		$deserializer = $this->getViolationMessageDeserializer();

		$message = $deserializer->deserialize( $serialized );

		$this->assertSame(
			'wbqc-violation-message-single-value',
			$message->getMessageKey()
		);
		$this->assertSame( [], $message->getArguments() );
	}

	/**
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageDeserializer::deserialize
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageDeserializer::deserializeArgument
	 */
	public function testDeserialize_entityId() {
		$serialized = [ 'k' => 'no-qualifiers', 'a' => [ [
			't' => ViolationMessage::TYPE_ENTITY_ID,
			'v' => 'P1',
			'r' => Role::CONSTRAINT_PROPERTY,
		] ] ];
		$deserializer = $this->getViolationMessageDeserializer();

		$message = $deserializer->deserialize( $serialized );
		$this->assertEquals(
			[ [
				'type' => ViolationMessage::TYPE_ENTITY_ID,
				'role' => Role::CONSTRAINT_PROPERTY,
				'value' => new PropertyId( 'P1' ),
			] ],
			$message->getArguments()
		);
	}

	/**
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageDeserializer::deserialize
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageDeserializer::deserializeArgument
	 */
	public function testDeserialize_unknownArgument() {
		$serialized = [
			'k' => 'unknown-argument-type',
			'a' => [ [
				't' => 'unknown',
				'v' => null,
				'r' => null,
			] ],
		];
		$deserializer = $this->getViolationMessageDeserializer();

		$this->setExpectedException( InvalidArgumentException::class );
		$deserializer->deserialize( $serialized );
	}

	/**
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageDeserializer::deserializeEntityId
	 */
	public function testDeserializeEntityId() {
		$propertyId = new PropertyId( 'P1' );
		$mock = $this->getMock( EntityIdParser::class );
		$mock->expects( $this->once() )
			->method( 'parse' )
			->with( 'P1' )
			->willReturn( $propertyId );
		$deserializer = $this->getViolationMessageDeserializer( $mock );

		$deserialized = TestingAccessWrapper::newFromObject( $deserializer )
			->deserializeEntityId( 'P1' );

		$this->assertSame( $propertyId, $deserialized );
	}

	/**
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageDeserializer::deserializeEntityIdList
	 */
	public function testDeserializeEntityIdList() {
		$entityIdSerializations = [ 'Q1', 'P1' ];
		$deserializer = $this->getViolationMessageDeserializer();

		$deserialized = TestingAccessWrapper::newFromObject( $deserializer )
			->deserializeEntityIdList( $entityIdSerializations );

		$this->assertEquals( [ new ItemId( 'Q1' ), new PropertyId( 'P1' ) ], $deserialized );
	}

	/**
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageDeserializer::deserializeEntityIdList
	 */
	public function testDeserializeEntityIdList_Empty() {
		$entityIdSerializations = [];
		$deserializer = $this->getViolationMessageDeserializer();

		$deserialized = TestingAccessWrapper::newFromObject( $deserializer )
			->deserializeEntityIdList( $entityIdSerializations );

		$this->assertEquals( [], $deserialized );
	}

	/**
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageDeserializer::deserializeItemIdSnakValue
	 */
	public function testDeserializeItemIdSnakValue_itemId() {
		$serialization = 'Q1';
		$deserializer = $this->getViolationMessageDeserializer();

		/** @var ItemIdSnakValue $deserialized */
		$deserialized = TestingAccessWrapper::newFromObject( $deserializer )
			->deserializeItemIdSnakValue( $serialization );

		$this->assertTrue( $deserialized->isValue() );
		$this->assertEquals( new ItemId( 'Q1' ), $deserialized->getItemId() );
	}

	/**
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageDeserializer::deserializeItemIdSnakValue
	 */
	public function testDeserializeItemIdSnakValue_someValue() {
		$serialization = '::somevalue';
		$deserializer = $this->getViolationMessageDeserializer();

		/** @var ItemIdSnakValue $deserialized */
		$deserialized = TestingAccessWrapper::newFromObject( $deserializer )
			->deserializeItemIdSnakValue( $serialization );

		$this->assertTrue( $deserialized->isSomeValue() );
	}

	/**
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageDeserializer::deserializeItemIdSnakValue
	 */
	public function testDeserializeItemIdSnakValue_noValue() {
		$serialization = '::novalue';
		$deserializer = $this->getViolationMessageDeserializer();

		/** @var ItemIdSnakValue $deserialized */
		$deserialized = TestingAccessWrapper::newFromObject( $deserializer )
			->deserializeItemIdSnakValue( $serialization );

		$this->assertTrue( $deserialized->isNoValue() );
	}

	/**
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageDeserializer::deserializeItemIdSnakValueList
	 */
	public function testDeserializeItemIdSnakValueList() {
		$serializations = [ 'Q1', '::somevalue', '::novalue' ];
		$deserializer = $this->getViolationMessageDeserializer();

		/** @var ItemIdSnakValue[] $deserialized */
		$deserialized = TestingAccessWrapper::newFromObject( $deserializer )
			->deserializeItemIdSnakValueList( $serializations );

		$this->assertCount( 3, $deserialized );
		$this->assertTrue( $deserialized[0]->isValue() );
		$this->assertEquals( new ItemId( 'Q1' ), $deserialized[0]->getItemId() );
		$this->assertTrue( $deserialized[1]->isSomeValue() );
		$this->assertTrue( $deserialized[2]->isNoValue() );
	}

	/**
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageDeserializer::deserializeItemIdSnakValueList
	 */
	public function testDeserializeItemIdSnakValueList_empty() {
		$serializations = [];
		$deserializer = $this->getViolationMessageDeserializer();

		$deserialized = TestingAccessWrapper::newFromObject( $deserializer )
			->deserializeItemIdSnakValueList( $serializations );

		$this->assertSame( [], $deserialized );
	}

}
