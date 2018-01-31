<?php

namespace WikibaseQuality\ConstraintReport\Tests\Message;

use Message;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\EntityId\EntityIdFormatter;
use Wikibase\DataModel\Services\EntityId\PlainEntityIdFormatter;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessage;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageRenderer;
use WikibaseQuality\ConstraintReport\Role;
use Wikimedia\TestingAccessWrapper;

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
		$renderer = new ViolationMessageRenderer( new PlainEntityIdFormatter() );

		$rendered = $renderer->render( $message );

		$this->assertSame( wfMessage( $messageKey )->escaped(), $rendered );
	}

	public function testRender_entityId() {
		$messageKey = 'wbqc-violation-message-no-qualifiers';
		$entityId = new PropertyId( 'P1' );
		$message = ( new ViolationMessage( $messageKey ) )
			->withEntityId( $entityId );
		$renderer = new ViolationMessageRenderer( new PlainEntityIdFormatter() );

		$rendered = $renderer->render( $message );

		$expected = wfMessage( $messageKey )
			->rawParams( 'P1' )
			->escaped();
		$this->assertSame( $expected, $rendered );
	}

	public function testRenderEntityId() {
		$entityId = new ItemId( 'Q1' );
		$role = null;
		$entityIdFormatter = $this->getMock( EntityIdFormatter::class );
		$entityIdFormatter->expects( $this->once() )
			->method( 'formatEntityId' )
			->with( $entityId )
			->willReturn( '<test property>' );
		$renderer = new ViolationMessageRenderer( $entityIdFormatter );

		$params = TestingAccessWrapper::newFromObject( $renderer )
			->renderEntityId( $entityId, $role );

		$this->assertSame(
			Message::rawParam( '<test property>' ),
			$params
		);
	}

	public function testRenderEntityId_withRole() {
		$entityId = new PropertyId( 'P1' );
		$role = Role::PREDICATE;
		$entityIdFormatter = $this->getMock( EntityIdFormatter::class );
		$entityIdFormatter->expects( $this->once() )
			->method( 'formatEntityId' )
			->with( $entityId )
			->willReturn( '<test property>' );
		$renderer = new ViolationMessageRenderer( $entityIdFormatter );

		$params = TestingAccessWrapper::newFromObject( $renderer )
			->renderEntityId( $entityId, $role );

		$this->assertSame(
			Message::rawParam( '<span class="wbqc-role wbqc-role-predicate"><test property></span>' ),
			$params
		);
	}

}
