<?php

namespace WikibaseQuality\ConstraintReport\Tests;

use Wikibase\DataModel\Entity\PropertyId;
use WikibaseQuality\ConstraintReport\ConstraintLookup;
use WikibaseQuality\ConstraintReport\CachingConstraintLookup;

/**
 * @covers WikibaseQuality\ConstraintReport\CachingConstraintLookup
 *
 * @group WikibaseQualityConstraints
 *
 * @author Lucas Werkmeister
 * @license GNU GPL v2+
 */
class CachingConstraintLookupTest extends \PHPUnit\Framework\TestCase {

	public function testQuery_CalledOnce() {
		$p2 = new PropertyId( 'P2' );
		$p3 = new PropertyId( 'P3' );

		$mock = $this->getMockBuilder( ConstraintLookup::class )->getMock();
		$mock->expects( $this->exactly( 2 ) )
			->method( 'queryConstraintsForProperty' )
			->will( $this->returnValue( [] ) )
			->withConsecutive(
				[ $this->equalTo( $p2 ) ],
				[ $this->equalTo( $p3 ) ]
			);

		/** @var ConstraintLookup $mock */
		$lookup = new CachingConstraintLookup( $mock );

		$this->assertSame( [], $lookup->queryConstraintsForProperty( $p2 ) );
		$this->assertSame( [], $lookup->queryConstraintsForProperty( $p2 ) );
		$this->assertSame( [], $lookup->queryConstraintsForProperty( $p3 ) );
		$this->assertSame( [], $lookup->queryConstraintsForProperty( $p2 ) );
	}

}
