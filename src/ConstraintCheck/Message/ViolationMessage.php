<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Message;

use InvalidArgumentException;

/**
 * A violation message of a constraint check.
 *
 * @license GNU GPL v2+
 */
class ViolationMessage {

	/**
	 * @private
	 */
	const MESSAGE_KEY_PREFIX = 'wbqc-violation-message-';

	/**
	 * @var string
	 */
	private $messageKeySuffix;

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
	}

	public function getMessageKey() {
		return self::MESSAGE_KEY_PREFIX . $this->messageKeySuffix;
	}

}
