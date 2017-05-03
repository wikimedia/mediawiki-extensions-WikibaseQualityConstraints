<?php

namespace WikibaseQuality\ConstraintReport\Test\RangeChecker;

use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use DataValues\DecimalValue;
use DataValues\QuantityValue;
use DataValues\StringValue;
use DataValues\TimeValue;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\RangeChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\RangeCheckerHelper;
use WikibaseQuality\Tests\Helper\JsonFileEntityLookup;

/**
 * @covers \WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\RangeChecker
 *
 * @group WikibaseQualityConstraints
 *
 * @uses   \WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 * @uses   \WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class RangeCheckerTest extends \MediaWikiTestCase {

	/**
	 * @var ConstraintParameterParser
	 */
	private $helper;

	/**
	 * @var JsonFileEntityLookup
	 */
	private $lookup;

	/**
	 * @var TimeValue
	 */
	private $timeValue;

	/**
	 * @var RangeChecker
	 */
	private $checker;

	protected function setUp() {
		parent::setUp();
		$this->helper = new ConstraintParameterParser();
		$this->lookup = new JsonFileEntityLookup( __DIR__ );
		$this->timeValue = new TimeValue( '+00000001970-01-01T00:00:00Z', 0, 0, 0, 11, 'http://www.wikidata.org/entity/Q1985727' );
		$this->checker = new RangeChecker( $this->helper, new RangeCheckerHelper() );
	}

	protected function tearDown() {
		unset( $this->helper );
		unset( $this->lookup );
		unset( $this->timeValue );
		unset( $this->checker );
		parent::tearDown();
	}

	public function testRangeConstraintWithinRange() {
		$value = new DecimalValue( 3.1415926536 );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P1457' ), new QuantityValue( $value, '1', $value, $value ) ) );
		$constraintParameters = array(
			'minimum_quantity' => 0,
			'maximum_quantity' => 10
		);
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $this->getEntity() );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testRangeConstraintTooSmall() {
		$value = new DecimalValue( 42 );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P1457' ), new QuantityValue( $value, '1', $value, $value ) ) );
		$constraintParameters = array(
			'minimum_quantity' => 100,
			'maximum_quantity' => 1000
		);
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $this->getEntity() );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testRangeConstraintTooBig() {
		$value = new DecimalValue( 3.141592 );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P1457' ), new QuantityValue( $value, '1', $value, $value ) ) );
		$constraintParameters = array(
			'minimum_quantity' => 0,
			'maximum_quantity' => 1
		);
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $this->getEntity() );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testRangeConstraintTimeWithinRange() {
		$this->markTestSkipped( 'RangeChecker does not correctly parse the parameters to compare – see T164279.' );
		$min = '+00000001960-01-01T00:00:00Z';
		$max = '+00000001980-01-01T00:00:00Z';
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P1457' ), $this->timeValue ) );
		$constraintParameters = array(
			'minimum_date' => $min,
			'maximum_date' => $max
		);
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $this->getEntity() );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testRangeConstraintTimeTooSmall() {
		$min = '+00000001975-01-01T00:00:00Z';
		$max = '+00000001980-01-01T00:00:00Z';
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P1457' ), $this->timeValue ) );
		$constraintParameters = array(
			'minimum_date' => $min,
			'maximum_date' => $max
		);
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $this->getEntity() );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testRangeConstraintTimeTooBig() {
		$min = '+00000001960-01-01T00:00:00Z';
		$max = '+00000001965-01-01T00:00:00Z';
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P1457' ), $this->timeValue ) );
		$constraintParameters = array(
			'minimum_date' => $min,
			'maximum_date' => $max
		);
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $this->getEntity() );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testRangeConstraintQuantityWrongParameter() {
		$min = '+00000001970-01-01T00:00:00Z';
		$max = 42;
		$value = new DecimalValue( 42 );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P1457' ), new QuantityValue( $value, '1', $value, $value ) ) );
		$constraintParameters = array(
			'minimum_quantity' => $min,
			'maximum_date' => $max
		);
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $this->getEntity() );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testRangeConstraintTimeWrongParameter() {
		$min = '+00000001970-01-01T00:00:00Z';
		$max = 42;
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P1457' ), $this->timeValue ) );
		$constraintParameters = array(
			'minimum_quantity' => $min,
			'maximum_date' => $max
		);
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $this->getEntity() );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testRangeConstraintWrongType() {
		$min = '+00000001960-01-01T00:00:00Z';
		$max = '+00000001965-01-01T00:00:00Z';
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P1457' ), new StringValue( '1.1.1970' ) ) );
		$constraintParameters = array(
			'minimum_date' => $min,
			'maximum_date' => $max
		);
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $this->getEntity() );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testRangeConstraintNoValueSnak() {
		$statement = new Statement( new PropertyNoValueSnak( 1 ) );
		$constraintParameters = array();
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $this->getEntity() );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
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
			 ->will( $this->returnValue( 'Range' ) );

		return $mock;
	}

	/**
	 * @return EntityDocument
	 */
	private function getEntity() {
		return new Item( new ItemId( 'Q1' ) );
	}

}
