<?php

namespace WikibaseQuality\ConstraintReport\Tests\Api;

use WikibaseQuality\ConstraintReport\Api\ExpiryLock;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @covers \WikibaseQuality\ConstraintReport\Api\ExpiryLock
 *
 * @group WikibaseQualityConstraints
 *
 * @author Matthias Geisler
 * @license GPL-2.0-or-later
 */
class ExpiryLockTest extends \MediaWikiTestCase {

	public function tearDown() {
		ConvertibleTimestamp::setFakeTime( false );
		parent::tearDown();
	}

	public function testLockingSetsAHashKey() {
		$id = 'someLockName';
		$timestampString = '100010001';
		$timeStamp = new ConvertibleTimestamp( $timestampString );
		$cache = $this->createMock( \BagOStuff::class );
		$cache->expects( $this->once() )
			->method( 'set' )
			->with( $this->anything(), $timestampString, $timestampString )
			->willReturn( true );

		$lock = new ExpiryLock( $cache );
		$lock->lock( $id, $timeStamp );
	}

	public function testUnlockedIfCachedValueUnset() {
		$cache = $this->createMock( \BagOStuff::class );
		$lock = new ExpiryLock( $cache );
		$this->assertFalse( $lock->isLocked( 'fooId' ) );
	}

	public function testUnlockedIfCachedValueIsNotValidTimestamp() {
		$cache = $this->createMock( \BagOStuff::class );
		$cache->method( 'get' )
			->willReturn( 'foo' );
		$lock = new ExpiryLock( $cache );
		$this->assertFalse( $lock->isLocked( 'fooId' ) );
	}

	public function testUnlockedIfCurrentTimeIsAfterLockedTime() {
		$cache = $this->createMock( \BagOStuff::class );
		$cache->method( 'get' )
			->willReturn( '10' );

		ConvertibleTimestamp::setFakeTime( '11' );

		$lock = new ExpiryLock( $cache );
		$this->assertFalse( $lock->isLocked( 'fooId' ) );
	}

	public function testLockedIfCurrentTimeIsBeforeLockedTime() {
		$cache = $this->createMock( \BagOStuff::class );
		$cache->method( 'get' )
			->willReturn( '50' );

		ConvertibleTimestamp::setFakeTime( '40' );

		$lock = new ExpiryLock( $cache );
		$this->assertTrue( $lock->isLocked( 'fooId' ) );
	}

}
