<?php

namespace WikibaseQuality\ConstraintReport\Test\Helper;

use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\ItemIdParser;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\Rdf\RdfVocabulary;
use WikibaseQuality\ConstraintReport\Constraint;
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
						  new ItemIdParser()
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
						  new ItemIdParser()
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

	public function testMatchesRegularExpression() {
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

		$result = $sparqlHelper->matchesRegularExpression( $text, $regex );

		$this->assertFalse( $result );
	}

	public function testMatchesRegularExpressionBadRegex() {
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
			call_user_func_array( [ $sparqlHelper, 'matchesRegularExpression' ], [ $text, $regex ] );
			$this->assertTrue( false,
				"matchesRegularExpression should have thrown a ConstraintParameterException with message ⧼$messageKey⧽." );
		} catch ( ConstraintParameterException $exception ) {
			$checkResult = new CheckResult(
				new ItemId( 'Q1' ),
				new Statement( new PropertyNoValueSnak( new PropertyId( 'P1' ) ) ),
				$this->getMockBuilder( Constraint::class )->disableOriginalConstructor()->getMock(),
				[],
				CheckResult::STATUS_VIOLATION,
				$exception->getMessage()
			);
			$this->assertViolation( $checkResult, $messageKey );
		}
	}

}
