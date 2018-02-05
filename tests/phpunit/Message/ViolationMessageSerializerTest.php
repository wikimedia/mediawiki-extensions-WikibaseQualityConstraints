<?php

namespace WikibaseQuality\ConstraintReport\Tests\Message;

use InvalidArgumentException;
use Wikibase\DataModel\Entity\PropertyId;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessage;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageSerializer;
use WikibaseQuality\ConstraintReport\Role;
use Wikimedia\TestingAccessWrapper;

/**
 * @group WikibaseQualityConstraints
 *
 * @author Lucas Werkmeister
 * @license GNU GPL v2+
 */
class ViolationMessageSerializerTest extends \PHPUnit_Framework_TestCase {

	/**
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageSerializer::serialize
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageSerializer::abbreviateViolationMessageKey
	 */
	public function testSerialize_noArguments() {
		$message = new ViolationMessage( 'wbqc-violation-message-single-value' );
		$serializer = new ViolationMessageSerializer();

		$serialized = $serializer->serialize( $message );

		$this->assertSame(
			[ 'k' => 'single-value', 'a' => [] ],
			$serialized
		);
	}

	/**
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageSerializer::serialize
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageSerializer::serializeArgument
	 */
	public function testSerialize_entityId_withRole() {
		$message = ( new ViolationMessage( 'wbqc-violation-message-no-qualifiers' ) )
			->withEntityId( new PropertyId( 'P1' ), Role::CONSTRAINT_PROPERTY );
		$serializer = new ViolationMessageSerializer();

		$serialized = $serializer->serialize( $message );

		$this->assertSame(
			[ 'k' => 'no-qualifiers', 'a' => [ [
				't' => ViolationMessage::TYPE_ENTITY_ID,
				'v' => 'P1',
				'r' => Role::CONSTRAINT_PROPERTY,
			] ] ],
			$serialized
		);
	}

	/**
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageSerializer::serialize
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageSerializer::serializeArgument
	 */
	public function testSerialize_unknownArgument() {
		$message = $this->getMockBuilder( ViolationMessage::class )
			->disableOriginalConstructor()
			->getMock();
		$message->method( 'getMessageKey' )
			->willReturn( 'wbqc-violation-message-unknown-argument-type' );
		$message->method( 'getArguments' )
			->willReturn( [ [ 'type' => 'unknown', 'value' => null, 'role' => null ] ] );
		$serializer = new ViolationMessageSerializer();

		$this->setExpectedException( InvalidArgumentException::class );
		$serializer->serialize( $message );
	}

	/**
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageSerializer::serializeEntityId
	 */
	public function testSerializeEntityId() {
		$entityId = new PropertyId( 'P1' );
		$serializer = new ViolationMessageSerializer();

		$serialized = TestingAccessWrapper::newFromObject( $serializer )
			->serializeEntityId( $entityId );

		$this->assertSame( 'P1', $serialized );
	}

}
