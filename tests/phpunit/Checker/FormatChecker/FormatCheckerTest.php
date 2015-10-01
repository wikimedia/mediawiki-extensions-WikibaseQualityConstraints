<?php

namespace WikibaseQuality\ConstraintReport\Test\FormatChecker;

use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use DataValues\StringValue;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\FormatChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;


/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\FormatChecker
 *
 * @group WikibaseQualityConstraints
 *
 * @uses   WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 * @uses   WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class FormatCheckerTest extends \MediaWikiTestCase {

	private $helper;
	private $formatChecker;

	protected function setUp() {
		parent::setUp();
		$this->helper = new ConstraintParameterParser();
		$this->formatChecker = new FormatChecker( $this->helper );
	}

	protected function tearDown() {
		unset( $this->helper );
		unset( $this->formatChecker );
		parent::tearDown();
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

		$this->assertEquals( 'todo', $this->formatChecker->checkConstraint( $statement1, $this->getConstraintMock( array( 'pattern' => $pattern ) ) )->getStatus(), 'check should comply' );
		$this->assertEquals( 'todo', $this->formatChecker->checkConstraint( $statement2, $this->getConstraintMock( array( 'pattern' => $pattern ) ) )->getStatus(), 'check should comply' );
		$this->assertEquals( 'todo', $this->formatChecker->checkConstraint( $statement3, $this->getConstraintMock( array( 'pattern' => $pattern ) ) )->getStatus(), 'check should comply' );
		$this->assertEquals( 'todo', $this->formatChecker->checkConstraint( $statement4, $this->getConstraintMock( array( 'pattern' => $pattern ) ) )->getStatus(), 'check should comply' );
		$this->assertEquals( 'todo', $this->formatChecker->checkConstraint( $statement5, $this->getConstraintMock( array( 'pattern' => $pattern ) ) )->getStatus(), 'check should not comply' );
		$this->assertEquals( 'todo', $this->formatChecker->checkConstraint( $statement6, $this->getConstraintMock( array( 'pattern' => $pattern ) ) )->getStatus(), 'check should not comply' );
		$this->assertEquals( 'todo', $this->formatChecker->checkConstraint( $statement7, $this->getConstraintMock( array( 'pattern' => $pattern ) ) )->getStatus(), 'check should not comply' );
		$this->assertEquals( 'todo', $this->formatChecker->checkConstraint( $statement8, $this->getConstraintMock( array( 'pattern' => $pattern ) ) )->getStatus(), 'check should not comply' );
		$this->assertEquals( 'todo', $this->formatChecker->checkConstraint( $statement9, $this->getConstraintMock( array( 'pattern' => $pattern ) ) )->getStatus(), 'check should not comply' );
		$this->assertEquals( 'todo', $this->formatChecker->checkConstraint( $statement10, $this->getConstraintMock( array( 'pattern' => $pattern ) ) )->getStatus(), 'check should not comply' );
	}

	public function testFormatConstraintTaxonName() {
		$pattern = "(|somevalue|novalue|.*virus.*|.*viroid.*|.*phage.*|((×)?[A-Z]([a-z]+-)?[a-z]+(( [A-Z]?[a-z]+)|( ([a-z]+-)?([a-z]+-)?[a-z]+)|( ×([a-z]+-)?([a-z]+-)?([a-z]+-)?([a-z]+-)?[a-z]+)|( \([A-Z][a-z]+\) [a-z]+)|( (‘|')[A-Z][a-z]+(('|’)s)?( de)?( [A-Z][a-z]+(-([A-Z])?[a-z]+)*)*('|’)*)|( ×| Group| (sub)?sp\.| (con)?(sub)?(notho)?var\.| (sub)?ser\.| (sub)?sect\.| subg\.| (sub)?f\.))*))";

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

		$this->assertEquals( 'todo', $this->formatChecker->checkConstraint( $statement1, $this->getConstraintMock( array( 'pattern' => $pattern ) ) )->getStatus(), 'check should comply' );
		$this->assertEquals( 'todo', $this->formatChecker->checkConstraint( $statement2, $this->getConstraintMock( array( 'pattern' => $pattern ) ) )->getStatus(), 'check should comply' );
		$this->assertEquals( 'todo', $this->formatChecker->checkConstraint( $statement3, $this->getConstraintMock( array( 'pattern' => $pattern ) ) )->getStatus(), 'check should comply' );
		$this->assertEquals( 'todo', $this->formatChecker->checkConstraint( $statement4, $this->getConstraintMock( array( 'pattern' => $pattern ) ) )->getStatus(), 'check should comply' );
		$this->assertEquals( 'todo', $this->formatChecker->checkConstraint( $statement5, $this->getConstraintMock( array( 'pattern' => $pattern ) ) )->getStatus(), 'check should not comply' );
		$this->assertEquals( 'todo', $this->formatChecker->checkConstraint( $statement6, $this->getConstraintMock( array( 'pattern' => $pattern ) ) )->getStatus(), 'check should not comply' );
		$this->assertEquals( 'todo', $this->formatChecker->checkConstraint( $statement7, $this->getConstraintMock( array( 'pattern' => $pattern ) ) )->getStatus(), 'check should not comply' );
		$this->assertEquals( 'todo', $this->formatChecker->checkConstraint( $statement8, $this->getConstraintMock( array( 'pattern' => $pattern ) ) )->getStatus(), 'check should not comply' );
		$this->assertEquals( 'todo', $this->formatChecker->checkConstraint( $statement9, $this->getConstraintMock( array( 'pattern' => $pattern ) ) )->getStatus(), 'check should not comply' );
		$this->assertEquals( 'todo', $this->formatChecker->checkConstraint( $statement10, $this->getConstraintMock( array( 'pattern' => $pattern ) ) )->getStatus(), 'check should not comply' );
	}

	public function testFormatConstraintEmptyPattern() {
		$pattern = null;
		$value = new StringValue( 'Populus × canescens' );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P345' ), $value ) );
		$this->assertEquals( 'todo', $this->formatChecker->checkConstraint( $statement, $this->getConstraintMock( array( 'pattern' => $pattern ) ) )->getStatus(), 'check should not comply' );
	}

	public function testFormatConstraintNoStringValue() {
		$pattern = "(|somevalue|novalue|.*virus.*|.*viroid.*|.*phage.*|((×)?[A-Z]([a-z]+-)?[a-z]+(( [A-Z]?[a-z]+)|( ([a-z]+-)?([a-z]+-)?[a-z]+)|( ×([a-z]+-)?([a-z]+-)?([a-z]+-)?([a-z]+-)?[a-z]+)|( \([A-Z][a-z]+\) [a-z]+)|( (‘|')[A-Z][a-z]+(('|’)s)?( de)?( [A-Z][a-z]+(-([A-Z])?[a-z]+)*)*('|’)*)|( ×| Group| (sub)?sp\.| (con)?(sub)?(notho)?var\.| (sub)?ser\.| (sub)?sect\.| subg\.| (sub)?f\.))*))";
		$value = new EntityIdValue( new ItemId( 'Q1' ) );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P345' ), $value ) );
		$this->assertEquals( 'violation', $this->formatChecker->checkConstraint( $statement, $this->getConstraintMock( array( 'pattern' => $pattern ) ) )->getStatus(), 'check should not comply' );
	}

	public function testFormatConstraintNoValueSnak() {
		$pattern = ".";
		$statement = new Statement( new PropertyNoValueSnak( 1 ) );
		$this->assertEquals( 'violation', $this->formatChecker->checkConstraint( $statement, $this->getConstraintMock( array( 'pattern' => $pattern ) ) )->getStatus(), 'check should not comply' );
	}

	private function getConstraintMock( $parameter ) {
		$mock = $this
			->getMockBuilder( 'WikibaseQuality\ConstraintReport\Constraint' )
			->disableOriginalConstructor()
			->getMock();
		$mock->expects( $this->any() )
			 ->method( 'getConstraintParameters' )
			 ->will( $this->returnValue( $parameter ) );
		$mock->expects( $this->any() )
			 ->method( 'getConstraintTypeQid' )
			 ->will( $this->returnValue( 'Format' ) );

		return $mock;
	}

}