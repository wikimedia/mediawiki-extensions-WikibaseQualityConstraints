<?php

namespace WikibaseQuality\ConstraintReport\Test\FormatChecker;

use HashConfig;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use DataValues\StringValue;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\FormatChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\SparqlHelper;
use WikibaseQuality\ConstraintReport\Tests\ConstraintParameters;
use WikibaseQuality\ConstraintReport\Tests\ResultAssertions;

/**
 * @covers \WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\FormatChecker
 *
 * @group WikibaseQualityConstraints
 *
 * @uses   \WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 * @uses   \WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class FormatCheckerTest extends \MediaWikiTestCase {

	use ConstraintParameters, ResultAssertions;

	/**
	 * @var FormatChecker
	 */
	private $formatChecker;

	protected function setUp() {
		parent::setUp();
		$sparqlHelper = $this->getMockBuilder( SparqlHelper::class )
					  ->disableOriginalConstructor()
					  ->setMethods( [ 'matchesRegularExpression' ] )
					  ->getMock();
		$sparqlHelper->method( 'matchesRegularExpression' )
			->will( $this->returnCallback(
				function( $text, $pattern ) {
					return preg_match( '/^' . str_replace( '/', '\/', $pattern ) . '$/', $text );
				}
			) );
		$this->formatChecker = new FormatChecker(
			$this->getConstraintParameterParser(),
			$this->getConstraintParameterRenderer(),
			$this->getDefaultConfig(),
			$sparqlHelper
		);
	}

	public function testFormatConstraintImdb() {
		$pattern = '(tt|nm|ch|co|ev)\d{7}';

		$value1 = new StringValue( 'nm0001398' );
		$value2 = new StringValue( 'tt1234567' );
		$value3 = new StringValue( 'ch7654321' );
		$value4 = new StringValue( 'ev7777777' );
		$value5 = new StringValue( 'nm88888888' );
		$value6 = new StringValue( 'nmabcdefg' );
		$value7 = new StringValue( 'ab0001398' );
		$value8 = new StringValue( '123456789' );
		$value9 = new StringValue( 'nm000139' );
		$value10 = new StringValue( 'nmnm0001398' );

		$statement1 = new Statement( new PropertyValueSnak( new PropertyId( 'P345' ), $value1 ) );
		$statement2 = new Statement( new PropertyValueSnak( new PropertyId( 'P345' ), $value2 ) );
		$statement3 = new Statement( new PropertyValueSnak( new PropertyId( 'P345' ), $value3 ) );
		$statement4 = new Statement( new PropertyValueSnak( new PropertyId( 'P345' ), $value4 ) );
		$statement5 = new Statement( new PropertyValueSnak( new PropertyId( 'P345' ), $value5 ) );
		$statement6 = new Statement( new PropertyValueSnak( new PropertyId( 'P345' ), $value6 ) );
		$statement7 = new Statement( new PropertyValueSnak( new PropertyId( 'P345' ), $value7 ) );
		$statement8 = new Statement( new PropertyValueSnak( new PropertyId( 'P345' ), $value8 ) );
		$statement9 = new Statement( new PropertyValueSnak( new PropertyId( 'P345' ), $value9 ) );
		$statement10 = new Statement( new PropertyValueSnak( new PropertyId( 'P345' ), $value10 ) );

		$result = $this->formatChecker->checkConstraint(
			$statement1,
			$this->getConstraintMock( $this->formatParameter( $pattern ) ),
			$this->getEntity()
		);
		$this->assertCompliance( $result );

		$result = $this->formatChecker->checkConstraint(
			$statement2,
			$this->getConstraintMock( $this->formatParameter( $pattern ) ),
			$this->getEntity()
		);
		$this->assertCompliance( $result );

		$result = $this->formatChecker->checkConstraint(
			$statement3,
			$this->getConstraintMock( $this->formatParameter( $pattern ) ),
			$this->getEntity()
		);
		$this->assertCompliance( $result );

		$result = $this->formatChecker->checkConstraint(
			$statement4,
			$this->getConstraintMock( $this->formatParameter( $pattern ) ),
			$this->getEntity()
		);
		$this->assertCompliance( $result );

		$result = $this->formatChecker->checkConstraint(
			$statement5,
			$this->getConstraintMock( $this->formatParameter( $pattern ) ),
			$this->getEntity()
		);
		$this->assertViolation( $result );

		$result = $this->formatChecker->checkConstraint(
			$statement6,
			$this->getConstraintMock( $this->formatParameter( $pattern ) ),
			$this->getEntity()
		);
		$this->assertViolation( $result );

		$result = $this->formatChecker->checkConstraint(
			$statement7,
			$this->getConstraintMock( $this->formatParameter( $pattern ) ),
			$this->getEntity()
		);
		$this->assertViolation( $result );

		$result = $this->formatChecker->checkConstraint(
			$statement8,
			$this->getConstraintMock( $this->formatParameter( $pattern ) ),
			$this->getEntity()
		);
		$this->assertViolation( $result );

		$result = $this->formatChecker->checkConstraint(
			$statement9,
			$this->getConstraintMock( $this->formatParameter( $pattern ) ),
			$this->getEntity()
		);
		$this->assertViolation( $result );

		$result = $this->formatChecker->checkConstraint(
			$statement10,
			$this->getConstraintMock( $this->formatParameter( $pattern ) ),
			$this->getEntity()
		);
		$this->assertViolation( $result );
	}

	public function testFormatConstraintTaxonName() {
		$pattern = '(|somevalue|novalue|.*virus.*|.*viroid.*|.*phage.*|((×)?[A-Z]([a-z]+-)?[a-z]+('
			. '( [A-Z]?[a-z]+)|'
			. '( ([a-z]+-)?([a-z]+-)?[a-z]+)|'
			. '( ×([a-z]+-)?([a-z]+-)?([a-z]+-)?([a-z]+-)?[a-z]+)|'
			. '( \([A-Z][a-z]+\) [a-z]+)|'
			. "( (‘|')[A-Z][a-z]+(('|’)s)?( de)?( [A-Z][a-z]+(-([A-Z])?[a-z]+)*)*('|’)*)|"
			. '( ×| Group| (sub)?sp\.| (con)?(sub)?(notho)?var\.| (sub)?ser\.| (sub)?sect\.|'
			. ' subg\.| (sub)?f\.))*))';

		$value1 = new StringValue( 'Populus × canescens' );
		$value2 = new StringValue( 'Encephalartos friderici-guilielmi' );
		$value3 = new StringValue( 'Eruca vesicaria subsp. sativa' );
		$value4 = new StringValue( 'Euxoa (Chorizagrotis) lidia' );
		$value5 = new StringValue( 'Hepatitis A' );
		$value6 = new StringValue( 'Symphysodon (Cichlidae)' );
		$value7 = new StringValue( 'eukaryota' );
		$value8 = new StringValue( 'Plantago maritima agg.' );
		$value9 = new StringValue( 'Deinococcus-Thermus' );
		$value10 = new StringValue( 'Escherichia coli O157:H7' );

		$statement1 = new Statement( new PropertyValueSnak( new PropertyId( 'P345' ), $value1 ) );
		$statement2 = new Statement( new PropertyValueSnak( new PropertyId( 'P345' ), $value2 ) );
		$statement3 = new Statement( new PropertyValueSnak( new PropertyId( 'P345' ), $value3 ) );
		$statement4 = new Statement( new PropertyValueSnak( new PropertyId( 'P345' ), $value4 ) );
		$statement5 = new Statement( new PropertyValueSnak( new PropertyId( 'P345' ), $value5 ) );
		$statement6 = new Statement( new PropertyValueSnak( new PropertyId( 'P345' ), $value6 ) );
		$statement7 = new Statement( new PropertyValueSnak( new PropertyId( 'P345' ), $value7 ) );
		$statement8 = new Statement( new PropertyValueSnak( new PropertyId( 'P345' ), $value8 ) );
		$statement9 = new Statement( new PropertyValueSnak( new PropertyId( 'P345' ), $value9 ) );
		$statement10 = new Statement( new PropertyValueSnak( new PropertyId( 'P345' ), $value10 ) );

		$result = $this->formatChecker->checkConstraint(
			$statement1,
			$this->getConstraintMock( $this->formatParameter( $pattern ) ),
			$this->getEntity()
		);
		$this->assertCompliance( $result );

		$result = $this->formatChecker->checkConstraint(
			$statement2,
			$this->getConstraintMock( $this->formatParameter( $pattern ) ),
			$this->getEntity()
		);
		$this->assertCompliance( $result );

		$result = $this->formatChecker->checkConstraint(
			$statement3,
			$this->getConstraintMock( $this->formatParameter( $pattern ) ),
			$this->getEntity()
		);
		$this->assertCompliance( $result );

		$result = $this->formatChecker->checkConstraint(
			$statement4,
			$this->getConstraintMock( $this->formatParameter( $pattern ) ),
			$this->getEntity()
		);
		$this->assertCompliance( $result );

		$result = $this->formatChecker->checkConstraint(
			$statement5,
			$this->getConstraintMock( $this->formatParameter( $pattern ) ),
			$this->getEntity()
		);
		$this->assertViolation( $result );

		$result = $this->formatChecker->checkConstraint(
			$statement6,
			$this->getConstraintMock( $this->formatParameter( $pattern ) ),
			$this->getEntity()
		);
		$this->assertViolation( $result );

		$result = $this->formatChecker->checkConstraint(
			$statement7,
			$this->getConstraintMock( $this->formatParameter( $pattern ) ),
			$this->getEntity()
		);
		$this->assertViolation( $result );

		$result = $this->formatChecker->checkConstraint(
			$statement8,
			$this->getConstraintMock( $this->formatParameter( $pattern ) ),
			$this->getEntity()
		);
		$this->assertViolation( $result );

		$result = $this->formatChecker->checkConstraint(
			$statement9,
			$this->getConstraintMock( $this->formatParameter( $pattern ) ),
			$this->getEntity()
		);
		$this->assertViolation( $result );

		$result = $this->formatChecker->checkConstraint(
			$statement10,
			$this->getConstraintMock( $this->formatParameter( $pattern ) ),
			$this->getEntity()
		);
		$this->assertViolation( $result );
	}

	public function testFormatConstraintNoStringValue() {
		$pattern = '(|somevalue|novalue|.*virus.*|.*viroid.*|.*phage.*|((×)?[A-Z]([a-z]+-)?[a-z]+('
			. '( [A-Z]?[a-z]+)|'
			. '( ([a-z]+-)?([a-z]+-)?[a-z]+)|'
			. '( ×([a-z]+-)?([a-z]+-)?([a-z]+-)?([a-z]+-)?[a-z]+)|'
			. '( \([A-Z][a-z]+\) [a-z]+)|'
			. "( (‘|')[A-Z][a-z]+(('|’)s)?( de)?( [A-Z][a-z]+(-([A-Z])?[a-z]+)*)*('|’)*)|"
			. '( ×| Group| (sub)?sp\.| (con)?(sub)?(notho)?var\.| (sub)?ser\.| (sub)?sect\.|'
			. ' subg\.| (sub)?f\.))*))';

		$value = new EntityIdValue( new ItemId( 'Q1' ) );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P345' ), $value ) );

		$result = $this->formatChecker->checkConstraint(
			$statement,
			$this->getConstraintMock( $this->formatParameter( $pattern ) ),
			$this->getEntity()
		);
		$this->assertEquals( 'violation', $result->getStatus(), 'check should not comply' );
	}

	public function testFormatConstraintNoValueSnak() {
		$pattern = ".";
		$statement = new Statement( new PropertyNoValueSnak( 1 ) );

		$result = $this->formatChecker->checkConstraint(
			$statement,
			$this->getConstraintMock( $this->formatParameter( $pattern ) ),
			$this->getEntity()
		);
		$this->assertEquals( 'violation', $result->getStatus(), 'check should not comply' );
	}

	public function testFormatConstraintWithoutSparql() {
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P1' ), new StringValue( '' ) ) );
		$constraint = $this->getConstraintMock( $this->formatParameter( '.' ) );
		$checker = new FormatChecker(
			$this->getConstraintParameterParser(),
			$this->getConstraintParameterRenderer(),
			$this->getDefaultConfig(),
			null
		);

		$result = $checker->checkConstraint(
			$statement,
			$constraint,
			$this->getEntity()
		);

		$this->assertTodoViolation( $result );
	}

	public function testFormatConstraintDisabled() {
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P1' ), new StringValue( '' ) ) );
		$constraint = $this->getConstraintMock( $this->formatParameter( '.' ) );
		$sparqlHelper = $this->getMockBuilder( SparqlHelper::class )
					  ->disableOriginalConstructor()
					  ->setMethods( [ 'matchesRegularExpression' ] )
					  ->getMock();
		$sparqlHelper->expects( $this->never() )->method( 'matchesRegularExpression' );
		$checker = new FormatChecker(
			$this->getConstraintParameterParser(),
			$this->getConstraintParameterRenderer(),
			new HashConfig( [ 'WBQualityConstraintsCheckFormatConstraint' => false ] ),
			$sparqlHelper
		);

		$result = $checker->checkConstraint(
			$statement,
			$constraint,
			$this->getEntity()
		);

		$this->assertTodo( $result );
	}

	/**
	 * @param string[] $parameters
	 *
	 * @return Constraint
	 */
	private function getConstraintMock( array $parameters ) {
		$mock = $this
			->getMockBuilder( Constraint::class )
			->disableOriginalConstructor()
			->getMock();
		$mock->expects( $this->any() )
			 ->method( 'getConstraintParameters' )
			 ->will( $this->returnValue( $parameters ) );
		$mock->expects( $this->any() )
			 ->method( 'getConstraintTypeItemId' )
			 ->will( $this->returnValue( 'Format' ) );

		return $mock;
	}

	/**
	 * @return EntityDocument
	 */
	private function getEntity() {
		return new Item( new ItemId( 'Q1' ) );
	}

}
