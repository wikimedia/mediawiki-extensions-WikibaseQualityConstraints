<?php

namespace WikibaseQuality\ConstraintReport\Test\Cache;

use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachedEntityIds;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\Metadata;

/**
 * @covers \WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachedEntityIds
 * @uses \WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachedArray
 * @uses \WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\Metadata
 *
 * @group WikibaseQualityConstraints
 *
 * @author Lucas Werkmeister
 * @license GNU GPL v2+
 */
class CachedEntityIdsTest extends \PHPUnit_Framework_TestCase {

	public function testGetArray() {
		$array = [ 'boolean' => true ];
		$cm = Metadata::blank();

		$cei = new CachedEntityIds( $array, $cm );

		$this->assertSame( $array, $cei->getArray() );
	}

}
