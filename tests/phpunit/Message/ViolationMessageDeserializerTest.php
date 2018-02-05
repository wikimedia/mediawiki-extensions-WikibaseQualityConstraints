<?php

namespace WikibaseQuality\ConstraintReport\Tests\Message;

use InvalidArgumentException;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageDeserializer;

/**
 * @group WikibaseQualityConstraints
 *
 * @author Lucas Werkmeister
 * @license GNU GPL v2+
 */
class ViolationMessageDeserializerTest extends \PHPUnit_Framework_TestCase {

	/**
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageDeserializer::deserialize
	 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageDeserializer::unabbreviateViolationMessageKey
	 */
	public function testDeserialize_noArguments() {
		$serialized = [ 'k' => 'single-value', 'a' => [] ];
		$deserializer = new ViolationMessageDeserializer();

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
	public function testDeserialize_unknownArgument() {
		$serialized = [
			'k' => 'unknown-argument-type',
			'a' => [ [
				't' => 'unknown',
				'v' => null,
				'r' => null,
			] ],
		];
		$deserializer = new ViolationMessageDeserializer();

		$this->setExpectedException( InvalidArgumentException::class );
		$deserializer->deserialize( $serialized );
	}

}
