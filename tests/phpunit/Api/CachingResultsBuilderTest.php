<?php

namespace WikibaseQuality\ConstraintReport\Tests\Api;

use DataValues\TimeValue;
use HashBagOStuff;
use HashConfig;
use NullStatsdDataFactory;
use Psr\Log\NullLogger;
use TimeAdjustableWANObjectCache;
use WANObjectCache;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\ItemIdParser;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\Lib\Store\EntityRevisionLookup;
use Wikibase\Lib\Store\Sql\WikiPageEntityMetaDataAccessor;
use WikibaseQuality\ConstraintReport\Api\CachingResultsBuilder;
use WikibaseQuality\ConstraintReport\Api\ResultsBuilder;
use WikibaseQuality\ConstraintReport\Api\ResultsCache;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachedCheckConstraintsResponse;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachingMetadata;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\DependencyMetadata;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\Metadata;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\LoggingHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;

include_once __DIR__ . '/../../../../../tests/phpunit/includes/libs/objectcache/WANObjectCacheTest.php';

/**
 * @covers WikibaseQuality\ConstraintReport\Api\CachingResultsBuilder
 *
 * @license GPL-2.0-or-later
 */
class CachingResultsBuilderTest extends \PHPUnit\Framework\TestCase {

	private function getCachingResultsBuilder(
		ResultsBuilder $resultsBuilder,
		ResultsCache $resultsCache,
		WikiPageEntityMetaDataAccessor $metaDataAccessor,
		array $possiblyStaleConstraintTypes = []
	) {
		return new CachingResultsBuilder(
			$resultsBuilder,
			$resultsCache,
			$metaDataAccessor,
			new ItemIdParser(),
			86400,
			$possiblyStaleConstraintTypes,
			10000,
			new LoggingHelper(
				new NullStatsdDataFactory(),
				new NullLogger(),
				new HashConfig( [
					'WBQualityConstraintsCheckDurationInfoSeconds' => 5,
					'WBQualityConstraintsCheckDurationWarningSeconds' => 10,
				] )
			)
		);
	}

	private function getCachingResultsBuilderMock(
		ResultsBuilder $resultsBuilder,
		ResultsCache $resultsCache,
		WikiPageEntityMetaDataAccessor $metaDataAccessor
	) {
		return $this->getMockBuilder( CachingResultsBuilder::class )
			->setConstructorArgs( [
				$resultsBuilder,
				$resultsCache,
				$metaDataAccessor,
				new ItemIdParser(),
				86400,
				[],
				10000,
				new LoggingHelper(
					new NullStatsdDataFactory(),
					new NullLogger(),
					new HashConfig( [
						'WBQualityConstraintsCheckDurationInfoSeconds' => 5,
						'WBQualityConstraintsCheckDurationWarningSeconds' => 10,
					] )
				)
			] )
			->setMethods( [ 'getStoredResults', 'getAndStoreResults' ] )
			->getMock();
	}

	public function testGetAndStoreResults_SameResults() {
		$expectedResults = new CachedCheckConstraintsResponse(
			[ 'Q100' => 'garbage data, should not matter' ],
			Metadata::blank()
		);
		$q100 = new ItemId( 'Q100' );
		$statuses = [ 'garbage status', 'other status' ];
		$resultsBuilder = $this->getMock( ResultsBuilder::class );
		$resultsBuilder->expects( $this->once() )
			->method( 'getResults' )
			->with( [ $q100 ], [], null, $statuses )
			->willReturn( $expectedResults );
		$metaDataAccessor = $this->getMock( WikiPageEntityMetaDataAccessor::class );
		$metaDataAccessor->method( 'loadRevisionInformation' )
			->willReturn( [] );
		$cachingResultsBuilder = $this->getCachingResultsBuilder(
			$resultsBuilder,
			new ResultsCache( WANObjectCache::newEmpty() ),
			$metaDataAccessor
		);

		$results = $cachingResultsBuilder->getAndStoreResults( [ $q100 ], [], null, $statuses );

		$this->assertSame( $expectedResults, $results );
	}

