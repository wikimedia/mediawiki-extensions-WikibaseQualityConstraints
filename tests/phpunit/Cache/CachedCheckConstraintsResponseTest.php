<?php

namespace WikibaseQuality\ConstraintReport\Tests\Cache;

use PHPUnit\Framework\TestCase;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachedCheckConstraintsResponse;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\Metadata;

/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachedCheckConstraintsResponse
 *
 * @group WikibaseQualityConstraints
 *
 * @author Lucas Werkmeister
 * @license GNU GPL v2+
 */
class CachedCheckConstraintsResponseTest extends TestCase {

	public function testGetArray() {
		$array = [ 'boolean' => true ];
		$cm = Metadata::blank();

		$cqr = new CachedCheckConstraintsResponse( $array, $cm );

		$this->assertSame( $array, $cqr->getArray() );
	}

}
