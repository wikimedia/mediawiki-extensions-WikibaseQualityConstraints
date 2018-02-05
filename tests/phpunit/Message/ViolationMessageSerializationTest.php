<?php

namespace WikibaseQuality\ConstraintReport\Tests\Message;

use Wikibase\DataModel\Entity\BasicEntityIdParser;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
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
	 * @var ViolationMessageSerializer
	 */
	private $serializer;

	/**
	 * @var ViolationMessageDeserializer
	 */
	private $deserializer;

	public function setUp() {
		parent::setUp();
		$this->serializer = new ViolationMessageSerializer();
		$this->deserializer = new ViolationMessageDeserializer(
			new BasicEntityIdParser()
		);
	}

	/**
	 * @dataProvider provideViolationMessages
	 */
	public function testSerializeDeserialize( ViolationMessage $message ) {

		$serialized = $this->serializer->serialize( $message );
		$deserialized = $this->deserializer->deserialize( $serialized );

		$this->assertEquals( $message, $deserialized );
	}

	public function provideViolationMessages() {
		return [
			'no arguments' => [ new ViolationMessage( 'wbqc-violation-message-single-value' ) ],
			'entity ID' => [
				( new ViolationMessage( 'wbqc-violation-message-no-qualifiers' ) )
					->withEntityId( new PropertyId( 'P1' ) )
			],
			'entity ID list' => [
				( new ViolationMessage( 'wbqc-violation-message-unique-value' ) )
					->withEntityIdList( [ new ItemId( 'Q1' ), new PropertyId( 'P1' ) ] )
			],
		];
	}

}
