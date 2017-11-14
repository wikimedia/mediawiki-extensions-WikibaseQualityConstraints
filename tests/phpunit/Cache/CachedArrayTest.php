<?php

namespace WikibaseQuality\ConstraintReport\Test\Cache;

use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachedArray;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachingMetadata;

/**
 * @covers \WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachedArray
 * @uses \WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachingMetadata
 *
 * @group WikibaseQualityConstraints
 *
 * @author Lucas Werkmeister
 * @license GNU GPL v2+
 */
class CachedArrayTest extends \PHPUnit_Framework_TestCase {

	public function testGetArray() {
		$array = [ 'array' => true ];
		$cm = CachingMetadata::fresh();

		$ca = new CachedArray( $array, $cm );

		$this->assertSame( $array, $ca->getArray() );
	}

	public function testGetCachingMetadata() {
		$array = [];
		$cm = CachingMetadata::ofMaximumAgeInSeconds( 42 );

		$ca = new CachedArray( $array, $cm );

		$this->assertSame( $cm, $ca->getCachingMetadata() );
	}

}
