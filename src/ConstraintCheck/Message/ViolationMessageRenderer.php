<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Message;

use InvalidArgumentException;
use Language;
use Message;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Services\EntityId\EntityIdFormatter;

/**
 * Render a {@link ViolationMessage} into a localized string.
 *
 * @license GNU GPL v2+
 */
class ViolationMessageRenderer {

	private $entityIdFormatter;

	public function __construct(
		EntityIdFormatter $entityIdFormatter
	) {
		$this->entityIdFormatter = $entityIdFormatter;
	}

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
		foreach ( $violationMessage->getArguments() as $argument ) {
			$this->renderArgument( $argument, $message );
		}
		return $message->toString( $format );
	}

	private function addRole( $value, $role ) {
		if ( $role === null ) {
			return $value;
		}

		return '<span class="wbqc-role wbqc-role-' . htmlspecialchars( $role ) . '">' .
			$value .
			'</span>';
	}

	private function renderArgument( array $argument, Message $message ) {
		switch ( $argument['type'] ) {
			case ViolationMessage::TYPE_ENTITY_ID:
				$params = $this->renderEntityId( $argument['value'], $argument['role'] );
				break;
			default:
				throw new InvalidArgumentException(
					'Unknown ViolationMessage argument type ' . $argument['type'] . '!'
				);
		}
		$message->params( $params );
	}

	private function renderEntityId( EntityId $entityId, $role ) {
		return Message::rawParam( $this->addRole(
			$this->entityIdFormatter->formatEntityId( $entityId ),
			$role
		) );
	}

}