	public function testGetAndStoreResults_DontCacheClaimIds() {
		$expectedResults = new CachedCheckConstraintsResponse(
			[ 'Q100' => 'garbage data, should not matter' ],
			Metadata::blank()
		);
		$statuses = [ 'garbage status', 'other status' ];
		$resultsBuilder = $this->getMock( ResultsBuilder::class );
		$resultsBuilder->expects( $this->once() )
			->method( 'getResults' )
			->with( [], [ 'fake' ], null, $statuses )
			->willReturn( $expectedResults );
		$cache = $this->getMockBuilder( WANObjectCache::class )
			->disableOriginalConstructor()
			->getMock();
		$cache->expects( $this->never() )->method( 'set' );
		$metaDataAccessor = $this->getMock( WikiPageEntityMetaDataAccessor::class );
		$metaDataAccessor->expects( $this->never() )->method( 'loadRevisionInformation ' );
		$cachingResultsBuilder = $this->getCachingResultsBuilder(
			$resultsBuilder,
			new ResultsCache( $cache ),
			$metaDataAccessor
		);

		$cachingResultsBuilder->getAndStoreResults( [], [ 'fake' ], null, $statuses );
	}

	public function testGetAndStoreResults_DontCacheWithConstraintIds() {
		$expectedResults = new CachedCheckConstraintsResponse(
			[ 'Q100' => 'garbage data, should not matter' ],
			Metadata::blank()
		);
		$q100 = new ItemId( 'Q100' );
		$statuses = [ 'garbage status', 'other status' ];
		$resultsBuilder = $this->getMock( ResultsBuilder::class );
		$resultsBuilder->expects( $this->once() )
			->method( 'getResults' )
			->with( [ $q100 ], [], [ 'fake' ], $statuses )
			->willReturn( $expectedResults );
		$cache = $this->getMockBuilder( WANObjectCache::class )
			->disableOriginalConstructor()
			->getMock();
		$cache->expects( $this->never() )->method( 'set' );
		$metaDataAccessor = $this->getMock( WikiPageEntityMetaDataAccessor::class );
		$metaDataAccessor->expects( $this->never() )->method( 'loadRevisionInformation ' );
		$cachingResultsBuilder = $this->getCachingResultsBuilder(
			$resultsBuilder,
			new ResultsCache( $cache ),
			$metaDataAccessor
		);

		$cachingResultsBuilder->getAndStoreResults( [ $q100 ], [], [ 'fake' ], $statuses );
	}

