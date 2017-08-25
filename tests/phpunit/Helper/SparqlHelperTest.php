<?php

namespace WikibaseQuality\ConstraintReport\Test\Helper;

use HashBagOStuff;
use HashConfig;
use WANObjectCache;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\ItemIdParser;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\Rdf\RdfVocabulary;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\Context;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterException;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\SparqlHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikibaseQuality\ConstraintReport\Tests\DefaultConfig;
use WikibaseQuality\ConstraintReport\Tests\ResultAssertions;

/**
 * @covers \WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\SparqlHelper
 *
 * @group WikibaseQualityConstraints
 *
 * @author Lucas Werkmeister
 * @license GNU GPL v2+
 */
class SparqlHelperTest extends \PHPUnit_Framework_TestCase {

	use DefaultConfig, ResultAssertions;

	public function testHasType() {
		$sparqlHelper = $this->getMockBuilder( SparqlHelper::class )
					  ->setConstructorArgs( [
						  $this->getDefaultConfig(),
						  new RdfVocabulary(
							  'http://www.wikidata.org/entity/',
							  'http://www.wikidata.org/wiki/Special:EntityData/'
						  ),
						  new ItemIdParser(),
						  WANObjectCache::newEmpty()
					  ] )
					  ->setMethods( [ 'runQuery' ] )
					  ->getMock();

		$query = <<<EOF
ASK {
  BIND(wd:Q1 AS ?item)
  VALUES ?class { wd:Q100 wd:Q101 }
  ?item wdt:P31/wdt:P279* ?class. hint:Prior hint:gearing "forward".
}
EOF;

		$sparqlHelper->expects( $this->exactly( 1 ) )
			->method( 'runQuery' )
			->willReturn( [ 'boolean' => true ] )
			->withConsecutive( [ $this->equalTo( $query ) ] );

		$this->assertTrue( $sparqlHelper->hasType( 'Q1', [ 'Q100', 'Q101' ], true ) );
	}

