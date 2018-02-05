<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Message;

use Deserializers\Deserializer;
use InvalidArgumentException;
use Wikimedia\Assert\Assert;

/**
 * A deserializer for {@link ViolationMessage}s.
 *
 * @license GNU GPL v2+
 */
class ViolationMessageDeserializer implements Deserializer {

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
	 * @param array $serializedArgument [ 't' => ViolationMessage::TYPE_*, 'v' => serialized value,
	 * 'r' => $role, (optional) 'a' => $alternativeMessageKey ]
	 * @return ViolationMessage $message with the deserialized argument appended
	 */
	private function deserializeArgument( ViolationMessage $message, array $serializedArgument ) {
		$methods = [
		];

		$type = $serializedArgument['t'];
		$serializedValue = $serializedArgument['v'];
		$role = $serializedArgument['r'];
		if ( array_key_exists( 'a', $serializedArgument ) ) {
			$alternativeMessageKey = $this->unabbreviateViolationMessageKey(
				$serializedArgument['a']
			);
		} else {
			$alternativeMessageKey = null;
		}

		if ( array_key_exists( $type, $methods ) ) {
			$method = $methods[$type];
			$value = $this->$method( $serializedValue );
		} else {
			throw new InvalidArgumentException(
				'Unknown ViolationMessage argument type ' . $type . '!'
			);
		}

		return $message->withArgument( $type, $role, $value, $alternativeMessageKey );
	}

}
