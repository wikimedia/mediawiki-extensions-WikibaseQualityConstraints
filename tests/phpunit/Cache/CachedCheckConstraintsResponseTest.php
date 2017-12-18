<?php

namespace WikibaseQuality\ConstraintReport\Test\Cache;

use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachedCheckConstraintsResponse;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\Metadata;

/**
 * @covers \WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachedCheckConstraintsResponse
 * @uses \WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachedArray
 * @uses \WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\Metadata
 *
 * @group WikibaseQualityConstraints
 *
 * @author Lucas Werkmeister
 * @license GNU GPL v2+
 */
class CachedCheckConstraintsResponseTest extends \PHPUnit_Framework_TestCase {

	public function testGetArray() {
		$array = [ 'boolean' => true ];
		$cm = Metadata::blank();

		$cqr = new CachedCheckConstraintsResponse( $array, $cm );

		$this->assertSame( $array, $cqr->getArray() );
	}

}
