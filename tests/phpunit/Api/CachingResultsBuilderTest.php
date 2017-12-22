<?php

namespace WikibaseQuality\ConstraintReport\Tests\Api;

use HashBagOStuff;
use TimeAdjustableWANObjectCache;
use WANObjectCache;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\ItemIdParser;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\Lib\Store\EntityRevisionLookup;
use WikibaseQuality\ConstraintReport\Api\CachingResultsBuilder;
use WikibaseQuality\ConstraintReport\Api\ResultsBuilder;
use WikibaseQuality\ConstraintReport\Api\ResultsCache;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachedCheckConstraintsResponse;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachingMetadata;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\DependencyMetadata;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\Metadata;

include_once __DIR__ . '/../../../../../tests/phpunit/includes/libs/objectcache/WANObjectCacheTest.php';

/**
 * @covers \WikibaseQuality\ConstraintReport\Api\CachingResultsBuilder
 *
 * @license GNU GPL v2+
 */
class CachingResultsBuilderTest extends \PHPUnit_Framework_TestCase {

	public function testGetAndStoreResults_SameResults() {
		$expectedResults = new CachedCheckConstraintsResponse(
			[ 'Q100' => 'garbage data, should not matter' ],
			Metadata::blank()
		);
		$q100 = new ItemId( 'Q100' );
		$resultsBuilder = $this->getMock( ResultsBuilder::class );
		$resultsBuilder->expects( $this->once() )
			->method( 'getResults' )
			->with( [ $q100 ], [], null )
			->willReturn( $expectedResults );
		$cachingResultsBuilder = new CachingResultsBuilder(
			$resultsBuilder,
			new ResultsCache( WANObjectCache::newEmpty() ),
			$this->getMock( EntityRevisionLookup::class ),
			new ItemIdParser(),
			86400,
			[]
		);

		$results = $cachingResultsBuilder->getAndStoreResults( [ $q100 ], [], null );

		$this->assertSame( $expectedResults, $results );
	}

	public function testGetAndStoreResults_DontCacheClaimIds() {
		$expectedResults = new CachedCheckConstraintsResponse(
			[ 'Q100' => 'garbage data, should not matter' ],
			Metadata::blank()
		);
		$resultsBuilder = $this->getMock( ResultsBuilder::class );
		$resultsBuilder->expects( $this->once() )
			->method( 'getResults' )
			->with( [], [ 'fake' ], null )
			->willReturn( $expectedResults );
		$cache = $this->getMockBuilder( WANObjectCache::class )
			->disableOriginalConstructor()
			->getMock();
		$cache->expects( $this->never() )->method( 'set' );
		$lookup = $this->getMock( EntityRevisionLookup::class );
		$lookup->expects( $this->never() )->method( 'getLatestRevisionId ' );
		$cachingResultsBuilder = new CachingResultsBuilder(
			$resultsBuilder,
			new ResultsCache( $cache ),
			$lookup,
			new ItemIdParser(),
			86400,
			[]
		);

		$cachingResultsBuilder->getAndStoreResults( [], [ 'fake' ], null );
	}

	public function testGetAndStoreResults_DontCacheWithConstraintIds() {
		$expectedResults = new CachedCheckConstraintsResponse(
			[ 'Q100' => 'garbage data, should not matter' ],
			Metadata::blank()
		);
		$q100 = new ItemId( 'Q100' );
		$resultsBuilder = $this->getMock( ResultsBuilder::class );
		$resultsBuilder->expects( $this->once() )
			->method( 'getResults' )
			->with( [ $q100 ], [], [ 'fake' ] )
			->willReturn( $expectedResults );
		$cache = $this->getMockBuilder( WANObjectCache::class )
			->disableOriginalConstructor()
			->getMock();
		$cache->expects( $this->never() )->method( 'set' );
		$lookup = $this->getMock( EntityRevisionLookup::class );
		$lookup->expects( $this->never() )->method( 'getLatestRevisionId ' );
		$cachingResultsBuilder = new CachingResultsBuilder(
			$resultsBuilder,
			new ResultsCache( $cache ),
			$lookup,
			new ItemIdParser(),
			86400,
			[]
		);

		$cachingResultsBuilder->getAndStoreResults( [ $q100 ], [], [ 'fake' ] );
	}

