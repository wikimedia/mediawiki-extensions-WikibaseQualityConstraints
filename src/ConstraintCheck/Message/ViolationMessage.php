<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Message;

use InvalidArgumentException;
use Wikibase\DataModel\Entity\EntityId;

/**
 * A violation message of a constraint check.
 *
 * A ViolationMessage object is immutable:
 * operations like {@link withEntityId} return a modified copy.
 *
 * @license GNU GPL v2+
 */
class ViolationMessage {

	/**
	 * @private
	 */
	const MESSAGE_KEY_PREFIX = 'wbqc-violation-message-';

	/**
	 * Argument type for a single entity ID.
	 * Value type: {@link EntityId}
	 */
	const TYPE_ENTITY_ID = 'e';

	/**
	 * @var string
	 */
	private $messageKeySuffix;

	/**
	 * @var array[]
	 */
	private $arguments;

	/**
	 * @param string $messageKey The full message key. Must start with 'wbqc-violation-message-'.
	 * (We require callers to specify the full message key
	 * so that it’s easy to search for code that produces a given message.)
	 *
	 * @throws InvalidArgumentException If the message key is invalid.
	 */
	public function __construct(
		$messageKey
	) {
		if ( strpos( $messageKey, self::MESSAGE_KEY_PREFIX ) !== 0 ) {
			throw new InvalidArgumentException(
				'ViolationMessage key ⧼' .
				$messageKey .
				'⧽ should start with "' .
				self::MESSAGE_KEY_PREFIX .
				'".'
			);
		}

		$this->messageKeySuffix = substr( $messageKey, strlen( self::MESSAGE_KEY_PREFIX ) );
		$this->arguments = [];
	}

	/**
	 * Get the full message key of this message.
	 * @return string
	 */
	public function getMessageKey() {
		return self::MESSAGE_KEY_PREFIX . $this->messageKeySuffix;
	}

	/**
	 * Get the arguments to this message,
	 * a list of [ 'type' => self::TYPE_*, 'role' => Role::*, 'value' => $value ] elements.
	 * @return array[]
	 */
	public function getArguments() {
		return $this->arguments;
	}

	/**
	 * @param string $type one of the self::TYPE_* constants
	 * @param string|null $role one of the Role::* constants
	 * @param mixed $value the value, which should match the $type
	 * @return ViolationMessage
	 */
	private function withArgument( $type, $role, $value ) {
		$ret = clone $this;
		$ret->arguments[] = [ 'type' => $type, 'role' => $role, 'value' => $value ];
		return $ret;
	}

	/**
	 * Append a single entity ID to the message arguments.
	 * (This operation returns a modified copy, the original object is unchanged.)
	 *
	 * @param EntityId $entityId
	 * @param string|null $role one of the Role::* constants
	 * @return ViolationMessage
	 */
	public function withEntityId( EntityId $entityId, $role = null ) {
		return $this->withArgument( self::TYPE_ENTITY_ID, $role, $entityId );
	}

}
