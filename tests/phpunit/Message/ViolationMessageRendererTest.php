<?php

namespace WikibaseQuality\ConstraintReport\Tests\Message;

use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessage;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageRenderer;

/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageRenderer
 *
 * @group WikibaseQualityConstraints
 *
 * @author Lucas Werkmeister
 * @license GNU GPL v2+
 */
class ViolationMessageRendererTest extends \PHPUnit_Framework_TestCase {

	public function testRender_simpleMessage() {
		$messageKey = 'wbqc-violation-message-single-value';
		$message = new ViolationMessage( $messageKey );
		$renderer = new ViolationMessageRenderer();

		$rendered = $renderer->render( $message );

		$this->assertSame( wfMessage( $messageKey )->escaped(), $rendered );
	}

}