	public function testGetAndStoreResults_StoreResults() {
		$expectedResults = new CachedCheckConstraintsResponse(
			[ 'Q100' => 'garbage data, should not matter' ],
			Metadata::blank()
		);
		$q100 = new ItemId( 'Q100' );
		$resultsBuilder = $this->getMock( ResultsBuilder::class );
		$resultsBuilder->expects( $this->once() )
			->method( 'getResults' )
			->with( [ $q100 ], [], null )
			->willReturn( $expectedResults );
		$cache = new WANObjectCache( [ 'cache' => new HashBagOStuff() ] );
		$resultsCache = new ResultsCache( $cache );
		$lookup = $this->getMock( EntityRevisionLookup::class );
		$cachingResultsBuilder = new CachingResultsBuilder(
			$resultsBuilder,
			$resultsCache,
			$lookup,
			new ItemIdParser(),
			86400,
			[]
		);

		$cachingResultsBuilder->getAndStoreResults( [ $q100 ], [], null );
		$cachedResults = $resultsCache->get( $q100 );

		$this->assertNotNull( $cachedResults );
		$this->assertArrayHasKey( 'results', $cachedResults );
		$this->assertSame( $expectedResults->getArray()['Q100'], $cachedResults['results'] );
	}

	public function testGetAndStoreResults_StoreLatestRevisionIds() {
		$q100 = new ItemId( 'Q100' );
		$q101 = new ItemId( 'Q101' );
		$p102 = new PropertyId( 'P102' );
		$expectedResults = new CachedCheckConstraintsResponse(
			[ 'Q100' => 'garbage data, should not matter' ],
			Metadata::ofDependencyMetadata( DependencyMetadata::merge( [
				DependencyMetadata::ofEntityId( $q100 ),
				DependencyMetadata::ofEntityId( $q101 ),
				DependencyMetadata::ofEntityId( $p102 ),
			] ) )
		);
		$revisionIds = [
			$q100->getSerialization() => 12345,
			$q101->getSerialization() => 1337,
			$p102->getSerialization() => 42,
		];
		$resultsBuilder = $this->getMock( ResultsBuilder::class );
		$resultsBuilder->expects( $this->once() )
			->method( 'getResults' )
			->with( [ $q100 ], [], null )
			->willReturn( $expectedResults );
		$cache = new WANObjectCache( [ 'cache' => new HashBagOStuff() ] );
		$resultsCache = new ResultsCache( $cache );
		$lookup = $this->getMock( EntityRevisionLookup::class );
		$lookup->expects( $this->atLeast( 3 ) )
			->method( 'getLatestRevisionId' )
			->willReturnCallback( function( EntityId $entityId ) use ( $revisionIds ) {
				$serialization = $entityId->getSerialization();
				$this->assertArrayHasKey( $serialization, $revisionIds );
				return $revisionIds[$serialization];
			} );
		$cachingResultsBuilder = new CachingResultsBuilder(
			$resultsBuilder,
			$resultsCache,
			$lookup,
			new ItemIdParser(),
			86400,
			[]
		);

		$cachingResultsBuilder->getAndStoreResults( [ $q100 ], [], null );
		$cachedResults = $resultsCache->get( $q100 );

		$this->assertNotNull( $cachedResults );
		$this->assertArrayHasKey( 'latestRevisionIds', $cachedResults );
		$this->assertSame( $revisionIds, $cachedResults['latestRevisionIds'] );
	}

	public function testGetStoredResults_CacheMiss() {
		$cachingResultsBuilder = new CachingResultsBuilder(
			$this->getMock( ResultsBuilder::class ),
			new ResultsCache( WANObjectCache::newEmpty() ),
			$this->getMock( EntityRevisionLookup::class ),
			new ItemIdParser(),
			86400,
			[]
		);

		$response = $cachingResultsBuilder->getStoredResults( new ItemId( 'Q1' ) );

		$this->assertNull( $response );
	}