	public function testGetAndStoreResults_StoreResults() {
		$expectedResults = new CachedCheckConstraintsResponse(
			[ 'Q100' => 'garbage data, should not matter' ],
			Metadata::blank()
		);
		$q100 = new ItemId( 'Q100' );
		$statuses = [
			CheckResult::STATUS_VIOLATION,
			CheckResult::STATUS_WARNING,
			CheckResult::STATUS_BAD_PARAMETERS
		];
		$resultsBuilder = $this->getMock( ResultsBuilder::class );
		$resultsBuilder->expects( $this->once() )
			->method( 'getResults' )
			->with( [ $q100 ], [], null, $statuses )
			->willReturn( $expectedResults );
		$cache = new WANObjectCache( [ 'cache' => new HashBagOStuff() ] );
		$resultsCache = new ResultsCache( $cache );
		$metaDataAccessor = $this->getMock( WikiPageEntityMetaDataAccessor::class );
		$metaDataAccessor->method( 'loadRevisionInformation' )
			->willReturn( [] );
		$cachingResultsBuilder = $this->getCachingResultsBuilder(
			$resultsBuilder,
			$resultsCache,
			$metaDataAccessor
		);

		$cachingResultsBuilder->getAndStoreResults( [ $q100 ], [], null, $statuses );
		$cachedResults = $resultsCache->get( $q100 );

		$this->assertNotFalse( $cachedResults );
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
		$statuses = [
			CheckResult::STATUS_VIOLATION,
			CheckResult::STATUS_WARNING,
			CheckResult::STATUS_BAD_PARAMETERS
		];
		$resultsBuilder = $this->getMock( ResultsBuilder::class );
		$resultsBuilder->expects( $this->once() )
			->method( 'getResults' )
			->with( [ $q100 ], [], null, $statuses )
			->willReturn( $expectedResults );
		$cache = new WANObjectCache( [ 'cache' => new HashBagOStuff() ] );
		$resultsCache = new ResultsCache( $cache );
		$metaDataAccessor = $this->getMock( WikiPageEntityMetaDataAccessor::class );
		$metaDataAccessor->expects( $this->once() )
			->method( 'loadRevisionInformation' )
			->with(
				[ $q100, $q101, $p102 ],
				EntityRevisionLookup::LATEST_FROM_REPLICA
			)
			->willReturnCallback( function ( $entityIds, $mode ) use ( $revisionIds ) {
				$metadata = [];
				foreach ( $entityIds as $entityId ) {
					$serialization = $entityId->getSerialization();
					$metadata[$serialization] = (object)[
						'page_latest' => $revisionIds[$entityId->getSerialization()],
					];
				}
				return $metadata;
			} );
		$cachingResultsBuilder = $this->getCachingResultsBuilder(
			$resultsBuilder,
			$resultsCache,
			$metaDataAccessor
		);

		$cachingResultsBuilder->getAndStoreResults( [ $q100 ], [], null, $statuses );
		$cachedResults = $resultsCache->get( $q100 );

		$this->assertNotFalse( $cachedResults );
		$this->assertArrayHasKey( 'latestRevisionIds', $cachedResults );
		$this->assertSame( $revisionIds, $cachedResults['latestRevisionIds'] );
	}

	public function testGetAndStoreResults_DontStoreWithWrongStatuses() {
		$expectedResults = new CachedCheckConstraintsResponse(
			[ 'Q100' => 'garbage data, should not matter' ],
			Metadata::blank()
		);
		$q100 = new ItemId( 'Q100' );
		$statuses = [ CheckResult::STATUS_VIOLATION ];
		$resultsBuilder = $this->getMock( ResultsBuilder::class );
		$resultsBuilder->expects( $this->once() )
			->method( 'getResults' )
			->with( [ $q100 ], [], null, $statuses )
			->willReturn( $expectedResults );
		$cache = $this->getMockBuilder( WANObjectCache::class )
			->disableOriginalConstructor()
			->getMock();
		$cache->expects( $this->never() )->method( 'set' );
		$metaDataAccessor = $this->getMock( WikiPageEntityMetaDataAccessor::class );
		$metaDataAccessor->expects( $this->never() )->method( 'loadRevisionInformation ' );
		$cachingResultsBuilder = $this->getCachingResultsBuilder(
			$resultsBuilder,
			new ResultsCache( $cache ),
			$metaDataAccessor
		);

		$cachingResultsBuilder->getAndStoreResults( [ $q100 ], [], null, $statuses );
	}

