<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Helper;

use Exception;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessage;

/**
 * Exception thrown when a constraint’s parameters are invalid.
 *
 * @author Lucas Werkmeister
 * @license GNU GPL v2+
 */
class ConstraintParameterException extends Exception {

	/**
	 * @param string $message HTML
	 */
	public function __construct( $message ) {
		parent::__construct( $message );
	}

	/**
	 * @return ViolationMessage|string
	 */
	public function getViolationMessage() {
		return $this->getMessage();
	}

}