	public function testGetStoredResults_Outdated() {
		$entityRevisionLookup = $this->getMock( EntityRevisionLookup::class );
		$entityRevisionLookup->method( 'getLatestRevisionId' )
			->willReturnCallback( function( EntityId $entityId ) {
				switch ( $entityId->getSerialization() ) {
					case 'Q5':
						return 100;
					case 'Q10':
						return 101;
				}
			} );
		$cache = new WANObjectCache( [ 'cache' => new HashBagOStuff() ] );
		$resultsCache = new ResultsCache( $cache );
		$cachingResultsBuilder = new CachingResultsBuilder(
			$this->getMock( ResultsBuilder::class ),
			$resultsCache,
			$entityRevisionLookup,
			new ItemIdParser(),
			86400,
			[]
		);
		$q5 = new ItemId( 'Q5' );
		$value = [
			'results' => 'garbage data, should not matter',
			'latestRevisionIds' => [
				'Q5' => 100,
				'Q10' => 99,
			],
		];
		$resultsCache->set( $q5, $value );

		$response = $cachingResultsBuilder->getStoredResults( $q5 );

		$this->assertNull( $response );
	}

	public function testGetStoredResults_Fresh() {
		$entityRevisionLookup = $this->getMock( EntityRevisionLookup::class );
		$entityRevisionLookup->method( 'getLatestRevisionId' )
			->willReturnCallback( function( EntityId $entityId ) {
				switch ( $entityId->getSerialization() ) {
					case 'Q5':
						return 100;
					case 'Q10':
						return 99;
				}
			} );

		$cache = new TimeAdjustableWANObjectCache( [ 'cache' => new HashBagOStuff() ] );
		$now = 9001;
		$cache->setTime( $now - 1337 );
		$resultsCache = new ResultsCache( $cache );
		$cachingResultsBuilder = new CachingResultsBuilder(
			$this->getMock( ResultsBuilder::class ),
			$resultsCache,
			$entityRevisionLookup,
			new ItemIdParser(),
			86400,
			[]
		);
		$cachingResultsBuilder->setMicrotimeFunction( function () use ( $now ) {
			return $now;
		} );

		$q5 = new ItemId( 'Q5' );
		$expectedResults = 'garbage data, should not matter';
		$value = [
			'results' => $expectedResults,
			'latestRevisionIds' => [
				'Q5' => 100,
				'Q10' => 99,
			],
		];
		$resultsCache->set( $q5, $value );

		$response = $cachingResultsBuilder->getStoredResults( $q5 );

		$this->assertNotNull( $response );
		$this->assertSame( [ 'Q5' => $expectedResults ], $response->getArray() );
		$cachingMetadata = $response->getMetadata()->getCachingMetadata();
		$this->assertTrue( $cachingMetadata->isCached() );
		$this->assertSame( 1337, $cachingMetadata->getMaximumAgeInSeconds() );
	}

	public function testGetResults_EmptyCache() {
		$entityIds = [ new ItemId( 'Q5' ), new ItemId( 'Q10' ) ];

		$cachingResultsBuilder = $this->getMockBuilder( CachingResultsBuilder::class )
			->disableOriginalConstructor()
			->setMethods( [ 'getStoredResults', 'getAndStoreResults' ] )
			->getMock();
		$cachingResultsBuilder->method( 'getStoredResults' )->willReturn( null );
		$cachingResultsBuilder->method( 'getAndStoreResults' )
			->with( $entityIds, [], null )
			->willReturnCallback( function( $entityIds, $claimIds, $constraintIds ) {
				$results = [];
				foreach ( $entityIds as $entityId ) {
					$serialization = $entityId->getSerialization();
					$results[$serialization] = 'garbage of ' . $serialization;
				}
				return new CachedCheckConstraintsResponse( $results, Metadata::blank() );
			} );
		/** @var CachingResultsBuilder $cachingResultsBuilder */

		$results = $cachingResultsBuilder->getResults( $entityIds, [], null );

		$expected = [ 'Q5' => 'garbage of Q5', 'Q10' => 'garbage of Q10' ];
		$actual = $results->getArray();
		asort( $expected );
		asort( $actual );
		$this->assertSame( $expected, $actual );
		$this->assertFalse( $results->getMetadata()->getCachingMetadata()->isCached() );
	}