	public function testGetAndStoreResults_WithoutFutureTime() {
		$expectedResults = new CachedCheckConstraintsResponse(
			[ 'Q100' => 'garbage data, should not matter' ],
			Metadata::ofDependencyMetadata( DependencyMetadata::blank() )
		);
		$q100 = new ItemId( 'Q100' );
		$statuses = [
			CheckResult::STATUS_VIOLATION,
			CheckResult::STATUS_WARNING,
			CheckResult::STATUS_BAD_PARAMETERS
		];
		$resultsBuilder = $this->getMock( ResultsBuilder::class );
		$resultsBuilder->expects( $this->once() )
			->method( 'getResults' )
			->with( [ $q100 ], [], null, $statuses )
			->willReturn( $expectedResults );
		$cache = new WANObjectCache( [ 'cache' => new HashBagOStuff() ] );
		$resultsCache = new ResultsCache( $cache );
		$metaDataAccessor = $this->getMock( WikiPageEntityMetaDataAccessor::class );
		$metaDataAccessor->method( 'loadRevisionInformation' )
			->willReturn( [] );
		$cachingResultsBuilder = $this->getCachingResultsBuilder(
			$resultsBuilder,
			$resultsCache,
			$metaDataAccessor
		);

		$cachingResultsBuilder->getAndStoreResults( [ $q100 ], [], null, $statuses );
		$cachedResults = $resultsCache->get( $q100 );

		$this->assertNotFalse( $cachedResults );
		$this->assertArrayNotHasKey( 'futureTime', $cachedResults );
	}

	public function testGetAndStoreResults_WithFutureTime() {
		$timeValue = new TimeValue(
			'+2012-10-29T00:00:00Z',
			0,
			0,
			0,
			TimeValue::PRECISION_DAY,
			TimeValue::CALENDAR_GREGORIAN
		);
		$expectedResults = new CachedCheckConstraintsResponse(
			[ 'Q100' => 'garbage data, should not matter' ],
			Metadata::ofDependencyMetadata( DependencyMetadata::ofFutureTime( $timeValue ) )
		);
		$q100 = new ItemId( 'Q100' );
		$statuses = [
			CheckResult::STATUS_VIOLATION,
			CheckResult::STATUS_WARNING,
			CheckResult::STATUS_BAD_PARAMETERS
		];
		$resultsBuilder = $this->getMock( ResultsBuilder::class );
		$resultsBuilder->expects( $this->once() )
			->method( 'getResults' )
			->with( [ $q100 ], [], null, $statuses )
			->willReturn( $expectedResults );
		$cache = new WANObjectCache( [ 'cache' => new HashBagOStuff() ] );
		$resultsCache = new ResultsCache( $cache );
		$metaDataAccessor = $this->getMock( WikiPageEntityMetaDataAccessor::class );
		$metaDataAccessor->method( 'loadRevisionInformation' )
			->willReturn( [] );
		$cachingResultsBuilder = $this->getCachingResultsBuilder(
			$resultsBuilder,
			$resultsCache,
			$metaDataAccessor
		);

		$cachingResultsBuilder->getAndStoreResults( [ $q100 ], [], null, $statuses );
		$cachedResults = $resultsCache->get( $q100 );

		$this->assertNotFalse( $cachedResults );
		$this->assertArrayHasKey( 'futureTime', $cachedResults );
		$this->assertEquals( $timeValue->getArrayValue(), $cachedResults['futureTime'] );
	}

	public function testGetAndStoreResults_DontStoreWithoutRevisionInformation() {
		$q100 = new ItemId( 'Q100' );
		$expectedResults = new CachedCheckConstraintsResponse(
			[ 'Q100' => 'garbage data, should not matter' ],
			Metadata::ofDependencyMetadata( DependencyMetadata::ofEntityId( $q100 ) )
		);
		$statuses = [
			CheckResult::STATUS_VIOLATION,
			CheckResult::STATUS_WARNING,
			CheckResult::STATUS_BAD_PARAMETERS
		];
		$resultsBuilder = $this->getMock( ResultsBuilder::class );
		$resultsBuilder->expects( $this->once() )
			->method( 'getResults' )
			->with( [ $q100 ], [], null, $statuses )
			->willReturn( $expectedResults );
		$cache = $this->getMockBuilder( WANObjectCache::class )
			->disableOriginalConstructor()
			->getMock();
		$cache->expects( $this->never() )->method( 'set' );
		$metaDataAccessor = $this->getMock( WikiPageEntityMetaDataAccessor::class );
		$metaDataAccessor->expects( $this->once() )
			->method( 'loadRevisionInformation' )
			->with(
				[ $q100 ],
				EntityRevisionLookup::LATEST_FROM_REPLICA
			)
			->willReturn( [ 'Q100' => false ] );
		$cachingResultsBuilder = $this->getCachingResultsBuilder(
			$resultsBuilder,
			new ResultsCache( $cache ),
			$metaDataAccessor
		);

		$cachingResultsBuilder->getAndStoreResults( [ $q100 ], [], null, $statuses );
	}

