<?php

namespace WikibaseQuality\ConstraintReport\Tests\Helper;

use Config;
use DataValues\DataValueFactory;
use DataValues\Deserializers\DataValueDeserializer;
use DataValues\Geo\Values\GlobeCoordinateValue;
use DataValues\Geo\Values\LatLongValue;
use DataValues\MonolingualTextValue;
use DataValues\StringValue;
use DataValues\TimeValue;
use DataValues\UnboundedQuantityValue;
use HashBagOStuff;
use HashConfig;
use MediaWiki\Http\HttpRequestFactory;
use NullStatsdDataFactory;
use PHPUnit4And6Compat;
use WANObjectCache;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\ItemIdParser;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\Lookup\InMemoryDataTypeLookup;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\Rdf\RdfVocabulary;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\Api\ExpiryLock;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachedQueryResults;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\Metadata;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\Context;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\ContextCursor;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterException;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\LoggingHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\SparqlHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\TooManySparqlRequestsException;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessage;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageDeserializer;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageSerializer;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikibaseQuality\ConstraintReport\Role;
use WikibaseQuality\ConstraintReport\Tests\DefaultConfig;
use WikibaseQuality\ConstraintReport\Tests\ResultAssertions;
use Wikimedia\TestingAccessWrapper;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\SparqlHelper
 *
 * @group WikibaseQualityConstraints
 *
 * @author Lucas Werkmeister
 * @license GPL-2.0-or-later
 */
class SparqlHelperTest extends \PHPUnit\Framework\TestCase {
	use PHPUnit4And6Compat;

	use DefaultConfig, ResultAssertions;

	public function tearDown() {
		ConvertibleTimestamp::setFakeTime( false );

		parent::tearDown();
	}

	private function selectResults( array $bindings ) {
		return new CachedQueryResults(
			[ 'results' => [ 'bindings' => $bindings ] ],
			Metadata::blank()
		);
	}

	private function askResult( $boolean ) {
		return new CachedQueryResults(
			[ 'boolean' => $boolean ],
			Metadata::blank()
		);
	}

	private function getSparqlHelper(
		Config $config = null,
		PropertyDataTypeLookup $dataTypeLookup = null,
		LoggingHelper $loggingHelper = null
	) {
		if ( $config === null ) {
			$config = $this->getDefaultConfig();
		}
		if ( $dataTypeLookup === null ) {
			$dataTypeLookup = new InMemoryDataTypeLookup();
		}
		if ( $loggingHelper === null ) {
			$loggingHelper = $this->createMock( LoggingHelper::class );
		}

		$entityIdParser = new ItemIdParser();

		return $this->getMockBuilder( SparqlHelper::class )
			->setConstructorArgs( [
				$config,
				new RdfVocabulary(
					[ '' => 'http://www.wikidata.org/entity/' ],
					'http://www.wikidata.org/wiki/Special:EntityData/'
				),
				$entityIdParser,
				$dataTypeLookup,
				WANObjectCache::newEmpty(),
				new ViolationMessageSerializer(),
				new ViolationMessageDeserializer(
					$entityIdParser,
					new DataValueFactory( new DataValueDeserializer() )
				),
				new NullStatsdDataFactory(),
				new ExpiryLock( new HashBagOStuff() ),
				$loggingHelper,
				'A fancy user agent',
				$this->createMock( HttpRequestFactory::class )
			] )
			->setMethods( [ 'runQuery' ] )
			->getMock();
	}

	public function testHasType() {
		$sparqlHelper = $this->getSparqlHelper();

		$query = <<<EOF
ASK {
  BIND(wd:Q1 AS ?item)
  VALUES ?class { wd:Q100 wd:Q101 }
  ?item wdt:P31/wdt:P279* ?class. hint:Prior hint:gearing "forward".
}
EOF;

		$sparqlHelper->expects( $this->exactly( 1 ) )
			->method( 'runQuery' )
			->willReturn( $this->askResult( true ) )
			->withConsecutive( [ $this->equalTo( $query ) ] );

		$this->assertTrue( $sparqlHelper->hasType( 'Q1', [ 'Q100', 'Q101' ], true )->getBool() );
	}

