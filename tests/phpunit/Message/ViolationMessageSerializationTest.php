<?php

namespace WikibaseQuality\ConstraintReport\Tests\Message;

use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessage;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageDeserializer;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageSerializer;

/**
 * @group WikibaseQualityConstraints
 *
 * @author Lucas Werkmeister
 * @license GNU GPL v2+
 */
class ViolationMessageSerializationTest extends \PHPUnit_Framework_TestCase {

	/**
	 * @dataProvider provideViolationMessages
	 */
	public function testSerializeDeserialize( ViolationMessage $message ) {
		$serializer = new ViolationMessageSerializer();
		$deserializer = new ViolationMessageDeserializer();

		$serialized = $serializer->serialize( $message );
		$deserialized = $deserializer->deserialize( $serialized );

		$this->assertEquals( $message, $deserialized );
	}

	public function provideViolationMessages() {
		return [
			'no arguments' => [ new ViolationMessage( 'wbqc-violation-message-single-value' ) ],
		];
	}

}
