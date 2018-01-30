<?php

namespace WikibaseQuality\ConstraintReport\Tests\Message;

use InvalidArgumentException;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessage;

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

}
