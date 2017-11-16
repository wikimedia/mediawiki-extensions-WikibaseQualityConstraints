<?php

namespace WikibaseQuality\ConstraintReport\Test\Cache;

use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachedBool;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachingMetadata;

/**
 * @covers \WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachedBool
 * @uses \WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachingMetadata
 *
 * @group WikibaseQualityConstraints
 *
 * @author Lucas Werkmeister
 * @license GNU GPL v2+
 */
class CachedBoolTest extends \PHPUnit_Framework_TestCase {

	public function testGetBool() {
		$bool = true;
		$cm = CachingMetadata::fresh();

		$cb = new CachedBool( $bool, $cm );

		$this->assertSame( $bool, $cb->getBool() );
	}

	public function testGetCachingMetadata() {
		$bool = false;
		$cm = CachingMetadata::ofMaximumAgeInSeconds( 42 );

		$cb = new CachedBool( $bool, $cm );

		$this->assertSame( $cm, $cb->getCachingMetadata() );
	}

}