	public function testFindEntitiesWithSameStatement() {
		$guid = 'Q1$8542690f-dfab-4846-944f-8382df730d2c';
		$statement = new Statement(
			new PropertyValueSnak( new PropertyId( 'P1' ), new EntityIdValue( new ItemId( 'Q1' ) ) ),
			null,
			null,
			$guid
		);

		$sparqlHelper = $this->getSparqlHelper();

		$query = <<<EOF
SELECT DISTINCT ?otherEntity WHERE {
  BIND(wds:Q1-8542690f-dfab-4846-944f-8382df730d2c AS ?statement)
  BIND(p:P1 AS ?p)
  BIND(ps:P1 AS ?ps)
  ?entity ?p ?statement.
  ?statement ?ps ?value.
  ?otherStatement ?ps ?value.
  ?otherEntity ?p ?otherStatement.
  FILTER(?otherEntity != ?entity)
  MINUS { ?otherStatement wikibase:rank wikibase:DeprecatedRank. }MINUS { ?otherStatement wikibase-beta:rank wikibase-beta:DeprecatedRank. }
}
LIMIT 10
EOF;

		$sparqlHelper->expects( $this->exactly( 1 ) )
			->method( 'runQuery' )
			->willReturn( $this->selectResults( [
				[ 'otherEntity' => [ 'type' => 'uri', 'value' => 'http://www.wikidata.org/entity/Q100' ] ],
				[ 'otherEntity' => [ 'type' => 'uri', 'value' => 'http://www.wikidata.org/entity/Q101' ] ],
			] ) )
			->withConsecutive( [ $this->equalTo( $query ) ] );

		$this->assertEquals(
			$sparqlHelper->findEntitiesWithSameStatement( $statement, true )->getArray(),
			[ new ItemId( 'Q100' ), new ItemId( 'Q101' ) ]
		);
	}

	/**
	 * @dataProvider provideSnaksWithSparqlValuesAndPropertyPaths
	 */
	public function testFindEntitiesWithSameQualifierOrReference(
		PropertyValueSnak $snak,
		$dataType,
		$contextType,
		$sparqlValue,
		$sparqlPath
	) {
		$dtLookup = $this->getMock( PropertyDataTypeLookup::class );
		$dtLookup->method( 'getDataTypeIdForProperty' )->willReturn( $dataType );

		$sparqlHelper = $this->getSparqlHelper( null, $dtLookup );

		$query = <<<EOF
SELECT DISTINCT ?otherEntity WHERE {
  BIND(wd:Q10 AS ?entity)
  BIND($sparqlValue AS ?value)
  ?entity ?p ?statement.
  ?statement $sparqlPath ?value.
  ?otherStatement $sparqlPath ?value.
  ?otherEntity ?otherP ?otherStatement.
  FILTER(?otherEntity != ?entity)

}
LIMIT 10
EOF;

		$sparqlHelper->expects( $this->exactly( 1 ) )
			->method( 'runQuery' )
			->willReturn( $this->selectResults( [
				[ 'otherEntity' => [ 'type' => 'uri', 'value' => 'http://www.wikidata.org/entity/Q100' ] ],
				[ 'otherEntity' => [ 'type' => 'uri', 'value' => 'http://www.wikidata.org/entity/Q101' ] ],
			] ) )
			->withConsecutive( [ $this->equalTo( $query ) ] );

		$this->assertEquals(
			$sparqlHelper->findEntitiesWithSameQualifierOrReference(
				new ItemId( 'Q10' ),
				$snak,
				$contextType,
				false
			)->getArray(),
			[ new ItemId( 'Q100' ), new ItemId( 'Q101' ) ]
		);
	}

