<?php

namespace WikibaseQuality\ConstraintReport\Tests\Cache;

use PHPUnit\Framework\TestCase;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachedQueryResults;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\Metadata;

/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachedQueryResults
 *
 * @group WikibaseQualityConstraints
 *
 * @author Lucas Werkmeister
 * @license GNU GPL v2+
 */
class CachedQueryResultsTest extends TestCase {

	public function testGetArray() {
		$array = [ 'boolean' => true ];
		$cm = Metadata::blank();

		$cqr = new CachedQueryResults( $array, $cm );

		$this->assertSame( $array, $cqr->getArray() );
	}

}
