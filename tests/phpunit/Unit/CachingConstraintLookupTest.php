<?php

namespace WikibaseQuality\ConstraintReport\Tests\Unit;

use Wikibase\DataModel\Entity\NumericPropertyId;
use WikibaseQuality\ConstraintReport\CachingConstraintLookup;
use WikibaseQuality\ConstraintReport\ConstraintLookup;

/**
 * @covers WikibaseQuality\ConstraintReport\CachingConstraintLookup
 *
 * @group WikibaseQualityConstraints
 *
 * @author Lucas Werkmeister
 * @license GPL-2.0-or-later
 */
class CachingConstraintLookupTest extends \MediaWikiUnitTestCase {

	public function testQuery_CalledOnce() {
		$p2 = new NumericPropertyId( 'P2' );
		$p3 = new NumericPropertyId( 'P3' );

		$mock = $this->createMock( ConstraintLookup::class );
		$expectedArgs = [ $p2, $p3 ];
		$mock->expects( $this->exactly( 2 ) )
			->method( 'queryConstraintsForProperty' )
			->willReturnCallback( function ( $propertyId ) use ( &$expectedArgs ): array {
				$this->assertSame( array_shift( $expectedArgs ), $propertyId );
				return [];
			} );

		/** @var ConstraintLookup $mock */
		$lookup = new CachingConstraintLookup( $mock );

		$this->assertSame( [], $lookup->queryConstraintsForProperty( $p2 ) );
		$this->assertSame( [], $lookup->queryConstraintsForProperty( $p2 ) );
		$this->assertSame( [], $lookup->queryConstraintsForProperty( $p3 ) );
		$this->assertSame( [], $lookup->queryConstraintsForProperty( $p2 ) );
	}

}