	public function provideSnaksWithSparqlValuesAndPropertyPaths() {
		$pid = new PropertyId( 'P1' );
		$globeCoordinateValue = new GlobeCoordinateValue( new LatLongValue( 42.0, 13.37 ) );
		$quantityValue = UnboundedQuantityValue::newFromNumber( -10, 'ms' );
		$timeValue = new TimeValue(
			'+00000001970-01-01T00:00:00Z',
			0,
			0,
			0,
			TimeValue::PRECISION_DAY,
			TimeValue::CALENDAR_GREGORIAN
		);
		return [
			'string, qualifier' => [
				new PropertyValueSnak( $pid, new StringValue( 'foo' ) ),
				'string',
				'qualifier',
				'"foo"',
				'pq:P1'
			],
			'external identifier, reference' => [
				new PropertyValueSnak( $pid, new StringValue( 'f00' ) ),
				'external-id',
				'reference',
				'"f00"',
				'prov:wasDerivedFrom/pr:P1'
			],
			'Commons media, qualifier' => [
				new PropertyValueSnak( $pid, new StringValue( 'Bar.jpg' ) ),
				'commonsMedia',
				'qualifier',
				'<http://commons.wikimedia.org/wiki/Special:FilePath/Bar.jpg>',
				'pq:P1'
			],
			'geoshape, reference' => [
				new PropertyValueSnak( $pid, new StringValue( 'Baznia.map' ) ),
				'geo-shape',
				'reference',
				'<http://commons.wikimedia.org/data/main/Baznia.map>',
				'prov:wasDerivedFrom/pr:P1'
			],
			'tabular data, qualifier' => [
				new PropertyValueSnak( $pid, new StringValue( 'Qux.tab' ) ),
				'tabular-data',
				'qualifier',
				'<http://commons.wikimedia.org/data/main/Qux.tab>',
				'pq:P1'
			],
			'url, reference' => [
				new PropertyValueSnak( $pid, new StringValue( 'https://wikibase.example/url' ) ),
				'url',
				'reference',
				'<https://wikibase.example/url>',
				'prov:wasDerivedFrom/pr:P1'
			],
			'item, qualifier' => [
				new PropertyValueSnak( $pid, new EntityIdValue( new ItemId( 'Q100' ) ) ),
				'wikibase-item',
				'qualifier',
				'wd:Q100',
				'pq:P1'
			],
			'property, reference' => [
				new PropertyValueSnak( $pid, new EntityIdValue( new PropertyId( 'P100' ) ) ),
				'wikibase-property',
				'reference',
				'wd:P100',
				'prov:wasDerivedFrom/pr:P1'
			],
			'monolingual text, qualifier' => [
				new PropertyValueSnak( $pid, new MonolingualTextValue( 'qqx', 'lorem ipsum' ) ),
				'monolingualtext',
				'qualifier',
				'"lorem ipsum"@qqx',
				'pq:P1'
			],
			'globe coordinate, reference' => [
				new PropertyValueSnak( $pid, $globeCoordinateValue ),
				'globe-coordinate',
				'reference',
				"wdv:{$globeCoordinateValue->getHash()}",
				'prov:wasDerivedFrom/prv:P1'
			],
			'quantity, qualifier' => [
				new PropertyValueSnak( $pid, $quantityValue ),
				'quantity',
				'qualifier',
				"wdv:{$quantityValue->getHash()}",
				'pqv:P1'
			],
			'time, reference' => [
				new PropertyValueSnak( $pid, $timeValue ),
				'time',
				'reference',
				"wdv:{$timeValue->getHash()}",
				'prov:wasDerivedFrom/prv:P1'
			],
		];
	}

	public function testSerializeConstraintParameterException() {
		$cpe = new ConstraintParameterException(
			( new ViolationMessage( 'wbqc-violation-message-parameter-regex' ) )
				->withInlineCode( '[', Role::CONSTRAINT_PARAMETER_VALUE )
		);
		$sparqlHelper = TestingAccessWrapper::newFromObject( $this->getSparqlHelper() );

		$serialization = $sparqlHelper->serializeConstraintParameterException( $cpe );

		$expected = [
			'type' => ConstraintParameterException::class,
			'violationMessage' => [
				'k' => 'parameter-regex',
				'a' => [
					[ 't' => ViolationMessage::TYPE_INLINE_CODE, 'v' => '[', 'r' => Role::CONSTRAINT_PARAMETER_VALUE ],
				],
			],
		];
		$this->assertSame( $expected, $serialization );
	}

	public function testDeserializeConstraintParameterException() {
		$serialization = [
			'type' => ConstraintParameterException::class,
			'violationMessage' => [
				'k' => 'parameter-regex',
				'a' => [
					[ 't' => ViolationMessage::TYPE_INLINE_CODE, 'v' => '[', 'r' => Role::CONSTRAINT_PARAMETER_VALUE ],
				],
			],
		];
		$sparqlHelper = TestingAccessWrapper::newFromObject( $this->getSparqlHelper() );

		$cpe = $sparqlHelper->deserializeConstraintParameterException( $serialization );

		$expected = new ConstraintParameterException(
			( new ViolationMessage( 'wbqc-violation-message-parameter-regex' ) )
				->withInlineCode( '[', Role::CONSTRAINT_PARAMETER_VALUE )
		);
		$this->assertEquals( $expected, $cpe );
	}