	public function testFindEntitiesWithSameStatement() {
		$guid = 'Q1$8542690f-dfab-4846-944f-8382df730d2c';
		$statement = new Statement(
			new PropertyValueSnak( new PropertyId( 'P1' ), new EntityIdValue( new ItemId( 'Q1' ) ) ),
			null,
			null,
			$guid
		);

		$sparqlHelper = $this->getMockBuilder( SparqlHelper::class )
					  ->setConstructorArgs( [
						  $this->getDefaultConfig(),
						  new RdfVocabulary(
							  'http://www.wikidata.org/entity/',
							  'http://www.wikidata.org/wiki/Special:EntityData/'
						  ),
						  new ItemIdParser(),
						  WANObjectCache::newEmpty()
					  ] )
					  ->setMethods( [ 'runQuery' ] )
					  ->getMock();

		$query = <<<EOF
SELECT ?otherEntity WHERE {
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
			->willReturn( [ 'head' => [ 'vars' => [ 'otherEntity' ] ], 'results' => [ 'bindings' => [
				[ 'otherEntity' => [ 'type' => 'uri', 'value' => 'http://www.wikidata.org/entity/Q100' ] ],
				[ 'otherEntity' => [ 'type' => 'uri', 'value' => 'http://www.wikidata.org/entity/Q101' ] ],
			] ] ] )
			->withConsecutive( [ $this->equalTo( $query ) ] );

		$this->assertEquals(
			$sparqlHelper->findEntitiesWithSameStatement( $statement, true ),
			[ new ItemId( 'Q100' ), new ItemId( 'Q101' ) ]
		);
	}

	public function testMatchesRegularExpressionWithSparql() {
		$text = '"&quot;\'\\\\"<&lt;'; // "&quot;'\\"<&lt;
		$regex = '\\"\\\\"\\\\\\"'; // \"\\"\\\"
		$query = 'SELECT (REGEX("\\"&quot;\'\\\\\\\\\\"<&lt;", "^\\\\\\"\\\\\\\\\\"\\\\\\\\\\\\\\"$") AS ?matches) {}';
		$sparqlHelper = $this->getMockBuilder( SparqlHelper::class )
					  ->disableOriginalConstructor()
					  ->setMethods( [ 'runQuery' ] )
					  ->getMock();

		$sparqlHelper->expects( $this->once() )
			->method( 'runQuery' )
			->with( $this->equalTo( $query ) )
			->willReturn( [ 'results' => [ 'bindings' => [ [ 'matches' => [ 'value' => 'false' ] ] ] ] ] );

		$result = $sparqlHelper->matchesRegularExpressionWithSparql( $text, $regex );

		$this->assertFalse( $result );
	}

	public function testMatchesRegularExpressionWithSparqlBadRegex() {
		$text = '';
		$regex = '(.{2,5)?';
		$query = 'SELECT (REGEX("", "^(.{2,5)?$") AS ?matches) {}';
		$sparqlHelper = $this->getMockBuilder( SparqlHelper::class )
					  ->disableOriginalConstructor()
					  ->setMethods( [ 'runQuery' ] )
					  ->getMock();
		$messageKey = 'wbqc-violation-message-parameter-regex';

		$sparqlHelper->expects( $this->once() )
			->method( 'runQuery' )
			->with( $this->equalTo( $query ) )
			->willReturn( [ 'results' => [ 'bindings' => [ [] ] ] ] );

		try {
			call_user_func_array( [ $sparqlHelper, 'matchesRegularExpressionWithSparql' ], [ $text, $regex ] );
			$this->assertTrue( false,
				"matchesRegularExpressionWithSparql should have thrown a ConstraintParameterException with message ⧼$messageKey⧽." );
		} catch ( ConstraintParameterException $exception ) {
			$checkResult = new CheckResult(
				$this->getMock( Context::class ),
				$this->getMockBuilder( Constraint::class )->disableOriginalConstructor()->getMock(),
				[],
				CheckResult::STATUS_VIOLATION,
				$exception->getMessage()
			);
			$this->assertViolation( $checkResult, $messageKey );
		}
	}

	/**
	 * @dataProvider haveCache
	 */
	public function testMatchesRegularExpressionCache( $haveCache ) {
		$text = '/.';
		$regex = '.?';
		$query = 'SELECT (REGEX("/.", "^.?$") AS ?matches) {}';
		$cache = $haveCache ?
			new WANObjectCache( [ 'cache' => new HashBagOStuff() ] ) :
			WANObjectCache::newEmpty();
		$sparqlHelper = $this->getMockBuilder( SparqlHelper::class )
			->setConstructorArgs( [
				$this->getDefaultConfig(),
				new RdfVocabulary(
					'http://www.wikidata.org/entity/',
					'http://www.wikidata.org/wiki/Special:EntityData/'
				),
				new ItemIdParser(),
				$cache
			] )
			->setMethods( [ 'runQuery' ] )
			->getMock();

		$sparqlHelper->expects( $haveCache ? $this->once() : $this->exactly( 2 ) )
			->method( 'runQuery' )
			->with( $this->equalTo( $query ) )
			->willReturn( [ 'results' => [ 'bindings' => [ [ 'matches' => [ 'value' => 'false' ] ] ] ] ] );

		$result1 = $sparqlHelper->matchesRegularExpression( $text, $regex );
		$cache->clearProcessCache();
		$result2 = $sparqlHelper->matchesRegularExpression( $text, $regex );

		$this->assertFalse( $result1 );
		$this->assertFalse( $result2 );
	}

	public function haveCache() {
		return [
			'with cache' => [ true ],
			'without cache' => [ false ],
		];
	}

	/**
	 * @dataProvider isTimeoutProvider
	 */
	public function testIsTimeout( $content, $expected ) {
		$sparqlHelper = new SparqlHelper(
			$this->getDefaultConfig(),
			new RdfVocabulary(
				'http://www.wikidata.org/entity/',
				'http://www.wikidata.org/wiki/Special:EntityData/'
			),
			new ItemIdParser(),
			WANObjectCache::newEmpty()
		);

		$actual = $sparqlHelper->isTimeout( $content );

		$this->assertSame( $expected, $actual );
	}

	public function testIsTimeoutRegex() {
		$sparqlHelper = new SparqlHelper(
			new HashConfig( [
				'WBQualityConstraintsSparqlTimeoutExceptionClasses' => [
					'(?!this may look like a regular expression)',
					'/but should not be interpreted as one/',
					'(x+x+)+y',
				]
			] ),
			new RdfVocabulary(
				'http://www.wikidata.org/entity/',
				'http://www.wikidata.org/wiki/Special:EntityData/'
			),
			new ItemIdParser(),
			WANObjectCache::newEmpty()
		);
		$content = '(x+x+)+y';

		$actual = $sparqlHelper->isTimeout( $content );

		$this->assertTrue( $actual );
	}

	public function isTimeoutProvider() {
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

}
