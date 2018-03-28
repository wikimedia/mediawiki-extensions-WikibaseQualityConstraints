<?php

namespace WikibaseQuality\ConstraintReport\Tests\Fake;

use Message;
use MessageLocalizer;
use MessageSpecifier;

/**
 * A simple {@link MessageLocalizer} implementation for use in tests.
 *
 * @author Lucas Werkmeister
 * @license GPL-2.0-or-later
 */
class TestMessageLocalizer implements MessageLocalizer {

	/**
	 * @var string|null
	 */
	private $languageCode;

	/**
	 * @param string|null $languageCode
	 */
	public function __construct( $languageCode = null ) {
		$this->languageCode = $languageCode;
	}

	/**
	 * Get a Message object.
	 * Parameters are the same as {@link wfMessage()}.
	 *
	 * @param string|string[]|MessageSpecifier $key Message key, or array of keys,
	 *   or a MessageSpecifier.
	 * @param mixed $args,...
	 * @return Message
	 */
	public function msg( $key ) {
		$args = func_get_args();

		/** @var Message $message */
		$message = call_user_func_array( 'wfMessage', $args );
		if ( $this->languageCode !== null ) {
			$message->inLanguage( $this->languageCode );
		}
		return $message;
	}

}