	public function testMatchesRegularExpressionWithSparql() {
		$text = '"&quot;\'\\\\"<&lt;'; // "&quot;'\\"<&lt;
		$regex = '\\"\\\\"\\\\\\"'; // \"\\"\\\"
		$query = 'SELECT (REGEX("\\"&quot;\'\\\\\\\\\\"<&lt;", "^(?:\\\\\\"\\\\\\\\\\"\\\\\\\\\\\\\\")$") AS ?matches) {}';
		$sparqlHelper = $this->getSparqlHelper();

		$sparqlHelper->expects( $this->once() )
			->method( 'runQuery' )
			->with( $this->equalTo( $query ) )
			->willReturn( $this->selectResults( [ [ 'matches' => [ 'value' => 'false' ] ] ] ) );

		$result = $sparqlHelper->matchesRegularExpressionWithSparql( $text, $regex );

		$this->assertFalse( $result );
	}

	public function testMatchesRegularExpressionWithSparqlBadRegex() {
		$text = '';
		$regex = '(.{2,5)?';
		$query = 'SELECT (REGEX("", "^(?:(.{2,5)?)$") AS ?matches) {}';
		$sparqlHelper = $this->getSparqlHelper();
		$messageKey = 'wbqc-violation-message-parameter-regex';

		$sparqlHelper->expects( $this->once() )
			->method( 'runQuery' )
			->with( $this->equalTo( $query ) )
			->willReturn( $this->selectResults( [ [] ] ) );

		try {
			call_user_func_array( [ $sparqlHelper, 'matchesRegularExpressionWithSparql' ], [ $text, $regex ] );
			$this->assertTrue( false,
				"matchesRegularExpressionWithSparql should have thrown a ConstraintParameterException with message ⧼${messageKey}⧽." );
		} catch ( ConstraintParameterException $exception ) {
			$checkResult = new CheckResult(
				$this->getMock( ContextCursor::class ),
				$this->getMockBuilder( Constraint::class )->disableOriginalConstructor()->getMock(),
				[],
				CheckResult::STATUS_VIOLATION,
				$exception->getViolationMessage()
			);
			$this->assertViolation( $checkResult, $messageKey );
		}
	}

	/**
	 * @dataProvider provideTimeoutMessages
	 */
	public function testIsTimeout( $content, $expected ) {
		$sparqlHelper = $this->getSparqlHelper();

		$actual = $sparqlHelper->isTimeout( $content );

		$this->assertSame( $expected, $actual );
	}

	public function testIsTimeoutRegex() {
		$sparqlHelper = $this->getSparqlHelper(
			new HashConfig( [
				'WBQualityConstraintsSparqlTimeoutExceptionClasses' => [
					'(?!this may look like a regular expression)',
					'/but should not be interpreted as one/',
					'(x+x+)+y',
				]
			] )
		);
		$content = '(x+x+)+y';

		$actual = $sparqlHelper->isTimeout( $content );

		$this->assertTrue( $actual );
	}

	public function provideTimeoutMessages() {
		return [
			'empty' => [
				'',
				false
			],
			'syntax error' => [
				'org.openrdf.query.MalformedQueryException: ' .
					'Encountered "<EOF>" at line 1, column 6.',
				false
			],
			'QueryTimeoutException' => [
				'java.util.concurrent.ExecutionException: ' .
					'java.util.concurrent.ExecutionException: ' .
					'org.openrdf.query.QueryInterruptedException: ' .
					'java.lang.RuntimeException: ' .
					'java.util.concurrent.ExecutionException: ' .
					'com.bigdata.bop.engine.QueryTimeoutException: ' .
					'Query deadline is expired.',
				true
			],
			'TimeoutException' => [
				"java.util.concurrent.TimeoutException\n" .
					"\tat java.util.concurrent.FutureTask.get(FutureTask.java:205)\n" .
					"\tat com.bigdata.rdf.sail.webapp.BigdataServlet.submitApiTask(BigdataServlet.java:289)\n" .
					"\tat com.bigdata.rdf.sail.webapp.QueryServlet.doSparqlQuery(QueryServlet.java:653)\n",
				true
			],
		];
	}

	/**
	 * @dataProvider provideCacheHeaders
	 */
	 public function testGetCacheMaxAge( $responseHeaders, $expected ) {
		$sparqlHelper = $this->getSparqlHelper();

		$actual = $sparqlHelper->getCacheMaxAge( $responseHeaders );

		$this->assertSame( $expected, $actual );
	 }

