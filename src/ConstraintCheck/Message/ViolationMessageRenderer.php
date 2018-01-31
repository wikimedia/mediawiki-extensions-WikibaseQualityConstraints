<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Message;

use InvalidArgumentException;
use LogicException;
use Message;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Services\EntityId\EntityIdFormatter;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ItemIdSnakValue;

/**
 * Render a {@link ViolationMessage} into a localized string.
 *
 * @license GNU GPL v2+
 */
class ViolationMessageRenderer {

	/**
	 * @var EntityIdFormatter
	 */
	private $entityIdFormatter;

	/**
	 * @var int
	 */
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
	 * @return string
	 */
	public function render( $violationMessage ) {
		if ( is_string( $violationMessage ) ) {
			// TODO remove this once all checkers produce ViolationMessage objects
			return $violationMessage;
		}
		$message = new Message( $violationMessage->getMessageKey() );
		foreach ( $violationMessage->getArguments() as $argument ) {
			$this->renderArgument( $argument, $message );
		}
		return $message->escaped();
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
		$type = $argument['type'];
		$value = $argument['value'];
		$role = $argument['role'];
		switch ( $type ) {
			case ViolationMessage::TYPE_ENTITY_ID:
				$params = $this->renderEntityId( $value, $role );
				break;
			case ViolationMessage::TYPE_ENTITY_ID_LIST:
				$params = $this->renderEntityIdList( $value, $role );
				break;
			case ViolationMessage::TYPE_ITEM_ID_SNAK_VALUE:
				$params = $this->renderItemIdSnakValue( $value, $role );
				break;
			default:
				throw new InvalidArgumentException(
					'Unknown ViolationMessage argument type ' . $type . '!'
				);
		}
		$message->params( $params );
	}

	private function renderList( array $list, $role, callable $render ) {
		if ( $list === [] ) {
			return [
				Message::numParam( 0 ),
				Message::rawParam( '<ul></ul>' ),
			];
		}

		if ( count( $list ) > $this->maxListLength ) {
			$list = array_slice( $list, 0, $this->maxListLength );
			$truncated = true;
		}

		$renderedParams = array_map(
			$render,
			$list,
			array_fill( 0, count( $list ), $role )
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
				Message::numParam( count( $list ) ),
				Message::rawParam(
					'<ul><li>' .
					implode( '</li><li>', $renderedElements ) .
					'</li></ul>'
				),
			],
			$renderedParams
		);
	}

	private function renderEntityId( EntityId $entityId, $role ) {
		return Message::rawParam( $this->addRole(
			$this->entityIdFormatter->formatEntityId( $entityId ),
			$role
		) );
	}

	private function renderEntityIdList( array $entityIdList, $role ) {
		return $this->renderList( $entityIdList, $role, [ $this, 'renderEntityId' ] );
	}

	private function renderItemIdSnakValue( ItemIdSnakValue $value, $role ) {
		switch ( true ) {
			case $value->isValue():
				return $this->renderEntityId( $value->getItemId(), $role );
			case $value->isSomeValue():
				return Message::rawParam( $this->addRole(
					'<span class="wikibase-snakview-variation-somevaluesnak">' .
						wfMessage( 'wikibase-snakview-snaktypeselector-somevalue' )->escaped() .
						'</span>',
					$role
				) );
			case $value->isNoValue():
				return Message::rawParam( $this->addRole(
					'<span class="wikibase-snakview-variation-novaluesnak">' .
						wfMessage( 'wikibase-snakview-snaktypeselector-novalue' )->escaped() .
						'</span>',
					$role
				) );
			default:
				// @codeCoverageIgnoreStart
				throw new LogicException(
					'ItemIdSnakValue should guarantee that one of is{,Some,No}Value() is true'
				);
				// @codeCoverageIgnoreEnd
		}
	}

}
