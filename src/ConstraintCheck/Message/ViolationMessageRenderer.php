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

	private $maxListLength;

	/**
	 * @param EntityIdFormatter $entityIdFormatter
	 * @param int $maxListLength The maximum number of elements to be rendered in a list parameter.
	 * Longer lists are truncated to this length and then rendered with an ellipsis in the HMTL list.
	 */
	public function __construct(
		EntityIdFormatter $entityIdFormatter,
		$maxListLength = 10
	) {
		$this->entityIdFormatter = $entityIdFormatter;
		$this->maxListLength = $maxListLength;
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
			case ViolationMessage::TYPE_ENTITY_ID_LIST:
				$params = $this->renderEntityIdList( $argument['value'], $argument['role'] );
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

	private function renderEntityIdList( array $entityIdList, $role ) {
		if ( $entityIdList === [] ) {
			return [
				Message::numParam( 0 ),
				Message::rawParam( '<ul></ul>' ),
			];
		}

		if ( count( $entityIdList ) > $this->maxListLength ) {
			$entityIdList = array_slice( $entityIdList, 0, $this->maxListLength );
			$truncated = true;
		}

		$renderedParams = array_map(
			[ $this, 'renderEntityId' ],
			$entityIdList,
			array_fill( 0, count( $entityIdList ), $role )
		);
		$renderedElements = array_map(
			function ( $param ) {
				return $param['raw'];
			},
			$renderedParams
		);
		if ( isset( $truncated ) ) {
			$renderedElements[] = wfMessage( 'ellipsis' )->escaped();
		}

		return array_merge(
			[
				Message::numParam( count( $entityIdList ) ),
				Message::rawParam(
					'<ul><li>' .
					implode( '</li><li>', $renderedElements ) .
					'</li></ul>'
				),
			],
			$renderedParams
		);
	}

}
