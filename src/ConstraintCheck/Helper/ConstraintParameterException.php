<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Helper;

use Exception;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessage;

/**
 * Exception thrown when a constraint’s parameters are invalid.
 *
 * @author Lucas Werkmeister
 * @license GPL-2.0-or-later
 */
class ConstraintParameterException extends Exception {

	/**
	 * @var ViolationMessage|string
	 */
	private $violationMessage;

	/**
	 * @param ViolationMessage|string $violationMessage
	 */
	public function __construct( $violationMessage ) {
		if ( $violationMessage instanceof ViolationMessage ) {
			$message = '⧼' . $violationMessage->getMessageKey() . '⧽';
		} else {
			$message = $violationMessage;
		}
		parent::__construct( $message );

		// TODO remove support for string $violationMessage (see ViolationMessageRenderer::render())
		$this->violationMessage = $violationMessage;
	}

	/**
	 * @return ViolationMessage|string
	 */
	public function getViolationMessage() {
		return $this->violationMessage;
	}

}
