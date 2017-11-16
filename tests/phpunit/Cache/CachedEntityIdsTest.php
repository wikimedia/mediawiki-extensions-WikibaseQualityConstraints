<?php

namespace WikibaseQuality\ConstraintReport\Test\Cache;

use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachedEntityIds;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachingMetadata;

/**
 * @covers \WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachedEntityIds
 * @uses \WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachedArray
 * @uses \WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachingMetadata
 *
 * @group WikibaseQualityConstraints
 *
 * @author Lucas Werkmeister
 * @license GNU GPL v2+
 */
class CachedEntityIdsTest extends \PHPUnit_Framework_TestCase {

	public function testGetArray() {
		$array = [ 'boolean' => true ];
		$cm = CachingMetadata::fresh();

		$ca = new CachedEntityIds( $array, $cm );

		$this->assertSame( $array, $ca->getArray() );
	}

}
