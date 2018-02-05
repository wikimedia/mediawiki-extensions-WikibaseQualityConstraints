<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Message;

use InvalidArgumentException;
use Serializers\Serializer;
use Wikibase\DataModel\Entity\EntityId;
use Wikimedia\Assert\Assert;

/**
 * A serializer for {@link ViolationMessage}s.
 *
 * @license GNU GPL v2+
 */
class ViolationMessageSerializer implements Serializer {

	private function abbreviateViolationMessageKey( $fullMessageKey ) {
		return substr( $fullMessageKey, strlen( ViolationMessage::MESSAGE_KEY_PREFIX ) );
	}

	/**
	 * @param ViolationMessage $object
	 * @return array
	 */
	public function serialize( $object ) {
		/** @var ViolationMessage $object */
		Assert::parameterType( ViolationMessage::class, $object, '$object' );

		$arguments = $object->getArguments();
		$serializedArguments = [];
		foreach ( $arguments as $argument ) {
			$serializedArguments[] = $this->serializeArgument( $argument );
		}

		return [
			'k' => $this->abbreviateViolationMessageKey( $object->getMessageKey() ),
			'a' => $serializedArguments,
		];
	}

	/**
	 * @param array $argument element of ViolationMessage::getArguments()
	 * @return array [ 't' => ViolationMessage::TYPE_*, 'v' => serialized value,
	 * 'r' => $role, (optional) 'a' => $alternativeMessageKey ]
	 */
	private function serializeArgument( array $argument ) {
		$methods = [
			ViolationMessage::TYPE_ENTITY_ID => 'serializeEntityId',
		];

		$type = $argument['type'];
		$value = $argument['value'];
		$role = $argument['role'];

		if ( array_key_exists( $type, $methods ) ) {
			$method = $methods[$type];
			$serializedValue = $this->$method( $value );
		} else {
			throw new InvalidArgumentException(
				'Unknown ViolationMessage argument type ' . $type . '!'
			);
		}

		$serialized = [
			't' => $type,
			'v' => $serializedValue,
			'r' => $role,
		];

		if ( array_key_exists( 'alternativeMessageKey', $argument ) ) {
			$serialized['a'] = $this->abbreviateViolationMessageKey(
				$argument['alternativeMessageKey']
			);
		}

		return $serialized;
	}

	/**
	 * @param EntityId $entityId
	 * @return string entity ID serialization
	 */
	private function serializeEntityId( EntityId $entityId ) {
		return $entityId->getSerialization();
	}

}