	public function testGetStoredResults_CacheMiss() {
		$cachingResultsBuilder = $this->getCachingResultsBuilder(
			$this->getMock( ResultsBuilder::class ),
			new ResultsCache( WANObjectCache::newEmpty() ),
			$this->getMock( WikiPageEntityMetaDataAccessor::class )
		);

		$response = $cachingResultsBuilder->getStoredResults( new ItemId( 'Q1' ) );

		$this->assertNull( $response );
	}

	public function testGetStoredResults_Outdated() {
		$metaDataAccessor = $this->getMock( WikiPageEntityMetaDataAccessor::class );
		$metaDataAccessor->method( 'loadRevisionInformation' )
			->willReturn( [
				'Q5' => (object)[ 'page_latest' => 100 ],
				'Q10' => (object)[ 'page_latest' => 101 ],
			] );
		$cache = new WANObjectCache( [ 'cache' => new HashBagOStuff() ] );
		$resultsCache = new ResultsCache( $cache );
		$cachingResultsBuilder = $this->getCachingResultsBuilder(
			$this->getMock( ResultsBuilder::class ),
			$resultsCache,
			$metaDataAccessor
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
		$metaDataAccessor = $this->getMock( WikiPageEntityMetaDataAccessor::class );
		$metaDataAccessor->method( 'loadRevisionInformation' )
			->willReturn( [
				'Q5' => (object)[ 'page_latest' => 100 ],
				'Q10' => (object)[ 'page_latest' => 99 ],
			] );

		$cache = new TimeAdjustableWANObjectCache( [ 'cache' => new HashBagOStuff() ] );
		$now = 9001;
		$cache->setTime( $now - 1337 );
		$resultsCache = new ResultsCache( $cache );
		$cachingResultsBuilder = $this->getCachingResultsBuilder(
			$this->getMock( ResultsBuilder::class ),
			$resultsCache,
			$metaDataAccessor
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

	public function testGetStoredResults_WithoutRevisionInformation() {
		$metaDataAccessor = $this->getMock( WikiPageEntityMetaDataAccessor::class );
		$metaDataAccessor
			->method( 'loadRevisionInformation' )
			->willReturn( [
				'Q5' => 100,
				'Q10' => false,
			] );
		$cache = new WANObjectCache( [ 'cache' => new HashBagOStuff() ] );
		$resultsCache = new ResultsCache( $cache );
		$cachingResultsBuilder = $this->getCachingResultsBuilder(
			$this->getMock( ResultsBuilder::class ),
			$resultsCache,
			$metaDataAccessor
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

	public function testGetResults_EmptyCache() {
		$entityIds = [ new ItemId( 'Q5' ), new ItemId( 'Q10' ) ];
		$statuses = [ 'garbage status', 'other status' ];

		$cachingResultsBuilder = $this->getCachingResultsBuilderMock(
			$this->getMock( ResultsBuilder::class ),
			new ResultsCache( WANObjectCache::newEmpty() ),
			$this->getMock( WikiPageEntityMetaDataAccessor::class )
		);
		$cachingResultsBuilder->method( 'getStoredResults' )->willReturn( null );
		$cachingResultsBuilder->method( 'getAndStoreResults' )
			->with( $entityIds, [], null, $statuses )
			->willReturnCallback( function( $entityIds, $claimIds, $constraintIds ) {
				$results = [];
				foreach ( $entityIds as $entityId ) {
					$serialization = $entityId->getSerialization();
					$results[$serialization] = 'garbage of ' . $serialization;
				}
				return new CachedCheckConstraintsResponse( $results, Metadata::blank() );
			} );
		/** @var CachingResultsBuilder $cachingResultsBuilder */

		$results = $cachingResultsBuilder->getResults(
			$entityIds,
			[],
			null,
			$statuses
		);

		$expected = [ 'Q5' => 'garbage of Q5', 'Q10' => 'garbage of Q10' ];
		$actual = $results->getArray();
		asort( $expected );
		asort( $actual );
		$this->assertSame( $expected, $actual );
		$this->assertFalse( $results->getMetadata()->getCachingMetadata()->isCached() );
	}

	public function testGetResults_FutureDateStillInFuture() {
		$metaDataAccessor = $this->getMock( WikiPageEntityMetaDataAccessor::class );
		$metaDataAccessor->method( 'loadRevisionInformation' )
			->willReturn( [] );
		$resultsCache = new ResultsCache( new WANObjectCache( [ 'cache' => new HashBagOStuff() ] ) );
		$cachingResultsBuilder = $this->getCachingResultsBuilder(
			$this->getMock( ResultsBuilder::class ),
			$resultsCache,
			$metaDataAccessor
		);

		$q5 = new ItemId( 'Q5' );
		$expectedResults = 'garbage data, should not matter';
		$futureTimestamp = '+20018-02-16T00:00:00Z';
		$value = [
			'results' => $expectedResults,
			'latestRevisionIds' => [],
			'futureTime' => [
				'time' => $futureTimestamp,
				'timezone' => 0,
				'before' => 0,
				'after' => 0,
				'precision' => TimeValue::PRECISION_DAY,
				'calendarmodel' => TimeValue::CALENDAR_GREGORIAN,
			],
		];
		$resultsCache->set( $q5, $value );

		$response = $cachingResultsBuilder->getStoredResults( $q5 );

		$this->assertNotNull( $response );
		$this->assertSame( [ 'Q5' => $expectedResults ], $response->getArray() );
		$dependencyMetadata = $response->getMetadata()->getDependencyMetadata();
		$this->assertNotNull( $dependencyMetadata->getFutureTime() );
		$this->assertSame( $futureTimestamp, $dependencyMetadata->getFutureTime()->getTime() );
	}

	public function testGetResults_FutureDateNowInPast() {
		$metaDataAccessor = $this->getMock( WikiPageEntityMetaDataAccessor::class );
		$metaDataAccessor->method( 'loadRevisionInformation' )
			->willReturn( [] );
		$resultsCache = new ResultsCache( new WANObjectCache( [ 'cache' => new HashBagOStuff() ] ) );
		$cachingResultsBuilder = $this->getCachingResultsBuilder(
			$this->getMock( ResultsBuilder::class ),
			$resultsCache,
			$metaDataAccessor
		);

		$q5 = new ItemId( 'Q5' );
		$expectedResults = 'garbage data, should not matter';
		$pastTimestamp = '+2018-02-14T00:00:00Z';
		$value = [
			'results' => $expectedResults,
			'latestRevisionIds' => [],
			'futureTime' => [
				'time' => $pastTimestamp,
				'timezone' => 0,
				'before' => 0,
				'after' => 0,
				'precision' => TimeValue::PRECISION_DAY,
				'calendarmodel' => TimeValue::CALENDAR_GREGORIAN,
			],
		];
		$resultsCache->set( $q5, $value );

		$response = $cachingResultsBuilder->getStoredResults( $q5 );

		$this->assertNull( $response );
	}

	public function testGetResults_ConstraintIds() {
		$entityIds = [ new ItemId( 'Q5' ), new ItemId( 'Q10' ) ];
		$statementIds = [];
		$constraintIds = [ 'P12$11a14ea5-10dc-425b-b94d-6e65997be983' ];
		$statuses = [ 'garbage status', 'other status' ];
		$expected = new CachedCheckConstraintsResponse(
			[ 'Q5' => 'garbage of Q5', 'Q10' => 'some garbage of Q10' ],
			Metadata::ofCachingMetadata( CachingMetadata::ofMaximumAgeInSeconds( 5 * 60 ) )
		);
		$cachingResultsBuilder = $this->getCachingResultsBuilderMock(
			$this->getMock( ResultsBuilder::class ),
			new ResultsCache( WANObjectCache::newEmpty() ),
			$this->getMock( WikiPageEntityMetaDataAccessor::class )
		);
		$cachingResultsBuilder->expects( $this->never() )->method( 'getStoredResults' );
		$cachingResultsBuilder->expects( $this->once() )->method( 'getAndStoreResults' )
			->with( $entityIds, $statementIds, $constraintIds, $statuses )
			->willReturn( $expected );
		/** @var CachingResultsBuilder $cachingResultsBuilder */

		$results = $cachingResultsBuilder->getResults(
			$entityIds,
			$statementIds,
			$constraintIds,
			$statuses
		);

		$this->assertSame( $expected->getArray(), $results->getArray() );
		$this->assertEquals( $expected->getMetadata(), $results->getMetadata() );
	}

	public function testGetResults_StatementIds() {
		$entityIds = [];
		$statementIds = [ 'Q5$9c009c6f-fdf5-41d1-86e9-e790427e3dc6' ];
		$constraintIds = [];
		$statuses = [ 'garbage status', 'other status' ];
		$expected = new CachedCheckConstraintsResponse(
			[ 'Q5' => 'some garbage of Q5' ],
			Metadata::ofCachingMetadata( CachingMetadata::ofMaximumAgeInSeconds( 5 * 60 ) )
		);
		$cachingResultsBuilder = $this->getCachingResultsBuilderMock(
			$this->getMock( ResultsBuilder::class ),
			new ResultsCache( WANObjectCache::newEmpty() ),
			$this->getMock( WikiPageEntityMetaDataAccessor::class )
		);
		$cachingResultsBuilder->expects( $this->never() )->method( 'getStoredResults' );
		$cachingResultsBuilder->expects( $this->once() )->method( 'getAndStoreResults' )
			->with( $entityIds, $statementIds, $constraintIds, $statuses )
			->willReturn( $expected );
		/** @var CachingResultsBuilder $cachingResultsBuilder */

		$results = $cachingResultsBuilder->getResults(
			$entityIds,
			$statementIds,
			$constraintIds,
			$statuses
		);

		$this->assertSame( $expected->getArray(), $results->getArray() );
		$this->assertEquals( $expected->getMetadata(), $results->getMetadata() );
	}

	public function testGetResults_FullyCached() {
		$entityIds = [ new ItemId( 'Q5' ), new ItemId( 'Q10' ) ];
		$statuses = [
			CheckResult::STATUS_VIOLATION,
			CheckResult::STATUS_WARNING,
			CheckResult::STATUS_BAD_PARAMETERS
		];
		$cachingResultsBuilder = $this->getCachingResultsBuilderMock(
			$this->getMock( ResultsBuilder::class ),
			new ResultsCache( WANObjectCache::newEmpty() ),
			$this->getMock( WikiPageEntityMetaDataAccessor::class )
		);
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

		$results = $cachingResultsBuilder->getResults(
			$entityIds,
			[],
			null,
			$statuses
		);

		$expected = [ 'Q5' => 'garbage of Q5', 'Q10' => 'garbage of Q10' ];
		$actual = $results->getArray();
		asort( $expected );
		asort( $actual );
		$this->assertSame( $expected, $actual );
		$this->assertSame( 64800, $results->getMetadata()->getCachingMetadata()->getMaximumAgeInSeconds() );
	}

	public function testGetResults_PartiallyCached() {
		$entityIds = [ new ItemId( 'Q5' ), new ItemId( 'Q10' ) ];
		$statuses = [
			CheckResult::STATUS_VIOLATION,
			CheckResult::STATUS_WARNING,
			CheckResult::STATUS_BAD_PARAMETERS
		];
		$cachingResultsBuilder = $this->getCachingResultsBuilderMock(
			$this->getMock( ResultsBuilder::class ),
			new ResultsCache( WANObjectCache::newEmpty() ),
			$this->getMock( WikiPageEntityMetaDataAccessor::class )
		);
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
			->with( [ $entityIds[1] ], [], null, $statuses )
			->willReturn( new CachedCheckConstraintsResponse(
				[ 'Q10' => 'garbage of Q10' ],
				Metadata::ofDependencyMetadata( DependencyMetadata::ofEntityId( $entityIds[1] ) )
			) );
		/** @var CachingResultsBuilder $cachingResultsBuilder */

		$results = $cachingResultsBuilder->getResults(
			$entityIds,
			[],
			null,
			$statuses
		);

		$expected = [ 'Q5' => 'garbage of Q5', 'Q10' => 'garbage of Q10' ];
		$actual = $results->getArray();
		asort( $expected );
		asort( $actual );
		$this->assertSame( $expected, $actual );
		$metadata = $results->getMetadata();
		$this->assertSame( 64800, $metadata->getCachingMetadata()->getMaximumAgeInSeconds() );
		$this->assertSame( [ $entityIds[1] ], $metadata->getDependencyMetadata()->getEntityIds() );
	}

	public function testGetResults_SkipCacheWithWrongStatuses() {
		$entityIds = [ new ItemId( 'Q5' ), new ItemId( 'Q10' ) ];
		$statuses = [
			CheckResult::STATUS_VIOLATION,
			CheckResult::STATUS_WARNING,
			CheckResult::STATUS_BAD_PARAMETERS,
			CheckResult::STATUS_TODO,
		];
		$expected = new CachedCheckConstraintsResponse(
			[ 'Q5' => 'some garbage of Q5' ],
			Metadata::ofCachingMetadata( CachingMetadata::ofMaximumAgeInSeconds( 5 * 60 ) )
		);
		$cachingResultsBuilder = $this->getCachingResultsBuilderMock(
			$this->getMock( ResultsBuilder::class ),
			new ResultsCache( WANObjectCache::newEmpty() ),
			$this->getMock( WikiPageEntityMetaDataAccessor::class )
		);
		$cachingResultsBuilder->expects( $this->never() )->method( 'getStoredResults' );
		$cachingResultsBuilder->expects( $this->once() )->method( 'getAndStoreResults' )
			->with( $entityIds, [], null, $statuses )
			->willReturn( $expected );
		/** @var CachingResultsBuilder $cachingResultsBuilder */

		$cachingResultsBuilder->getResults(
			$entityIds,
			[],
			null,
			$statuses
		);
	}

	public function testUpdateCachingMetadata_CachedToplevel() {
		$cachingResultsBuilder = $this->getCachingResultsBuilder(
			$this->getMock( ResultsBuilder::class ),
			new ResultsCache( WANObjectCache::newEmpty() ),
			$this->getMock( WikiPageEntityMetaDataAccessor::class )
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
		$cachingResultsBuilder = $this->getCachingResultsBuilder(
			$this->getMock( ResultsBuilder::class ),
			new ResultsCache( WANObjectCache::newEmpty() ),
			$this->getMock( WikiPageEntityMetaDataAccessor::class )
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
		$cachingResultsBuilder = $this->getCachingResultsBuilder(
			$this->getMock( ResultsBuilder::class ),
			new ResultsCache( WANObjectCache::newEmpty() ),
			$this->getMock( WikiPageEntityMetaDataAccessor::class ),
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