	public function testGetResults_ConstraintIds() {
		$entityIds = [ new ItemId( 'Q5' ), new ItemId( 'Q10' ) ];
		$statementIds = [];
		$constraintIds = [ 'P12$11a14ea5-10dc-425b-b94d-6e65997be983' ];
		$expected = new CachedCheckConstraintsResponse(
			[ 'Q5' => 'garbage of Q5', 'Q10' => 'some garbage of Q10' ],
			Metadata::ofCachingMetadata( CachingMetadata::ofMaximumAgeInSeconds( 5 * 60 ) )
		);
		$cachingResultsBuilder = $this->getMockBuilder( CachingResultsBuilder::class )
			->disableOriginalConstructor()
			->setMethods( [ 'getStoredResults', 'getAndStoreResults' ] )
			->getMock();
		$cachingResultsBuilder->expects( $this->never() )->method( 'getStoredResults' );
		$cachingResultsBuilder->expects( $this->once() )->method( 'getAndStoreResults' )
			->with( $entityIds, $statementIds, $constraintIds )
			->willReturn( $expected );
		/** @var CachingResultsBuilder $cachingResultsBuilder */

		$results = $cachingResultsBuilder->getResults( $entityIds, $statementIds, $constraintIds );

		$this->assertSame( $expected->getArray(), $results->getArray() );
		$this->assertEquals( $expected->getMetadata(), $results->getMetadata() );
	}

	public function testGetResults_StatementIds() {
		$entityIds = [];
		$statementIds = [ 'Q5$9c009c6f-fdf5-41d1-86e9-e790427e3dc6' ];
		$constraintIds = [];
		$expected = new CachedCheckConstraintsResponse(
			[ 'Q5' => 'some garbage of Q5' ],
			Metadata::ofCachingMetadata( CachingMetadata::ofMaximumAgeInSeconds( 5 * 60 ) )
		);
		$cachingResultsBuilder = $this->getMockBuilder( CachingResultsBuilder::class )
			->disableOriginalConstructor()
			->setMethods( [ 'getStoredResults', 'getAndStoreResults' ] )
			->getMock();
		$cachingResultsBuilder->expects( $this->never() )->method( 'getStoredResults' );
		$cachingResultsBuilder->expects( $this->once() )->method( 'getAndStoreResults' )
			->with( $entityIds, $statementIds, $constraintIds )
			->willReturn( $expected );
		/** @var CachingResultsBuilder $cachingResultsBuilder */

		$results = $cachingResultsBuilder->getResults( $entityIds, $statementIds, $constraintIds );

		$this->assertSame( $expected->getArray(), $results->getArray() );
		$this->assertEquals( $expected->getMetadata(), $results->getMetadata() );
	}

	public function testGetResults_FullyCached() {
		$entityIds = [ new ItemId( 'Q5' ), new ItemId( 'Q10' ) ];
		$cachingResultsBuilder = $this->getMockBuilder( CachingResultsBuilder::class )
			->disableOriginalConstructor()
			->setMethods( [ 'getStoredResults', 'getAndStoreResults' ] )
			->getMock();
		$cachingResultsBuilder->expects( $this->exactly( 2 ) )->method( 'getStoredResults' )
			->willReturnCallback( function ( EntityId $entityId ) {
				$serialization = $entityId->getSerialization();
				return new CachedCheckConstraintsResponse(
					[ $serialization => 'garbage of ' . $serialization ],
					Metadata::ofCachingMetadata( CachingMetadata::ofMaximumAgeInSeconds( 64800 ) )
				);
			} );
		$cachingResultsBuilder->expects( $this->never() )->method( 'getAndStoreResults' );
		/** @var CachingResultsBuilder $cachingResultsBuilder */

		$results = $cachingResultsBuilder->getResults( $entityIds, [], null );

		$expected = [ 'Q5' => 'garbage of Q5', 'Q10' => 'garbage of Q10' ];
		$actual = $results->getArray();
		asort( $expected );
		asort( $actual );
		$this->assertSame( $expected, $actual );
		$this->assertSame( 64800, $results->getMetadata()->getCachingMetadata()->getMaximumAgeInSeconds() );
	}

