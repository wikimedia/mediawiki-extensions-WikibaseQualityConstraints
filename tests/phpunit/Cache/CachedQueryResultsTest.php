<?php

namespace WikibaseQuality\ConstraintReport\Test\Cache;

use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachedQueryResults;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachingMetadata;

/**
 * @covers \WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachedQueryResults
 * @uses \WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachedArray
 * @uses \WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachingMetadata
 *
 * @group WikibaseQualityConstraints
 *
 * @author Lucas Werkmeister
 * @license GNU GPL v2+
 */
class CachedQueryResultsTest extends \PHPUnit_Framework_TestCase {

	public function testGetArray() {
		$array = [ 'boolean' => true ];
		$cm = CachingMetadata::fresh();

		$cqr = new CachedQueryResults( $array, $cm );

		$this->assertSame( $array, $cqr->getArray() );
	}

}