	 public function provideCacheHeaders() {
		 return [
			 'WDQS hit' => [
				 [ 'x-cache-status' => [ 'hit-front' ], 'cache-control' => [ 'public, max-age=300' ] ],
				 300
			 ],
			 'WDQS miss' => [
				 [ 'x-cache-status' => [ 'miss' ], 'cache-control' => [ 'public, max-age=300' ] ],
				 false
			 ],
			 'generic hit' => [
				 [ 'x-cache-status' => [ 'hit' ] ],
				 true
			 ],
		 ];
	 }

	 public function testrunQuerySetsLock_if429HeadersAndRetryAfterSet() {
		$lock = $this->createMock( ExpiryLock::class );
		$retryAfter = 1000;
		$responseMock = [ 'Retry-After' => $retryAfter ];

		$requestMock = $this->createMock( \MWHttpRequest::class );
		$requestMock->method( 'getResponseHeaders' )
			->willReturn( $responseMock );
		$requestMock->method( 'getStatus' )
			->willReturn( 429 );

		$requestFactoryMock = $this->createMock( HttpRequestFactory::class );
		$requestFactoryMock->method( 'create' )
			->willReturn( $requestMock );

		 $fakeNow = 5000;
		 ConvertibleTimestamp::setFakeTime( $fakeNow );
		 $expectedTimestamp = $retryAfter + $fakeNow;

		 $loggingHelper = $this->getLoggingHelperExpectingRetryAfterPresent( new ConvertibleTimestamp( $expectedTimestamp ) );
		 $lock->expects( $this->once() )
			 ->method( 'lock' )
			 ->with( $this->equalTo( SparqlHelper::EXPIRY_LOCK_ID ),
				 $this->callback( function ( $actualTimestamp ) use ( $expectedTimestamp ) {
					 $actualUnixTime = $actualTimestamp->format( 'U' );
					 return $actualUnixTime == $expectedTimestamp;
				 } )
			 );

		 $sparqlHelper = new SparqlHelper(
			$this->getDefaultConfig(),
			$this->createMock( RdfVocabulary::class ),
			$this->createMock( EntityIdParser::class ),
			$this->createMock( PropertyDataTypeLookup::class ),
			$this->createMock( WANObjectCache::class ),
			$this->createMock( ViolationMessageSerializer::class ),
			$this->createMock( ViolationMessageDeserializer::class ),
			$this->createMock( \IBufferingStatsdDataFactory::class ),
			$lock,
			$loggingHelper,
			'',
			$requestFactoryMock
		);

		$this->setExpectedException( TooManySparqlRequestsException::class );
		$sparqlHelper->runQuery( 'fake query' );
	 }

	public function testRunQuerySetsLock_if429HeadersButRetryAfterMissing() {
		$fakeNow = 5000;
		ConvertibleTimestamp::setFakeTime( $fakeNow );

		$requestMock = $this->createMock( \MWHttpRequest::class );
		$requestMock->method( 'getStatus' )
			->willReturn( 429 );
		$requestMock->method( 'getResponseHeaders' )
			->willReturn( [] );

		$requestFactoryMock = $this->createMock( HttpRequestFactory::class );
		$requestFactoryMock->method( 'create' )
			->willReturn( $requestMock );

		$config = $this->getDefaultConfig();
		$defaultWait = 154;
		$config->set( 'WBQualityConstraintsSparqlThrottlingFallbackDuration', $defaultWait );

		$expectedTimestamp = $defaultWait + $fakeNow;

		$loggingHelper = $this->getLoggingHelperExpectingRetryAfterMissing();

		$sparqlHelper = new SparqlHelper(
			$config,
			$this->createMock( RdfVocabulary::class ),
			$this->createMock( EntityIdParser::class ),
			$this->createMock( PropertyDataTypeLookup::class ),
			$this->createMock( WANObjectCache::class ),
			$this->createMock( ViolationMessageSerializer::class ),
			$this->createMock( ViolationMessageDeserializer::class ),
			$this->createMock( \IBufferingStatsdDataFactory::class ),
			$this->getMockLock( SparqlHelper::EXPIRY_LOCK_ID, $expectedTimestamp ),
			$loggingHelper,
			'',
			$requestFactoryMock
		);

		$this->setExpectedException( TooManySparqlRequestsException::class );
		$sparqlHelper->runQuery( 'fake query' );
	}