	public function testGetResults_PartiallyCached() {
		$entityIds = [ new ItemId( 'Q5' ), new ItemId( 'Q10' ) ];
		$cachingResultsBuilder = $this->getMockBuilder( CachingResultsBuilder::class )
			->disableOriginalConstructor()
			->setMethods( [ 'getStoredResults', 'getAndStoreResults' ] )
			->getMock();
		$cachingResultsBuilder->expects( $this->exactly( 2 ) )->method( 'getStoredResults' )
			->willReturnCallback( function ( EntityId $entityId ) {
				if ( $entityId->getSerialization() === 'Q5' ) {
					return new CachedCheckConstraintsResponse(
						[ 'Q5' => 'garbage of Q5' ],
						Metadata::ofCachingMetadata( CachingMetadata::ofMaximumAgeInSeconds( 64800 ) )
					);
				} else {
					return null;
				}
			} );
		$cachingResultsBuilder->expects( $this->once() )->method( 'getAndStoreResults' )
			->with( [ $entityIds[1] ], [], null )
			->willReturn( new CachedCheckConstraintsResponse(
				[ 'Q10' => 'garbage of Q10' ],
				Metadata::ofDependencyMetadata( DependencyMetadata::ofEntityId( $entityIds[1] ) )
			) );
		/** @var CachingResultsBuilder $cachingResultsBuilder */

		$results = $cachingResultsBuilder->getResults( $entityIds, [], null );

		$expected = [ 'Q5' => 'garbage of Q5', 'Q10' => 'garbage of Q10' ];
		$actual = $results->getArray();
		asort( $expected );
		asort( $actual );
		$this->assertSame( $expected, $actual );
		$metadata = $results->getMetadata();
		$this->assertSame( 64800, $metadata->getCachingMetadata()->getMaximumAgeInSeconds() );
		$this->assertSame( [ $entityIds[1] ], $metadata->getDependencyMetadata()->getEntityIds() );
	}

	public function testUpdateCachingMetadata_CachedToplevel() {
		$cachingResultsBuilder = new CachingResultsBuilder(
			$this->getMock( ResultsBuilder::class ),
			new ResultsCache( WANObjectCache::newEmpty() ),
			$this->getMock( EntityRevisionLookup::class ),
			new ItemIdParser(),
			86400,
			[]
		);
		$cm1 = CachingMetadata::ofMaximumAgeInSeconds( 20 );
		$cm2 = CachingMetadata::ofMaximumAgeInSeconds( 40 );
		$array1 = $cm1->toArray();
		$array2 = $cm2->toArray();

		$cachingResultsBuilder->updateCachingMetadata( $array1, 'cached', $cm2 );
		$cachingResultsBuilder->updateCachingMetadata( $array2, 'cached', $cm1 );

		$arrayMerged = CachingMetadata::merge( [ $cm1, $cm2 ] )->toArray();
		$this->assertSame( $arrayMerged, $array1 );
		$this->assertSame( $arrayMerged, $array2 );
	}

	public function testUpdateCachingMetadata_CachedNested() {
		$cachingResultsBuilder = new CachingResultsBuilder(
			$this->getMock( ResultsBuilder::class ),
			new ResultsCache( WANObjectCache::newEmpty() ),
			$this->getMock( EntityRevisionLookup::class ),
			new ItemIdParser(),
			86400,
			[]
		);
		$cm1 = CachingMetadata::ofMaximumAgeInSeconds( 20 );
		$cm2 = CachingMetadata::ofMaximumAgeInSeconds( 40 );
		$array = [ 'foo' => [ [ 'bar' => 10, 'baz' => [ 'cached' => $cm1->toArray() ] ] ] ];

		$cachingResultsBuilder->updateCachingMetadata( $array, 0, $cm2 );

		$this->assertSame(
			[ 'foo' => [ [ 'bar' => 10, 'baz' => [ 'cached' => $cm2->toArray() ] ] ] ],
			$array
		);
	}

	public function testUpdateCachingMetadata_AddToConstraint() {
		$cachingResultsBuilder = new CachingResultsBuilder(
			$this->getMock( ResultsBuilder::class ),
			new ResultsCache( WANObjectCache::newEmpty() ),
			$this->getMock( EntityRevisionLookup::class ),
			new ItemIdParser(),
			86400,
			[ 'Q1' ]
		);
		$cm = CachingMetadata::ofMaximumAgeInSeconds( 10 );
		$array = [
			[ 'constraint' => [ 'type' => 'Q1' ], 'status' => '✔' ],
			[ 'constraint' => [ 'type' => 'Q2' ], 'status' => '✘' ],
		];

		$cachingResultsBuilder->updateCachingMetadata( $array, 0, $cm );

		$this->assertSame(
			[
				[ 'constraint' => [ 'type' => 'Q1' ], 'status' => '✔', 'cached' => $cm->toArray() ],
				[ 'constraint' => [ 'type' => 'Q2' ], 'status' => '✘' ],
			],
			$array
		);
	}

}
