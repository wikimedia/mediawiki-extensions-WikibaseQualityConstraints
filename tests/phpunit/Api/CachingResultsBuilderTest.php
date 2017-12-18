<?php

namespace WikibaseQuality\ConstraintReport\Tests\Api;

use HashBagOStuff;
use WANObjectCache;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\Lib\Store\EntityRevisionLookup;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Api\CachingResultsBuilder;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Api\ResultsBuilder;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachedCheckConstraintsResponse;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\DependencyMetadata;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\Metadata;

/**
 * @covers \WikibaseQuality\ConstraintReport\ConstraintCheck\Api\CachingResultsBuilder
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
			WANObjectCache::newEmpty(),
			$this->getMock( EntityRevisionLookup::class ),
			86400
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
			$cache,
			$lookup,
			86400
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
			$cache,
			$lookup,
			86400
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
		$lookup = $this->getMock( EntityRevisionLookup::class );
		$cachingResultsBuilder = new CachingResultsBuilder(
			$resultsBuilder,
			$cache,
			$lookup,
			86400
		);

		$cachingResultsBuilder->getAndStoreResults( [ $q100 ], [], null );
		$cachedResults = $cache->get( $cache->makeKey(
			'WikibaseQualityConstraints',
			'checkConstraints',
			'v2',
			'Q100'
		) );

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
			$cache,
			$lookup,
			86400
		);

		$cachingResultsBuilder->getAndStoreResults( [ $q100 ], [], null );
		$cachedResults = $cache->get( $cache->makeKey(
			'WikibaseQualityConstraints',
			'checkConstraints',
			'v2',
			'Q100'
		) );

		$this->assertNotNull( $cachedResults );
		$this->assertArrayHasKey( 'latestRevisionIds', $cachedResults );
		$this->assertSame( $revisionIds, $cachedResults['latestRevisionIds'] );
	}

}
