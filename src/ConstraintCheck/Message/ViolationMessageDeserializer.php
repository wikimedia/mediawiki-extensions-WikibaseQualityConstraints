<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Message;

use DataValues\DataValue;
use DataValues\DataValueFactory;
use Deserializers\Deserializer;
use InvalidArgumentException;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\EntityIdParser;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ItemIdSnakValue;
use Wikimedia\Assert\Assert;

/**
 * A deserializer for {@link ViolationMessage}s.
 *
 * @license GNU GPL v2+
 */
class ViolationMessageDeserializer implements Deserializer {

	/**
	 * @var EntityIdParser
	 */
	private $entityIdParser;

	/**
	 * @var DataValueFactory
	 */
	private $dataValueFactory;

	public function __construct(
		EntityIdParser $entityIdParser,
		DataValueFactory $dataValueFactory
	) {
		$this->entityIdParser = $entityIdParser;
		$this->dataValueFactory = $dataValueFactory;
	}

	public function unabbreviateViolationMessageKey( $messageKeySuffix ) {
		return ViolationMessage::MESSAGE_KEY_PREFIX . $messageKeySuffix;
	}

	/**
	 * @param array $serialization
	 * @return ViolationMessage
	 */
	public function deserialize( $serialization ) {
		Assert::parameterType( 'array', $serialization, '$serialization' );

		$message = new ViolationMessage(
			$this->unabbreviateViolationMessageKey( $serialization['k'] )
		);

		foreach ( $serialization['a'] as $serializedArgument ) {
			$message = $this->deserializeArgument( $message, $serializedArgument );
		}

		return $message;
	}

	/**
	 * @param ViolationMessage $message
	 * @param array $serializedArgument [ 't' => ViolationMessage::TYPE_*, 'v' => serialized value, 'r' => $role ]
	 * @return ViolationMessage $message with the deserialized argument appended
	 */
	private function deserializeArgument( ViolationMessage $message, array $serializedArgument ) {
		$methods = [
			ViolationMessage::TYPE_ENTITY_ID => 'deserializeEntityId',
			ViolationMessage::TYPE_ENTITY_ID_LIST => 'deserializeEntityIdList',
			ViolationMessage::TYPE_ITEM_ID_SNAK_VALUE => 'deserializeItemIdSnakValue',
			ViolationMessage::TYPE_ITEM_ID_SNAK_VALUE_LIST => 'deserializeItemIdSnakValueList',
			ViolationMessage::TYPE_DATA_VALUE => 'deserializeDataValue',
		];

		$type = $serializedArgument['t'];
		$serializedValue = $serializedArgument['v'];
		$role = $serializedArgument['r'];

		if ( array_key_exists( $type, $methods ) ) {
			$method = $methods[$type];
			$value = $this->$method( $serializedValue );
		} else {
			throw new InvalidArgumentException(
				'Unknown ViolationMessage argument type ' . $type . '!'
			);
		}

		return $message->withArgument( $type, $role, $value );
	}

	/**
	 * @param string $entityIdSerialization entity ID serialization
	 * @return EntityId
	 */
	private function deserializeEntityId( $entityIdSerialization ) {
		return $this->entityIdParser->parse( $entityIdSerialization );
	}

	/**
	 * @param string[] $entityIdSerializations entity ID serializations
	 * @return EntityId[]
	 */
	private function deserializeEntityIdList( array $entityIdSerializations ) {
		return array_map( [ $this, 'deserializeEntityId' ], $entityIdSerializations );
	}

	/**
	 * @param string $valueSerialization entity ID serialization, '::somevalue' or '::novalue'
	 * @return ItemIdSnakValue
	 */
	private function deserializeItemIdSnakValue( $valueSerialization ) {
		switch ( $valueSerialization ) {
			case '::somevalue':
				return ItemIdSnakValue::someValue();
			case '::novalue':
				return ItemIdSnakValue::noValue();
			default:
				return ItemIdSnakValue::fromItemId( $this->deserializeEntityId( $valueSerialization ) );
		}
	}

	/**
	 * @param string[] $valueSerializations entity ID serializations, '::somevalue's or '::novalue's
	 * @return ItemIdSnakValue[]
	 */
	private function deserializeItemIdSnakValueList( $valueSerializations ) {
		return array_map( [ $this, 'deserializeItemIdSnakValue' ], $valueSerializations );
	}

	/**
	 * @param array $dataValueSerialization the data value in array form
	 * @return DataValue
	 */
	private function deserializeDataValue( array $dataValueSerialization ) {
		return $this->dataValueFactory->newFromArray( $dataValueSerialization );
	}

}
