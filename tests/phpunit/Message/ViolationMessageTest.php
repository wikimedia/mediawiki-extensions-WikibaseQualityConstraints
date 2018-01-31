<?php

namespace WikibaseQuality\ConstraintReport\Tests\Message;

use InvalidArgumentException;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ItemIdSnakValue;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessage;
use WikibaseQuality\ConstraintReport\Role;

/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessage
 *
 * @group WikibaseQualityConstraints
 *
 * @author Lucas Werkmeister
 * @license GNU GPL v2+
 */
class ViolationMessageTest extends \PHPUnit_Framework_TestCase {

	public function testGetMessageKey() {
		$messageKey = 'wbqc-violation-message-single-value';
		$violationMessage = new ViolationMessage( $messageKey );

		$this->assertSame( $messageKey, $violationMessage->getMessageKey() );
	}

	public function testConstruct_messageKeyWithoutPrefix() {
		$this->setExpectedException( InvalidArgumentException::class );
		new ViolationMessage( 'single-value' );
	}

	public function testConstruct_unrelatedMessageKey() {
		$this->setExpectedException( InvalidArgumentException::class );
		new ViolationMessage( 'wbqc-exception-message' );
	}

	public function testWithEntityId() {
		$value = new PropertyId( 'P1' );
		$role = Role::CONSTRAINT_PROPERTY;
		$message = ( new ViolationMessage( 'wbqc-violation-message-no-qualifiers' ) )
			->withEntityId( $value, $role );

		$this->assertSame(
			[ [ 'type' => ViolationMessage::TYPE_ENTITY_ID, 'role' => $role, 'value' => $value ] ],
			$message->getArguments()
		);
	}

	public function testWithEntityId_returnsClone() {
		$message1 = ( new ViolationMessage( 'wbqc-violation-message-no-qualifiers' ) );
		$message2 = $message1->withEntityId( new PropertyId( 'P1' ), Role::CONSTRAINT_PROPERTY );

		$this->assertNotSame( $message1, $message2 );
		$this->assertSame( [], $message1->getArguments() );
	}

	public function testWithEntityIdList() {
		$value = [ new ItemId( 'Q1' ), new PropertyId( 'P2' ) ];
		$role = Role::CONSTRAINT_PARAMETER_VALUE;
		$message = ( new ViolationMessage( 'wbqc-violation-message-unique-value' ) )
			->withEntityIdList( $value, $role );

		$this->assertSame(
			[ [ 'type' => ViolationMessage::TYPE_ENTITY_ID_LIST, 'role' => $role, 'value' => $value ] ],
			$message->getArguments()
		);
	}

	public function testWithEntityIdList_empty() {
		$value = [];
		$role = Role::CONSTRAINT_PARAMETER_VALUE;
		$message = ( new ViolationMessage( 'wbqc-violation-message-unique-value' ) )
			->withEntityIdList( $value, $role );

		$this->assertSame(
			[ [ 'type' => ViolationMessage::TYPE_ENTITY_ID_LIST, 'role' => $role, 'value' => $value ] ],
			$message->getArguments()
		);
	}

	public function testWithItemIdSnakValue() {
		$value = ItemIdSnakValue::noValue();
		$role = Role::OBJECT;
		$message = ( new ViolationMessage( 'wbqc-violation-message-conflicts-with-claim' ) )
			->withEntityId( new PropertyId( 'P1' ), Role::CONSTRAINT_PROPERTY )
			->withEntityId( new PropertyId( 'P2' ), Role::PREDICATE )
			->withItemIdSnakValue( $value, $role );

		$this->assertSame(
			[ 'type' => ViolationMessage::TYPE_ITEM_ID_SNAK_VALUE, 'role' => $role, 'value' => $value ],
			$message->getArguments()[2]
		);
	}

	public function testWithItemIdSnakValueList() {
		$value = [ ItemIdSnakValue::fromItemId( new ItemId( 'Q1' ) ), ItemIdSnakValue::noValue() ];
		$role = Role::CONSTRAINT_PARAMETER_VALUE;
		$message = ( new ViolationMessage( 'wbqc-violation-message-one-of' ) )
			->withEntityId( new ItemId( 'Q1' ) )
			->withItemIdSnakValueList( $value, $role );

		$this->assertSame(
			[ 'type' => ViolationMessage::TYPE_ITEM_ID_SNAK_VALUE_LIST, 'role' => $role, 'value' => $value ],
			$message->getArguments()[1]
		);
	}

	public function testWithItemIdSnakValueList_empty() {
		$value = [];
		$role = Role::CONSTRAINT_PARAMETER_VALUE;
		$message = ( new ViolationMessage( 'wbqc-violation-message-one-of' ) )
			->withEntityId( new ItemId( 'Q1' ) )
			->withItemIdSnakValueList( $value, $role );

		$this->assertSame(
			[ 'type' => ViolationMessage::TYPE_ITEM_ID_SNAK_VALUE_LIST, 'role' => $role, 'value' => $value ],
			$message->getArguments()[1]
		);
	}

}