	 public function testRunQueryDoesNotQuery_ifLockIsLocked() {
		 $lock = $this->createMock( ExpiryLock::class );
		 $lock->method( 'isLocked' )
			 ->willReturn( true );

		 $requestMock = $this->createMock( \MWHttpRequest::class );
		 $requestMock->expects( $this->never() )
			 ->method( 'execute' );

		 $requestFactoryMock = $this->createMock( HttpRequestFactory::class );
		 $requestFactoryMock->method( 'create' )
			 ->willReturn( $requestMock );

		 $sparqlHelper = new SparqlHelper(
			 $this->getDefaultConfig(),
			 $this->createMock( RdfVocabulary::class ),
			 $this->createMock( EntityIdParser::class ),
			 $this->createMock( PropertyDataTypeLookup::class ),
			 $this->createMock( WANObjectCache::class ),
			 $this->createMock( ViolationMessageSerializer::class ),
			 $this->createMock( ViolationMessageDeserializer::class ),
			 $this->createMock( \IBufferingStatsdDataFactory::class ),
			 $lock,
			 $this->createMock( LoggingHelper::class ),
			 '',
			 $requestFactoryMock
		 );

		 $this->setExpectedException( TooManySparqlRequestsException::class );
		 $sparqlHelper->runQuery( 'foo baz' );
	 }

	public function testRunQuerySetsLock_if429HeadersPresentAndRetryAfterMalformed() {

		$requestFactoryMock = $this->getMock429RequestFactory( [ 'Retry-After' => 'malformedthing' ] );

		$config = $this->getDefaultConfig();
		$defaultWait = 154;
		$config->set( 'WBQualityConstraintsSparqlThrottlingFallbackDuration', $defaultWait );
		$fakeNow = 5000;
		ConvertibleTimestamp::setFakeTime( $fakeNow );

		$lock = $this->getMockLock( SparqlHelper::EXPIRY_LOCK_ID, $defaultWait + $fakeNow );

		$loggingHelper = $this->getLoggingHelperExpectingRetryAfterMissing();

		$sparqlHelper = new SparqlHelper(
			$config,
			$this->createMock( RdfVocabulary::class ),
			$this->createMock( EntityIdParser::class ),
			$this->createMock( PropertyDataTypeLookup::class ),
			$this->createMock( WANObjectCache::class ),
			$this->createMock( ViolationMessageSerializer::class ),
			$this->createMock( ViolationMessageDeserializer::class ),
			$this->createMock( \IBufferingStatsdDataFactory::class ),
			$lock,
			$loggingHelper,
			'',
			$requestFactoryMock
		);

		$this->setExpectedException( TooManySparqlRequestsException::class );
		$sparqlHelper->runQuery( 'foo baz' );
	}

	private function getMockLock( $expectedLockId, $expectedLockExpiryTimestamp ) {
		$lock = $this->createMock( ExpiryLock::class );
		$lock->expects( $this->once() )
			->method( 'lock' )
			->with(
				$this->equalTo( $expectedLockId ),
				$this->callback(
					function ( $actualTimestamp ) use ( $expectedLockExpiryTimestamp ) {
						$actualUnixTime = $actualTimestamp->format( 'U' );
						return $actualUnixTime == $expectedLockExpiryTimestamp;
					}
				)
			);
		return $lock;
	}

	private function getMock429RequestFactory( $requestHeaders = [] ) {
		$requestMock = $this->createMock( \MWHttpRequest::class );

		$requestMock->method( 'getStatus' )
			->willReturn( 429 );

		$requestMock->method( 'getResponseHeaders' )
			->willReturn( [] );

		$requestFactoryMock = $this->createMock( HttpRequestFactory::class );
		$requestFactoryMock->method( 'create' )
			->willReturn( $requestMock );

		return $requestFactoryMock;
	}

	private function getLoggingHelperExpectingRetryAfterPresent( $retryTime ) {
		$helper = $this->createMock( LoggingHelper::class );
		$helper->expects( $this->once() )
			->method( 'logSparqlHelperTooManyRequestsRetryAfterPresent' )
			->with( $this->equalTo( $retryTime ), $this->anything() );
		return $helper;
	}

	private function getLoggingHelperExpectingRetryAfterMissing() {
		$helper = $this->createMock( LoggingHelper::class );
		$helper->expects( $this->once() )
			->method( 'logSparqlHelperTooManyRequestsRetryAfterInvalid' );
		return $helper;
	}

}
