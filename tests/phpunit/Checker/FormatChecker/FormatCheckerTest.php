<?php

namespace WikidataQuality\ConstraintReport\Test\FormatChecker;

use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Claim\Claim;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use DataValues\StringValue;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use WikidataQuality\ConstraintReport\ConstraintCheck\Checker\FormatChecker;
use WikidataQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper;


/**
 * @covers WikidataQuality\ConstraintReport\ConstraintCheck\Checker\FormatChecker
 *
 * @uses   WikidataQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 * @uses   WikidataQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class FormatCheckerTest extends \MediaWikiTestCase {

	private $helper;
	private $formatChecker;

	protected function setUp() {
		parent::setUp();
		$this->helper = new ConstraintReportHelper();
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

		$statement1 = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P345' ), $value1 ) ) );
		$statement2 = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P345' ), $value2 ) ) );
		$statement3 = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P345' ), $value3 ) ) );
		$statement4 = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P345' ), $value4 ) ) );
		$statement5 = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P345' ), $value5 ) ) );
		$statement6 = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P345' ), $value6 ) ) );
		$statement7 = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P345' ), $value7 ) ) );
		$statement8 = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P345' ), $value8 ) ) );
		$statement9 = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P345' ), $value9 ) ) );
		$statement10 = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P345' ), $value10 ) ) );

		$this->assertEquals( 'compliance', $this->formatChecker->checkConstraint( $statement1, array( 'pattern' => $pattern ) )->getStatus(), 'check should comply' );
		$this->assertEquals( 'compliance', $this->formatChecker->checkConstraint( $statement2, array( 'pattern' => $pattern ) )->getStatus(), 'check should comply' );
		$this->assertEquals( 'compliance', $this->formatChecker->checkConstraint( $statement3, array( 'pattern' => $pattern ) )->getStatus(), 'check should comply' );
		$this->assertEquals( 'compliance', $this->formatChecker->checkConstraint( $statement4, array( 'pattern' => $pattern ) )->getStatus(), 'check should comply' );
		$this->assertEquals( 'violation', $this->formatChecker->checkConstraint( $statement5, array( 'pattern' => $pattern ) )->getStatus(), 'check should not comply' );
		$this->assertEquals( 'violation', $this->formatChecker->checkConstraint( $statement6, array( 'pattern' => $pattern ) )->getStatus(), 'check should not comply' );
		$this->assertEquals( 'violation', $this->formatChecker->checkConstraint( $statement7, array( 'pattern' => $pattern ) )->getStatus(), 'check should not comply' );
		$this->assertEquals( 'violation', $this->formatChecker->checkConstraint( $statement8, array( 'pattern' => $pattern ) )->getStatus(), 'check should not comply' );
		$this->assertEquals( 'violation', $this->formatChecker->checkConstraint( $statement9, array( 'pattern' => $pattern ) )->getStatus(), 'check should not comply' );
		$this->assertEquals( 'violation', $this->formatChecker->checkConstraint( $statement10, array( 'pattern' => $pattern ) )->getStatus(), 'check should not comply' );
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

		$statement1 = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P345' ), $value1 ) ) );
		$statement2 = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P345' ), $value2 ) ) );
		$statement3 = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P345' ), $value3 ) ) );
		$statement4 = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P345' ), $value4 ) ) );
		$statement5 = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P345' ), $value5 ) ) );
		$statement6 = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P345' ), $value6 ) ) );
		$statement7 = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P345' ), $value7 ) ) );
		$statement8 = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P345' ), $value8 ) ) );
		$statement9 = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P345' ), $value9 ) ) );
		$statement10 = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P345' ), $value10 ) ) );

		$this->assertEquals( 'compliance', $this->formatChecker->checkConstraint( $statement1, array( 'pattern' => $pattern ) )->getStatus(), 'check should comply' );
		$this->assertEquals( 'compliance', $this->formatChecker->checkConstraint( $statement2, array( 'pattern' => $pattern ) )->getStatus(), 'check should comply' );
		$this->assertEquals( 'compliance', $this->formatChecker->checkConstraint( $statement3, array( 'pattern' => $pattern ) )->getStatus(), 'check should comply' );
		$this->assertEquals( 'compliance', $this->formatChecker->checkConstraint( $statement4, array( 'pattern' => $pattern ) )->getStatus(), 'check should comply' );
		$this->assertEquals( 'violation', $this->formatChecker->checkConstraint( $statement5, array( 'pattern' => $pattern ) )->getStatus(), 'check should not comply' );
		$this->assertEquals( 'violation', $this->formatChecker->checkConstraint( $statement6, array( 'pattern' => $pattern ) )->getStatus(), 'check should not comply' );
		$this->assertEquals( 'violation', $this->formatChecker->checkConstraint( $statement7, array( 'pattern' => $pattern ) )->getStatus(), 'check should not comply' );
		$this->assertEquals( 'violation', $this->formatChecker->checkConstraint( $statement8, array( 'pattern' => $pattern ) )->getStatus(), 'check should not comply' );
		$this->assertEquals( 'violation', $this->formatChecker->checkConstraint( $statement9, array( 'pattern' => $pattern ) )->getStatus(), 'check should not comply' );
		$this->assertEquals( 'violation', $this->formatChecker->checkConstraint( $statement10, array( 'pattern' => $pattern ) )->getStatus(), 'check should not comply' );
	}

	public function testFormatConstraintEmptyPattern() {
		$pattern = null;
		$value = new StringValue( 'Populus × canescens' );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P345' ), $value ) ) );
		$this->assertEquals( 'violation', $this->formatChecker->checkConstraint( $statement, array( 'pattern' => $pattern ) )->getStatus(), 'check should not comply' );
	}

	public function testFormatConstraintNoStringValue() {
		$pattern = "(|somevalue|novalue|.*virus.*|.*viroid.*|.*phage.*|((×)?[A-Z]([a-z]+-)?[a-z]+(( [A-Z]?[a-z]+)|( ([a-z]+-)?([a-z]+-)?[a-z]+)|( ×([a-z]+-)?([a-z]+-)?([a-z]+-)?([a-z]+-)?[a-z]+)|( \([A-Z][a-z]+\) [a-z]+)|( (‘|')[A-Z][a-z]+(('|’)s)?( de)?( [A-Z][a-z]+(-([A-Z])?[a-z]+)*)*('|’)*)|( ×| Group| (sub)?sp\.| (con)?(sub)?(notho)?var\.| (sub)?ser\.| (sub)?sect\.| subg\.| (sub)?f\.))*))";
		$value = new EntityIdValue( new ItemId( 'Q1' ) );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P345' ), $value ) ) );
		$this->assertEquals( 'violation', $this->formatChecker->checkConstraint( $statement, array( 'pattern' => $pattern ) )->getStatus(), 'check should not comply' );
	}

}