<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Message;

use Language;
use Message;

/**
 * Render a {@link ViolationMessage} into a localized string.
 *
 * @license GNU GPL v2+
 */
class ViolationMessageRenderer {

	/**
	 * @param ViolationMessage|string $violationMessage
	 * (temporarily, pre-rendered strings are allowed and returned without changes)
	 * @param Language|null $language language to use, defaulting to current user language
	 * @param string $format one of the Message::FORMAT_* constants
	 * @return string
	 */
	public function render(
		$violationMessage,
		$language = null,
		$format = Message::FORMAT_ESCAPED
	) {
		if ( is_string( $violationMessage ) ) {
			// TODO remove this once all checkers produce ViolationMessage objects
			return $violationMessage;
		}
		$message = new Message( $violationMessage->getMessageKey(), [], $language );
		return $message->toString( $format );
	}

}
