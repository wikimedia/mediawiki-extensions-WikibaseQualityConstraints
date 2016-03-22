<?php

namespace WikibaseQuality\ConstraintReport\Test\OneOfChecker;

use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use DataValues\StringValue;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\OneOfChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;

/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\OneOfChecker
 *
 * @group WikibaseQualityConstraints
 *
 * @uses   WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 * @uses   WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class OneOfCheckerTest extends \MediaWikiTestCase {

	/**
	 * @var ConstraintParameterParser
	 */
	private $helper;

	/**
	 * @var OneOfChecker
	 */
	private $oneOfChecker;

	protected function setUp() {
		parent::setUp();
		$this->helper = new ConstraintParameterParser();
		$this->oneOfChecker = new OneOfChecker( $this->helper );
	}

	protected function tearDown() {
		unset( $this->helper, $this->oneOfChecker );
		parent::tearDown();
	}

	public function testOneOfConstraint() {
		$valueIn = new EntityIdValue( new ItemId( 'Q1' ) );
		$valueNotIn = new EntityIdValue( new ItemId( 'Q9' ) );

		$statementIn = new Statement( new PropertyValueSnak( new PropertyId( 'P123' ), $valueIn ) );
		$statementNotIn = new Statement( new PropertyValueSnak( new PropertyId( 'P123' ), $valueNotIn ) );

		$values = 'Q1,Q2,Q3';

		$this->assertEquals( 'compliance', $this->oneOfChecker->checkConstraint( $statementIn, $this->getConstraintMock( array( 'item' => $values ) ) )->getStatus(), 'check should comply' );
		$this->assertEquals( 'violation', $this->oneOfChecker->checkConstraint( $statementNotIn, $this->getConstraintMock( array( 'item' => $values ) ) )->getStatus(), 'check should not comply' );
	}

	public function testOneOfConstraintWrongType() {
		$value = new StringValue( 'Q1' );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P123' ), $value ) );
		$values = 'Q1,Q2,Q3';
		$this->assertEquals( 'violation', $this->oneOfChecker->checkConstraint( $statement, $this->getConstraintMock( array( 'item' => $values ) ) )->getStatus(), 'check should not comply' );
	}

	public function testOneOfConstraintEmptyArray() {
		$value = new EntityIdValue( new ItemId( 'Q1' ) );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P123' ), $value ) );
		$this->assertEquals( 'violation', $this->oneOfChecker->checkConstraint( $statement, $this->getConstraintMock( array() ) )->getStatus(), 'check should not comply' );
	}

	public function testOneOfConstraintArrayWithSomevalue() {
		$value = new EntityIdValue( new ItemId( 'Q1' ) );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P123' ), $value ) );
		$values = 'Q1,Q2,Q3,somevalue';
		$this->assertEquals( 'compliance', $this->oneOfChecker->checkConstraint( $statement, $this->getConstraintMock( array( 'item' => $values ) ) )->getStatus(), 'check should comply' );
	}

	public function testOneOfConstraintNoValueSnak() {
		$statement = new Statement( new PropertyNoValueSnak( 1 ) );
		$values = 'Q1,Q2,Q3,somevalue';
		$this->assertEquals( 'violation', $this->oneOfChecker->checkConstraint( $statement, $this->getConstraintMock( array( 'item' => $values ) ) )->getStatus(), 'check should not comply' );
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
			 ->method( 'getConstraintTypeQid' )
			 ->will( $this->returnValue( 'One of' ) );

		return $mock;
	}

}
