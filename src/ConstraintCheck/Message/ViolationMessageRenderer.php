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
	 * @param ViolationMessage $violationMessage
	 * @param Language|null $language language to use, defaulting to current user language
	 * @param string $format one of the Message::FORMAT_* constants
	 * @return string
	 */
	public function render(
		ViolationMessage $violationMessage,
		$language = null,
		$format = Message::FORMAT_ESCAPED
	) {
		$message = new Message( $violationMessage->getMessageKey(), [], $language );
		return $message->toString( $format );
	}

}
