<?php

declare( strict_types = 1 );

namespace WikibaseQuality\ConstraintReport\Tests\Helper;

use DataValues\Deserializers\DataValueDeserializer;
use DataValues\Geo\Values\GlobeCoordinateValue;
use DataValues\Geo\Values\LatLongValue;
use DataValues\MonolingualTextValue;
use DataValues\StringValue;
use DataValues\TimeValue;
use DataValues\UnboundedQuantityValue;
use MediaWiki\Config\Config;
use MediaWiki\Config\HashConfig;
use MediaWiki\Config\MultiConfig;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Status\Status;
use Wikibase\DataAccess\DatabaseEntitySource;
use Wikibase\DataAccess\EntitySourceDefinitions;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\ItemIdParser;
use Wikibase\DataModel\Entity\NumericPropertyId;
use Wikibase\DataModel\Services\Lookup\InMemoryDataTypeLookup;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\Lib\DataValueFactory;
use Wikibase\Lib\SubEntityTypesMapper;
use Wikibase\Repo\Rdf\RdfVocabulary;
use WikibaseQuality\ConstraintReport\Api\ExpiryLock;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachedQueryResults;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\Metadata;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\ContextCursor;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterException;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\LoggingHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\SparqlHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\SparqlHelperException;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\TooManySparqlRequestsException;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessage;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageDeserializer;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageSerializer;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikibaseQuality\ConstraintReport\Role;
use WikibaseQuality\ConstraintReport\Tests\DefaultConfig;
use WikibaseQuality\ConstraintReport\Tests\ResultAssertions;
use Wikimedia\ObjectCache\HashBagOStuff;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\Stats\BufferingStatsdDataFactory;
use Wikimedia\Stats\IBufferingStatsdDataFactory;
use Wikimedia\Stats\NullStatsdDataFactory;
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

	use DefaultConfig;
	use ResultAssertions;

	public function tearDown(): void {
		ConvertibleTimestamp::setFakeTime( false );

		parent::tearDown();
	}

	private function selectResults( array $bindings ): CachedQueryResults {
		return new CachedQueryResults(
			[ 'results' => [ 'bindings' => $bindings ] ],
			Metadata::blank()
		);
	}

	private function askResult( $boolean ): CachedQueryResults {
		return new CachedQueryResults(
			[ 'boolean' => $boolean ],
			Metadata::blank()
		);
	}

	private function getRdfVocabulary(): RdfVocabulary {
		$itemSource = new DatabaseEntitySource(
			'local',
			false,
			[ 'item' => [ 'namespaceId' => 0, 'slot' => SlotRecord::MAIN ] ],
			'http://www.wikidata.org/entity/',
			'wd',
			'',
			'local'
		);

		return new RdfVocabulary(
			[ 'local' => 'http://www.wikidata.org/entity/' ],
			[ 'local' => 'http://www.wikidata.org/wiki/Special:EntityData/' ],
			new EntitySourceDefinitions( [ 'local' => $itemSource ], new SubEntityTypesMapper( [] ) ),
			[ 'local' => 'wd' ],
			[ 'local' => '' ]
		);
	}

	private function getSparqlHelper(
		?Config $config = null,
		?PropertyDataTypeLookup $dataTypeLookup = null,
		?LoggingHelper $loggingHelper = null
	) {
		$entityIdParser = new ItemIdParser();

		return $this->getMockBuilder( SparqlHelper::class )
			->setConstructorArgs( [
				new MultiConfig( [ $config ?? new HashConfig(), self::getDefaultConfig() ] ),
				$this->getRdfVocabulary(),
				$entityIdParser,
				$dataTypeLookup ?? new InMemoryDataTypeLookup(),
				WANObjectCache::newEmpty(),
				new ViolationMessageSerializer(),
				new ViolationMessageDeserializer(
					$entityIdParser,
					new DataValueFactory( new DataValueDeserializer() )
				),
				new NullStatsdDataFactory(),
				new ExpiryLock( new HashBagOStuff() ),
				$loggingHelper ?? $this->createMock( LoggingHelper::class ),
				'A fancy user agent',
				$this->createMock( HttpRequestFactory::class ),
			] )
			->onlyMethods( [ 'runQuery' ] )
			->getMock();
	}

	public function testHasTypeWithoutHint(): void {
		$sparqlHelper = TestingAccessWrapper::newFromObject( $this->getSparqlHelper() );

		$query = <<<EOF
ASK {
  BIND(wd:Q1 AS ?item)
  VALUES ?class { wd:Q100 wd:Q101 }
  ?item wdt:P279* ?class.
}
EOF;

		$sparqlHelper->expects( $this->once() )
			->method( 'runQuery' )
			->willReturn( $this->askResult( true ) )
			->with( $query );

		$this->assertTrue( $sparqlHelper->hasType( 'Q1', [ 'Q100', 'Q101' ] )->getBool() );
	}

	public function testHasTypeWithHint() {
		$sparqlHelper = TestingAccessWrapper::newFromObject( $this->getSparqlHelper( new HashConfig( [
			'WBQualityConstraintsSparqlHasWikibaseSupport' => true,
		] ) ) );

		$query = <<<EOF
ASK {
  BIND(wd:Q1 AS ?item)
  VALUES ?class { wd:Q100 wd:Q101 }
  ?item wdt:P279* ?class. hint:Prior hint:gearing "forward".
}
EOF;

		$sparqlHelper->expects( $this->once() )
			->method( 'runQuery' )
			->willReturn( $this->askResult( true ) )
			->with( $query );

		$this->assertTrue( $sparqlHelper->hasType( 'Q1', [ 'Q100', 'Q101' ] )->getBool() );
	}

	public static function provideSeparatorIdsAndExpectedFilters() {
		$p21 = new NumericPropertyId( 'P21' );
		$p22 = new NumericPropertyId( 'P22' );

		yield [
			[], // No separators shouldn't add filtering or declaration
			'',
		];

		yield [
			[
				$p21, $p22,
			],
<<<EOF
  MINUS {
    ?statement pq:P21 ?qualifier.
    FILTER NOT EXISTS {
      ?otherStatement pq:P21 ?qualifier.
    }
  }
  MINUS {
    ?otherStatement pq:P21 ?qualifier.
    FILTER NOT EXISTS {
      ?statement pq:P21 ?qualifier.
    }
  }
  MINUS {
    ?statement a wdno:P21.
    FILTER NOT EXISTS {
      ?otherStatement a wdno:P21.
    }
  }
  MINUS {
    ?otherStatement a wdno:P21.
    FILTER NOT EXISTS {
      ?statement a wdno:P21.
    }
  }
  MINUS {
    ?statement pq:P22 ?qualifier.
    FILTER NOT EXISTS {
      ?otherStatement pq:P22 ?qualifier.
    }
  }
  MINUS {
    ?otherStatement pq:P22 ?qualifier.
    FILTER NOT EXISTS {
      ?statement pq:P22 ?qualifier.
    }
  }
  MINUS {
    ?statement a wdno:P22.
    FILTER NOT EXISTS {
      ?otherStatement a wdno:P22.
    }
  }
  MINUS {
    ?otherStatement a wdno:P22.
    FILTER NOT EXISTS {
      ?statement a wdno:P22.
    }
  }
EOF
,
		];
	}

	/**
	 * @dataProvider provideSeparatorIdsAndExpectedFilters
	 */
	public function testFindEntitiesWithSameStatement(
		array $separators,
		string $expectedFilter
	): void {
		$guid = 'Q1$8542690f-dfab-4846-944f-8382df730d2c';
		$statement = new Statement(
			new PropertyValueSnak( new NumericPropertyId( 'P1' ), new EntityIdValue( new ItemId( 'Q1' ) ) ),
			null,
			null,
			$guid
		);

		$sparqlHelper = TestingAccessWrapper::newFromObject( $this->getSparqlHelper() );
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
  MINUS { ?otherStatement wikibase:rank wikibase:DeprecatedRank. }
  $expectedFilter
}
LIMIT 10
EOF;

		$sparqlHelper->expects( $this->once() )
			->method( 'runQuery' )
			->willReturn( $this->selectResults( [
				[ 'otherEntity' => [ 'type' => 'uri', 'value' => 'http://www.wikidata.org/entity/Q100' ] ],
				[ 'otherEntity' => [ 'type' => 'uri', 'value' => 'http://www.wikidata.org/entity/Q101' ] ],
			] ) )
			->with( $query );

		$this->assertEquals(
			$sparqlHelper->findEntitiesWithSameStatement( $statement, $separators )->getArray(),
			[ new ItemId( 'Q100' ), new ItemId( 'Q101' ) ]
		);
	}

	/**
	 * @dataProvider provideSnaksWithSparqlValuesAndPropertyPaths
	 */
	public function testFindEntitiesWithSameQualifierOrReference(
		PropertyValueSnak $snak,
		string $dataType,
		string $contextType,
		string $sparqlValue,
		string $sparqlPath
	): void {
		$dtLookup = $this->createMock( PropertyDataTypeLookup::class );
		$dtLookup->method( 'getDataTypeIdForProperty' )->willReturn( $dataType );

		$sparqlHelper = TestingAccessWrapper::newFromObject( $this->getSparqlHelper( null, $dtLookup ) );

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

		$sparqlHelper->expects( $this->once() )
			->method( 'runQuery' )
			->willReturn( $this->selectResults( [
				[ 'otherEntity' => [ 'type' => 'uri', 'value' => 'http://www.wikidata.org/entity/Q100' ] ],
				[ 'otherEntity' => [ 'type' => 'uri', 'value' => 'http://www.wikidata.org/entity/Q101' ] ],
			] ) )
			->with( $query );

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

	public static function provideSnaksWithSparqlValuesAndPropertyPaths(): iterable {
		$pid = new NumericPropertyId( 'P1' );
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
				'pq:P1',
			],
			'external identifier, reference' => [
				new PropertyValueSnak( $pid, new StringValue( 'f00' ) ),
				'external-id',
				'reference',
				'"f00"',
				'prov:wasDerivedFrom/pr:P1',
			],
			'Commons media, qualifier' => [
				new PropertyValueSnak( $pid, new StringValue( 'Bar.jpg' ) ),
				'commonsMedia',
				'qualifier',
				'<http://commons.wikimedia.org/wiki/Special:FilePath/Bar.jpg>',
				'pq:P1',
			],
			'geoshape, reference' => [
				new PropertyValueSnak( $pid, new StringValue( 'Baznia.map' ) ),
				'geo-shape',
				'reference',
				'<http://commons.wikimedia.org/data/main/Baznia.map>',
				'prov:wasDerivedFrom/pr:P1',
			],
			'tabular data, qualifier' => [
				new PropertyValueSnak( $pid, new StringValue( 'Qux.tab' ) ),
				'tabular-data',
				'qualifier',
				'<http://commons.wikimedia.org/data/main/Qux.tab>',
				'pq:P1',
			],
			'url, reference' => [
				new PropertyValueSnak( $pid, new StringValue( 'https://wikibase.example/url' ) ),
				'url',
				'reference',
				'<https://wikibase.example/url>',
				'prov:wasDerivedFrom/pr:P1',
			],
			'item, qualifier' => [
				new PropertyValueSnak( $pid, new EntityIdValue( new ItemId( 'Q100' ) ) ),
				'wikibase-item',
				'qualifier',
				'wd:Q100',
				'pq:P1',
			],
			'property, reference' => [
				new PropertyValueSnak( $pid, new EntityIdValue( new NumericPropertyId( 'P100' ) ) ),
				'wikibase-property',
				'reference',
				'wd:P100',
				'prov:wasDerivedFrom/pr:P1',
			],
			'monolingual text, qualifier' => [
				new PropertyValueSnak( $pid, new MonolingualTextValue( 'qqx', 'lorem ipsum' ) ),
				'monolingualtext',
				'qualifier',
				'"lorem ipsum"@qqx',
				'pq:P1',
			],
			'globe coordinate, reference' => [
				new PropertyValueSnak( $pid, $globeCoordinateValue ),
				'globe-coordinate',
				'reference',
				"wdv:{$globeCoordinateValue->getHash()}",
				'prov:wasDerivedFrom/prv:P1',
			],
			'quantity, qualifier' => [
				new PropertyValueSnak( $pid, $quantityValue ),
				'quantity',
				'qualifier',
				"wdv:{$quantityValue->getHash()}",
				'pqv:P1',
			],
			'time, reference' => [
				new PropertyValueSnak( $pid, $timeValue ),
				'time',
				'reference',
				"wdv:{$timeValue->getHash()}",
				'prov:wasDerivedFrom/prv:P1',
			],
		];
	}

	/**
	 * @dataProvider provideEndpointsAndResults
	 */
	public function testFindEntitiesWithSameStatementWithAdditionalEndpoints(
		array $primaryResult,
		array $additionalResults,
		array $expectedResult
	): void {
		$separators = [];
		$expectedFilter = "";
		$guid = 'Q1$8542690f-dfab-4846-944f-8382df730d2c';
		$statement = new Statement(
			new PropertyValueSnak( new NumericPropertyId( 'P1' ), new EntityIdValue( new ItemId( 'Q1' ) ) ),
			null,
			null,
			$guid
		);

		$primaryEndpoint = self::getDefaultConfig()->get( 'WBQualityConstraintsSparqlEndpoint' );

		$sparqlHelper = TestingAccessWrapper::newFromObject( $this->getSparqlHelper( new HashConfig( [
			'WBQualityConstraintsAdditionalSparqlEndpoints' => array_keys( $additionalResults ),
		] ) ) );

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
  MINUS { ?otherStatement wikibase:rank wikibase:DeprecatedRank. }
  $expectedFilter
}
LIMIT 10
EOF;

		$sparqlHelper->expects( $this->exactly( count( $additionalResults ) + 1 ) )
			->method( 'runQuery' )
			->willReturnCallback(
				function ( $query, $endpoint ) use ( $additionalResults, $primaryResult, $primaryEndpoint ) {
					return $this->selectResults(
						$endpoint === $primaryEndpoint ? $primaryResult : $additionalResults[ $endpoint ]
					);
				} )
			->with( $query, $this->callback( function ( $endpoint ) use ( $additionalResults, $primaryEndpoint ) {
				return $endpoint === $primaryEndpoint || array_key_exists( $endpoint, $additionalResults );
			} ) );

		$this->assertEquals(
			$expectedResult,
			$sparqlHelper->findEntitiesWithSameStatement( $statement, $separators )->getArray(),
		);
	}

	/**
	 * @dataProvider provideEndpointsAndResults
	 */
	public function testFindEntitiesWithSameQualifierOrReferenceWithAdditionalEndpoints(
		array $primaryResult,
		array $additionalResults,
		array $expectedResult
	): void {
		$pid = new NumericPropertyId( 'P1' );
		$snak = new PropertyValueSnak( $pid, new StringValue( 'foo' ) );
		$dataType = 'string';
		$contextType = 'qualifier';
		$sparqlValue = '"foo"';
		$sparqlPath = 'pq:P1';

		$dtLookup = $this->createMock( PropertyDataTypeLookup::class );
		$dtLookup->method( 'getDataTypeIdForProperty' )->willReturn( $dataType );

		$primaryEndpoint = self::getDefaultConfig()->get( 'WBQualityConstraintsSparqlEndpoint' );

		$sparqlHelper = TestingAccessWrapper::newFromObject( $this->getSparqlHelper( new HashConfig( [
			'WBQualityConstraintsAdditionalSparqlEndpoints' => array_keys( $additionalResults ),
		] ), $dtLookup ) );

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
		$sparqlHelper->expects( $this->exactly( count( $additionalResults ) + 1 ) )
			->method( 'runQuery' )
			->willReturnCallback(
				function ( $query, $endpoint ) use ( $additionalResults, $primaryResult, $primaryEndpoint ) {
					return $this->selectResults(
						$endpoint === $primaryEndpoint ? $primaryResult : $additionalResults[ $endpoint ]
					);
				} )
			->with( $query, $this->callback( function ( $endpoint ) use ( $additionalResults, $primaryEndpoint ) {
				return $endpoint === $primaryEndpoint || array_key_exists( $endpoint, $additionalResults );
			} ) );

		$this->assertEquals(
			$expectedResult,
			$sparqlHelper->findEntitiesWithSameQualifierOrReference(
				new ItemId( 'Q10' ),
				$snak,
				$contextType,
				false
			)->getArray()
		);
	}

	public static function provideEndpointsAndResults(): iterable {
		return [
			'default has matches, no additional endpoints' => [
				[
					[ 'otherEntity' => [ 'type' => 'uri', 'value' => 'http://www.wikidata.org/entity/Q100' ] ],
					[ 'otherEntity' => [ 'type' => 'uri', 'value' => 'http://www.wikidata.org/entity/Q101' ] ],
				],
				[],
				[ new ItemId( 'Q100' ), new ItemId( 'Q101' ) ],
			],
			'default has matches, additional endpoints have none' => [
				[
					[ 'otherEntity' => [ 'type' => 'uri', 'value' => 'http://www.wikidata.org/entity/Q100' ] ],
					[ 'otherEntity' => [ 'type' => 'uri', 'value' => 'http://www.wikidata.org/entity/Q101' ] ],
				],
				[
					'firstAdditionalEndpoint' => [],
					'secondAdditionalEndpoint' => [],
				],
				[ new ItemId( 'Q100' ), new ItemId( 'Q101' ) ],
			],
			'default and additional endpoints have matches' => [
				[
					[ 'otherEntity' => [ 'type' => 'uri', 'value' => 'http://www.wikidata.org/entity/Q100' ] ],
					[ 'otherEntity' => [ 'type' => 'uri', 'value' => 'http://www.wikidata.org/entity/Q101' ] ],
				],
				[
					'firstAdditionalEndpoint' => [
						[ 'otherEntity' => [ 'type' => 'uri', 'value' => 'http://www.wikidata.org/entity/Q102' ] ],
					],
					'secondAdditionalEndpoint' => [
						[ 'otherEntity' => [ 'type' => 'uri', 'value' => 'http://www.wikidata.org/entity/Q103' ] ],
						[ 'otherEntity' => [ 'type' => 'uri', 'value' => 'http://www.wikidata.org/entity/Q104' ] ],
					],
				],
				[
					new ItemId( 'Q100' ),
					new ItemId( 'Q101' ),
					new ItemId( 'Q102' ),
					new ItemId( 'Q103' ),
					new ItemId( 'Q104' ),
				],
			],
			'default has no matches, additional endpoints do' => [
				[],
				[
					'firstAdditionalEndpoint' => [
						[ 'otherEntity' => [ 'type' => 'uri', 'value' => 'http://www.wikidata.org/entity/Q102' ] ],
					],
					'secondAdditionalEndpoint' => [
						[ 'otherEntity' => [ 'type' => 'uri', 'value' => 'http://www.wikidata.org/entity/Q103' ] ],
						[ 'otherEntity' => [ 'type' => 'uri', 'value' => 'http://www.wikidata.org/entity/Q104' ] ],
					],
				],
				[
					new ItemId( 'Q102' ),
					new ItemId( 'Q103' ),
					new ItemId( 'Q104' ),
				],
			],
		];
	}

	public function testSerializeConstraintParameterException(): void {
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

	public function testDeserializeConstraintParameterException(): void {
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

	public function testMatchesRegularExpressionWithSparql(): void {
		$text = '"&quot;\'\\\\"<&lt;'; // "&quot;'\\"<&lt;
		$regex = '\\"\\\\"\\\\\\"'; // \"\\"\\\"
		$query = 'SELECT (REGEX("\\"&quot;\'\\\\\\\\\\"<&lt;", "^(?:\\\\\\"\\\\\\\\\\"\\\\\\\\\\\\\\")$") AS ?matches) {}';
		$sparqlHelper = TestingAccessWrapper::newFromObject( $this->getSparqlHelper() );

		$sparqlHelper->expects( $this->once() )
			->method( 'runQuery' )
			->with( $query )
			->willReturn( $this->selectResults( [ [ 'matches' => [ 'value' => 'false' ] ] ] ) );

		$result = $sparqlHelper->matchesRegularExpressionWithSparql( $text, $regex );

		$this->assertFalse( $result );
	}

	public function testMatchesRegularExpressionWithSparqlBadRegex(): void {
		$text = '';
		$regex = '(.{2,5)?';
		$query = 'SELECT (REGEX("", "^(?:(.{2,5)?)$") AS ?matches) {}';
		$sparqlHelper = TestingAccessWrapper::newFromObject( $this->getSparqlHelper() );
		$messageKey = 'wbqc-violation-message-parameter-regex';

		$sparqlHelper->expects( $this->once() )
			->method( 'runQuery' )
			->with( $query )
			->willReturn( $this->selectResults( [ [] ] ) );

		try {
			$sparqlHelper->matchesRegularExpressionWithSparql( $text, $regex );
			$this->fail(
				"matchesRegularExpressionWithSparql should have thrown a ConstraintParameterException with message "
			. "⧼{$messageKey}⧽."
			);
		} catch ( ConstraintParameterException $exception ) {
			$checkResult = new CheckResult(
				$this->createMock( ContextCursor::class ),
				$this->createMock( Constraint::class ),
				CheckResult::STATUS_VIOLATION,
				$exception->getViolationMessage()
			);
			$this->assertViolation( $checkResult, $messageKey );
		}
	}

	/**
	 * @dataProvider provideTimeoutMessages
	 */
	public function testIsTimeout( string $content, bool $expected ): void {
		$sparqlHelper = $this->getSparqlHelper();

		$actual = $sparqlHelper->isTimeout( $content );

		$this->assertSame( $expected, $actual );
	}

	public function testIsTimeoutRegex(): void {
		$sparqlHelper = $this->getSparqlHelper(
			new HashConfig( [
				'WBQualityConstraintsSparqlTimeoutExceptionClasses' => [
					'(?!this may look like a regular expression)',
					'/but should not be interpreted as one/',
					'(x+x+)+y',
				],
			] )
		);
		$content = '(x+x+)+y';

		$actual = $sparqlHelper->isTimeout( $content );

		$this->assertTrue( $actual );
	}

	public static function provideTimeoutMessages(): iterable {
		return [
			'empty' => [
				'',
				false,
			],
			'syntax error' => [
				'org.openrdf.query.MalformedQueryException: ' .
					'Encountered "<EOF>" at line 1, column 6.',
				false,
			],
			'QueryTimeoutException' => [
				'java.util.concurrent.ExecutionException: ' .
					'java.util.concurrent.ExecutionException: ' .
					'org.openrdf.query.QueryInterruptedException: ' .
					'java.lang.RuntimeException: ' .
					'java.util.concurrent.ExecutionException: ' .
					'com.bigdata.bop.engine.QueryTimeoutException: ' .
					'Query deadline is expired.',
				true,
			],
			'TimeoutException' => [
				"java.util.concurrent.TimeoutException\n" .
					"\tat java.util.concurrent.FutureTask.get(FutureTask.java:205)\n" .
					"\tat com.bigdata.rdf.sail.webapp.BigdataServlet.submitApiTask(BigdataServlet.java:289)\n" .
					"\tat com.bigdata.rdf.sail.webapp.QueryServlet.doSparqlQuery(QueryServlet.java:653)\n",
				true,
			],
		];
	}

	/**
	 * @dataProvider provideCacheHeaders
	 */
	public function testGetCacheMaxAge( array $responseHeaders, $expected ) {
		$sparqlHelper = $this->getSparqlHelper();

		$actual = $sparqlHelper->getCacheMaxAge( $responseHeaders );

		$this->assertSame( $expected, $actual );
	}

	public static function provideCacheHeaders(): iterable {
		return [
			'WDQS hit' => [
				[ 'x-cache-status' => [ 'hit-front' ], 'cache-control' => [ 'public, max-age=300' ] ],
				300,
			],
			'WDQS miss' => [
				[ 'x-cache-status' => [ 'miss' ], 'cache-control' => [ 'public, max-age=300' ] ],
				false,
			],
			'generic hit' => [
				[ 'x-cache-status' => [ 'hit' ] ],
				true,
			],
		];
	}

	public function testrunQuerySetsLock_if429HeadersAndRetryAfterSet(): void {
		$lock = $this->createMock( ExpiryLock::class );
		$retryAfter = 1000;

		$requestMock = $this->createMock( \MWHttpRequest::class );
		$requestMock->method( 'getResponseHeader' )
			->with( $this->callback( function ( $headerName ) {
				return strtolower( $headerName ) === 'retry-after';
			} ) )
			->willReturn( (string)$retryAfter );
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
			->with( SparqlHelper::EXPIRY_LOCK_ID,
				$this->callback( function ( $actualTimestamp ) use ( $expectedTimestamp ) {
					$actualUnixTime = $actualTimestamp->format( 'U' );
					return $actualUnixTime == $expectedTimestamp;
				} )
			);

		$sparqlHelper = TestingAccessWrapper::newFromObject( new SparqlHelper(
			self::getDefaultConfig(),
			$this->getRdfVocabulary(),
			$this->createMock( EntityIdParser::class ),
			$this->createMock( PropertyDataTypeLookup::class ),
			WANObjectCache::newEmpty(),
			$this->createMock( ViolationMessageSerializer::class ),
			$this->createMock( ViolationMessageDeserializer::class ),
			$this->createMock( IBufferingStatsdDataFactory::class ),
			$lock,
			$loggingHelper,
			'',
			$requestFactoryMock
		) );

		$this->expectException( TooManySparqlRequestsException::class );
		$sparqlHelper->runQuery( 'fake query', self::getDefaultConfig()->get( 'WBQualityConstraintsSparqlEndpoint' ) );
	}

	public function testRunQuerySetsLock_if429HeadersButRetryAfterMissing(): void {
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

		$config = self::getDefaultConfig();
		$defaultWait = 154;
		$config->set( 'WBQualityConstraintsSparqlThrottlingFallbackDuration', $defaultWait );

		$expectedTimestamp = $defaultWait + $fakeNow;

		$loggingHelper = $this->getLoggingHelperExpectingRetryAfterMissing();

		$sparqlHelper = TestingAccessWrapper::newFromObject( new SparqlHelper(
			$config,
			$this->getRdfVocabulary(),
			$this->createMock( EntityIdParser::class ),
			$this->createMock( PropertyDataTypeLookup::class ),
			WANObjectCache::newEmpty(),
			$this->createMock( ViolationMessageSerializer::class ),
			$this->createMock( ViolationMessageDeserializer::class ),
			$this->createMock( IBufferingStatsdDataFactory::class ),
			$this->getMockLock( SparqlHelper::EXPIRY_LOCK_ID, $expectedTimestamp ),
			$loggingHelper,
			'',
			$requestFactoryMock
		) );

		$this->expectException( TooManySparqlRequestsException::class );
		$sparqlHelper->runQuery( 'fake query', self::getDefaultConfig()->get( 'WBQualityConstraintsSparqlEndpoint' ) );
	}

	public function testRunQueryDoesNotQuery_ifLockIsLocked(): void {
		$lock = $this->createMock( ExpiryLock::class );
		$lock->method( 'isLocked' )
			->willReturn( true );

		$requestMock = $this->createMock( \MWHttpRequest::class );
		$requestMock->expects( $this->never() )
			->method( 'execute' );

		$requestFactoryMock = $this->createMock( HttpRequestFactory::class );
		$requestFactoryMock->method( 'create' )
			->willReturn( $requestMock );

		$sparqlHelper = TestingAccessWrapper::newFromObject( new SparqlHelper(
			self::getDefaultConfig(),
			$this->getRdfVocabulary(),
			$this->createMock( EntityIdParser::class ),
			$this->createMock( PropertyDataTypeLookup::class ),
			WANObjectCache::newEmpty(),
			$this->createMock( ViolationMessageSerializer::class ),
			$this->createMock( ViolationMessageDeserializer::class ),
			$this->createMock( IBufferingStatsdDataFactory::class ),
			$lock,
			$this->createMock( LoggingHelper::class ),
			'',
			$requestFactoryMock
		) );

		$this->expectException( TooManySparqlRequestsException::class );
		$sparqlHelper->runQuery( 'foo baz', self::getDefaultConfig()->get( 'WBQualityConstraintsSparqlEndpoint' ) );
	}

	public function testRunQuerySetsLock_if429HeadersPresentAndRetryAfterMalformed(): void {

		$requestFactoryMock = $this->getMock429RequestFactory( [ 'Retry-After' => 'malformedthing' ] );

		$config = self::getDefaultConfig();
		$defaultWait = 154;
		$config->set( 'WBQualityConstraintsSparqlThrottlingFallbackDuration', $defaultWait );
		$fakeNow = 5000;
		ConvertibleTimestamp::setFakeTime( $fakeNow );

		$lock = $this->getMockLock( SparqlHelper::EXPIRY_LOCK_ID, $defaultWait + $fakeNow );

		$loggingHelper = $this->getLoggingHelperExpectingRetryAfterMissing();

		$sparqlHelper = TestingAccessWrapper::newFromObject( new SparqlHelper(
			$config,
			$this->getRdfVocabulary(),
			$this->createMock( EntityIdParser::class ),
			$this->createMock( PropertyDataTypeLookup::class ),
			WANObjectCache::newEmpty(),
			$this->createMock( ViolationMessageSerializer::class ),
			$this->createMock( ViolationMessageDeserializer::class ),
			$this->createMock( IBufferingStatsdDataFactory::class ),
			$lock,
			$loggingHelper,
			'',
			$requestFactoryMock
		) );

		$this->expectException( TooManySparqlRequestsException::class );
		$sparqlHelper->runQuery( 'foo baz', self::getDefaultConfig()->get( 'WBQualityConstraintsSparqlEndpoint' ) );
	}

	private function getMockLock( string $expectedLockId, int $expectedLockExpiryTimestamp ) {
		$lock = $this->createMock( ExpiryLock::class );
		$lock->expects( $this->once() )
			->method( 'lock' )
			->with(
				$expectedLockId,
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

	private function getLoggingHelperExpectingRetryAfterPresent( ConvertibleTimestamp $retryTime ) {
		$helper = $this->createMock( LoggingHelper::class );
		$helper->expects( $this->once() )
			->method( 'logSparqlHelperTooManyRequestsRetryAfterPresent' )
			->with( $retryTime, $this->anything() );
		return $helper;
	}

	private function getLoggingHelperExpectingRetryAfterMissing() {
		$helper = $this->createMock( LoggingHelper::class );
		$helper->expects( $this->once() )
			->method( 'logSparqlHelperTooManyRequestsRetryAfterInvalid' );
		return $helper;
	}

	public function testGivenNeedsPrefixesFlagTrue_RunQueryIncludesPrefixesInResultingQuery(): void {
		$lock = $this->createMock( ExpiryLock::class );
		$lock->method( 'isLocked' )
			->willReturn( false );

		$request = $this->createMock( \MWHttpRequest::class );
		$request->method( 'getStatus' )
			->willReturn( 200 );
		$request->method( 'getResponseHeaders' )
			->willReturn( [] );
		$request->method( 'execute' )
			->willReturn( Status::newGood() );
		$request->method( 'getContent' )->willReturn( '{}' );

		$requestFactory = $this->createMock( HttpRequestFactory::class );
		$requestFactory->expects( $this->atLeastOnce() )
			->method( 'create' )
			->with(
				$this->callback( function ( $url ) {
					$query = substr( $url, strpos( $url, '?query=' ) );
					$query = substr( $query, strlen( '?query=' ) );
					$query = substr( $query, 0, strpos( $query, '&format=' ) );

					$expectedPrefix = <<<END
#wbqc
PREFIX wd: <http://wiki/entity/>
PREFIX wds: <http://wiki/entity/statement/>
PREFIX wdv: <http://wiki/value/>
PREFIX wdt: <http://wiki/prop/direct/>
PREFIX p: <http://wiki/prop/>
PREFIX ps: <http://wiki/prop/statement/>
PREFIX pq: <http://wiki/prop/qualifier/>
PREFIX pqv: <http://wiki/prop/qualifier/value/>
PREFIX pr: <http://wiki/prop/reference/>
PREFIX prv: <http://wiki/prop/reference/value/>
PREFIX wikibase: <http://wikiba.se/ontology#>
END;
					$expectedPrefix = rawurlencode( $expectedPrefix );
					return substr( $query, 0, strlen( $expectedPrefix ) ) === $expectedPrefix;
				} ),
				$this->anything()
			)
			->willReturn( $request );

		$config = self::getDefaultConfig();
		$config->set( 'WBQualityConstraintsSparqlHasWikibaseSupport', false );

		$rdfVocabulary = new RdfVocabulary(
			[ 'wd' => 'http://wiki/entity/' ],
			[ 'wd' => 'http://data.wiki/' ],
			new EntitySourceDefinitions( [
				new DataBaseEntitySource(
					'wd',
					false,
					[
						'item' => [ 'namespaceId' => 100, 'slot' => SlotRecord::MAIN ],
						'property' => [ 'namespaceId' => 200, 'slot' => SlotRecord::MAIN ],
					],
					'http://wiki/entity/',
					'wd',
					'',
					'd'
				),
			], new SubEntityTypesMapper( [] ) ),
			[ 'wd' => 'wd' ],
			[ 'wd' => '' ]
		);

		$sparqlHelper = TestingAccessWrapper::newFromObject( new SparqlHelper(
			self::getDefaultConfig(),
			$rdfVocabulary,
			$this->createMock( EntityIdParser::class ),
			$this->createMock( PropertyDataTypeLookup::class ),
			WANObjectCache::newEmpty(),
			$this->createMock( ViolationMessageSerializer::class ),
			$this->createMock( ViolationMessageDeserializer::class ),
			$this->createMock( IBufferingStatsdDataFactory::class ),
			$lock,
			$this->createMock( LoggingHelper::class ),
			'',
			$requestFactory
		) );

		$query = <<<END
SELECT ?item ?itemLabel
WHERE
{
  ?item wdt:P31 wd:Q2934.
  SERVICE wikibase:label { bd:serviceParam wikibase:language "[AUTO_LANGUAGE],en". }
}
END;

		$sparqlHelper->runQuery(
			$query,
			self::$defaultConfig->get( 'WBQualityConstraintsSparqlEndpoint' ),
			true
		);
	}

	public function testRunQueryTracksError_http(): void {
		$request = $this->createMock( \MWHttpRequest::class );
		$request->method( 'getStatus' )
			->willReturn( 500 );
		$request->method( 'getResponseHeaders' )
			->willReturn( [] );
		$request->method( 'execute' )
			->willReturn( Status::newFatal( 'http-bad-status' ) );
		$request->method( 'getContent' )
			->willReturn( '' );

		$requestFactory = $this->createMock( HttpRequestFactory::class );
		$requestFactory->method( 'create' )
			->willReturn( $request );

		$dataFactory = new BufferingStatsdDataFactory( '' );

		$sparqlHelper = TestingAccessWrapper::newFromObject( new SparqlHelper(
			self::getDefaultConfig(),
			$this->getRdfVocabulary(),
			$this->createMock( EntityIdParser::class ),
			$this->createMock( PropertyDataTypeLookup::class ),
			WANObjectCache::newEmpty(),
			$this->createMock( ViolationMessageSerializer::class ),
			$this->createMock( ViolationMessageDeserializer::class ),
			$dataFactory,
			new ExpiryLock( new HashBagOStuff() ),
			$this->createMock( LoggingHelper::class ),
			'',
			$requestFactory
		) );

		try {
			$sparqlHelper->runQuery( 'query', self::getDefaultConfig()->get( 'WBQualityConstraintsSparqlEndpoint' ) );
			$this->fail( 'should have thrown' );
		} catch ( SparqlHelperException $e ) {
			$statsdData = $dataFactory->getData();
			// three data events: timing (ignored here), HTTP error, generic error
			$this->assertCount( 3, $statsdData );
			$this->assertSame(
				'wikibase.quality.constraints.sparql.error.http.500',
				$statsdData[1]->getKey()
			);
			$this->assertSame(
				'wikibase.quality.constraints.sparql.error',
				$statsdData[2]->getKey()
			);
		}
	}

	public function testRunQueryTracksError_json(): void {
		$request = $this->createMock( \MWHttpRequest::class );
		$request->method( 'getStatus' )
			->willReturn( 200 );
		$request->method( 'getResponseHeaders' )
			->willReturn( [] );
		$request->method( 'execute' )
			->willReturn( Status::newGood() );
		$request->method( 'getContent' )
			->willReturn( '{"truncated json":' );

		$requestFactory = $this->createMock( HttpRequestFactory::class );
		$requestFactory->method( 'create' )
			->willReturn( $request );

		$dataFactory = new BufferingStatsdDataFactory( '' );

		$sparqlHelper = TestingAccessWrapper::newFromObject( new SparqlHelper(
			self::getDefaultConfig(),
			$this->getRdfVocabulary(),
			$this->createMock( EntityIdParser::class ),
			$this->createMock( PropertyDataTypeLookup::class ),
			WANObjectCache::newEmpty(),
			$this->createMock( ViolationMessageSerializer::class ),
			$this->createMock( ViolationMessageDeserializer::class ),
			$dataFactory,
			new ExpiryLock( new HashBagOStuff() ),
			$this->createMock( LoggingHelper::class ),
			'',
			$requestFactory
		) );

		try {
			$sparqlHelper->runQuery( 'query', self::getDefaultConfig()->get( 'WBQualityConstraintsSparqlEndpoint' ) );
			$this->fail( 'should have thrown' );
		} catch ( SparqlHelperException $e ) {
			$statsdData = $dataFactory->getData();
			// three data events: timing (ignored here), JSON error, generic error
			$this->assertCount( 3, $statsdData );
			$this->assertSame(
				'wikibase.quality.constraints.sparql.error.json.json_error_syntax',
				$statsdData[1]->getKey()
			);
			$this->assertSame(
				'wikibase.quality.constraints.sparql.error',
				$statsdData[2]->getKey()
			);
		}
	}

}
