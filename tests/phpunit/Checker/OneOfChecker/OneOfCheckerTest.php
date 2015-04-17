<?php

namespace WikidataQuality\ConstraintReport\Test\OneOfChecker;

use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Claim\Claim;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use DataValues\StringValue;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use WikidataQuality\ConstraintReport\ConstraintCheck\Checker\OneOfChecker;
use WikidataQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper;


/**
 * @covers WikidataQuality\ConstraintReport\ConstraintCheck\Checker\OneOfChecker
 *
 * @uses   WikidataQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 * @uses   WikidataQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class OneOfCheckerTest extends \MediaWikiTestCase {

	private $helper;
	private $oneOfChecker;

	protected function setUp() {
		parent::setUp();
		$this->helper = new ConstraintReportHelper();
		$this->oneOfChecker = new OneOfChecker( $this->helper );
	}

	protected function tearDown() {
		unset( $this->helper );
		unset( $this->oneOfChecker );
		parent::tearDown();
	}

	public function testCheckOneOfConstraint() {
		$valueIn = new EntityIdValue( new ItemId( 'Q1' ) );
		$valueNotIn = new EntityIdValue( new ItemId( 'Q9' ) );

		$statementIn = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P123' ), $valueIn ) ) );
		$statementNotIn = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P123' ), $valueNotIn ) ) );

		$values = array ( 'Q1', 'Q2', 'Q3' );

		$this->assertEquals( 'compliance', $this->oneOfChecker->checkOneOfConstraint( $statementIn, $values )->getStatus(), 'check should comply' );
		$this->assertEquals( 'violation', $this->oneOfChecker->checkOneOfConstraint( $statementNotIn, $values )->getStatus(), 'check should not comply' );
	}

	public function testCheckOneOfConstraintWrongType() {
		$value = new StringValue( 'Q1' );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P123' ), $value ) ) );
		$values = array ( 'Q1', 'Q2', 'Q3' );
		$this->assertEquals( 'violation', $this->oneOfChecker->checkOneOfConstraint( $statement, $values )->getStatus(), 'check should not comply' );
	}

	public function testCheckOneOfConstraintEmptyArray() {
		$value = new EntityIdValue( new ItemId( 'Q1' ) );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P123' ), $value ) ) );
		$values = array ( '' );
		$this->assertEquals( 'violation', $this->oneOfChecker->checkOneOfConstraint( $statement, $values )->getStatus(), 'check should not comply' );
	}

	public function testCheckOneOfConstraintArrayWithSomevalue() {
		$value = new EntityIdValue( new ItemId( 'Q1' ) );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P123' ), $value ) ) );
		$values = array ( 'Q1', 'Q2', 'Q3', 'somevalue' );
		$this->assertEquals( 'compliance', $this->oneOfChecker->checkOneOfConstraint( $statement, $values )->getStatus(), 'check should comply' );
	}

}