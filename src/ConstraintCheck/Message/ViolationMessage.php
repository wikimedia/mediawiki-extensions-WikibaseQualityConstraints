<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Message;

use DataValues\DataValue;
use InvalidArgumentException;
use Wikibase\DataModel\Entity\EntityId;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ItemIdSnakValue;

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
	 * Argument type for a list of entity IDs.
	 * Value type: {@link EntityId}[]
	 */
	const TYPE_ENTITY_ID_LIST = 'E';

	/**
	 * Argument type for an item ID, “unknown value”, or “no value”.
	 * Value type: {@link ItemIdSnakValue}
	 */
	const TYPE_ITEM_ID_SNAK_VALUE = 'i';

	/**
	 * Argument type for a list of item IDs, “unknown value”s, or “no value”s.
	 * Value type: {@link ItemIdSnakValue}[]
	 */
	const TYPE_ITEM_ID_SNAK_VALUE_LIST = 'I';

	/**
	 * Argument type for a single data value.
	 * Value type: {@link DataValue}
	 */
	const TYPE_DATA_VALUE = 'v';

	/**
	 * Argument type for a data value type, like "time" or "wikibase-entityid".
	 * (Not to be confused with a data type, like "time" or "wikibase-item".)
	 * Value type: string
	 */
	const TYPE_DATA_VALUE_TYPE = 't';

	/**
	 * Argument type for a short fragment of inline computer code.
	 * Value type: string
	 */
	const TYPE_INLINE_CODE = 'c';

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

	/**
	 * Append a list of entity IDs to the message arguments.
	 * (This operation returns a modified copy, the original object is unchanged.)
	 *
	 * This is not the same as appending the list elements individually with {@link withEntityId}!
	 * In the final message, this corresponds to
	 * one parameter for the number of list elements,
	 * one parameter with an HTML list of the list elements,
	 * and then one parameter per entity ID.
	 *
	 * @param EntityId[] $entityIdList
	 * @param string|null $role one of the Role::* constants
	 * @return ViolationMessage
	 */
	public function withEntityIdList( array $entityIdList, $role = null ) {
		return $this->withArgument( self::TYPE_ENTITY_ID_LIST, $role, $entityIdList );
	}

	/**
	 * Append a single item ID, “unknown value”, or “no value” to the message arguments.
	 * (This operation returns a modified copy, the original object is unchanged.)
	 *
	 * @param ItemIdSnakValue $value
	 * @param string|null $role one of the Role::* constants
	 * @return ViolationMessage
	 */
	public function withItemIdSnakValue( ItemIdSnakValue $value, $role = null ) {
		return $this->withArgument( self::TYPE_ITEM_ID_SNAK_VALUE, $role, $value );
	}

	/**
	 * Append a list of item IDs, “unknown value”s, or “no value”s to the message arguments.
	 * (This operation returns a modified copy, the original object is unchanged.)
	 *
	 * This is not the same as appending the list elements individually with {@link withItemIdSnakValue}!
	 * In the final message, this corresponds to
	 * one parameter for the number of list elements,
	 * one parameter with an HTML list of the list elements,
	 * and then one parameter per value.
	 *
	 * @param ItemIdSnakValue[] $valueList
	 * @param string|null $role one of the Role::* constants
	 * @return ViolationMessage
	 */
	public function withItemIdSnakValueList( array $valueList, $role = null ) {
		return $this->withArgument( self::TYPE_ITEM_ID_SNAK_VALUE_LIST, $role, $valueList );
	}

	/**
	 * Append a single data value to the message arguments.
	 * (This operation returns a modified copy, the original object is unchanged.)
	 *
	 * @param DataValue $dataValue
	 * @param string|null $role one of the Role::* constants
	 * @return ViolationMessage
	 */
	public function withDataValue( DataValue $dataValue, $role = null ) {
		return $this->withArgument( self::TYPE_DATA_VALUE, $role, $dataValue );
	}

	/**
	 * Append a single data value type, like "time" or "wikibase-entityid".
	 * (This operation returns a modified copy, the original object is unchanged.)
	 *
	 * Data value types should not be confused with data types, like "time" or "wikibase-item".
	 * For example, "wikibase-entityid" is the data value type
	 * used by the data types "wikibase-item" and "wikibase-property".
	 *
	 * @param string $dataValueType
	 * @param string|null $role one of the Role::* constants
	 * @return ViolationMessage
	 */
	public function withDataValueType( $dataValueType, $role = null ) {
		return $this->withArgument( self::TYPE_DATA_VALUE_TYPE, $role, $dataValueType );
	}

	/**
	 * Append a single short fragment of inline computer code to the message arguments.
	 * (This operation returns a modified copy, the original object is unchanged.)
	 *
	 * @param string $code
	 * @param string|null $role one of the Role::* constants
	 * @return ViolationMessage
	 */
	public function withInlineCode( $code, $role = null ) {
		return $this->withArgument( self::TYPE_INLINE_CODE, $role, $code );
	}

}
