<?php

namespace WikibaseQuality\ConstraintReport\Test\RangeChecker;

use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use DataValues\StringValue;
use DataValues\TimeValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\DiffWithinRangeChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\RangeCheckerHelper;
use WikibaseQuality\Tests\Helper\JsonFileEntityLookup;

/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\DiffWithinRangeChecker
 *
 * @group WikibaseQualityConstraints
 *
 * @uses   WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 * @uses   WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class DiffWithinRangeCheckerTest extends \MediaWikiTestCase {

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
	 * @var DiffWithinRangeChecker
	 */
	private $checker;

	protected function setUp() {
		parent::setUp();
		$this->helper = new ConstraintParameterParser();
		$this->lookup = new JsonFileEntityLookup( __DIR__ );
		$this->timeValue = new TimeValue( '+00000001970-01-01T00:00:00Z', 0, 0, 0, 11, 'http://www.wikidata.org/entity/Q1985727' );
		$this->checker = new DiffWithinRangeChecker( $this->helper, new RangeCheckerHelper() );
	}

	protected function tearDown() {
		unset( $this->helper );
		unset( $this->lookup );
		unset( $this->timeValue );
		unset( $this->checker );
		parent::tearDown();
	}

	public function testDiffWithinRangeConstraintWithinRange() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q4' ) );
		$constraintParameters = array(
			'property' => 'P569',
			'minimum_quantity' => 0,
			'maximum_quantity' => 150
		);
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P570' ), $this->timeValue ) );

		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testDiffWithinRangeConstraintTooSmall() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q5' ) );
		$constraintParameters = array(
			'property' => 'P569',
			'minimum_quantity' => 50,
			'maximum_quantity' => 150
		);
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P570' ), $this->timeValue ) );

		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testDiffWithinRangeConstraintTooBig() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q6' ) );
		$constraintParameters = array(
			'property' => 'P569',
			'minimum_quantity' => 0,
			'maximum_quantity' => 150
		);
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P570' ), $this->timeValue ) );

		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testDiffWithinRangeConstraintWithoutProperty() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
		$constraintParameters = array();
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P1457' ), $this->timeValue ) );

		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testDiffWithinRangeConstraintWrongType() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
		$constraintParameters = array(
			'property' => 'P1'
		);
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P1457' ), new StringValue( '1.1.1970' ) ) );

		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testDiffWithinRangeConstraintWrongTypeOfProperty() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q7' ) );
		$constraintParameters = array(
			'property' => 'P569',
			'minimum_quantity' => 1,
			'maximum_quantity' => 100
		);
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P570' ), $this->timeValue ) );

		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testDiffWithinRangeConstraintWithoutBaseProperty() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q4' ) );
		$constraintParameters = array(
			'property' => 'P1000',
			'minimum_quantity' => 0,
			'maximum_quantity' => 150
		);
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P570' ), $this->timeValue ) );

		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testDiffWithinRangeConstraintNoValueSnak() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q4' ) );
		$statement = new Statement( new PropertyNoValueSnak( 1 ) );
		$constraintParameters = array(
			'property' => 'P1000',
			'minimum_quantity' => 0,
			'maximum_quantity' => 150
		);
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	/**
	 * @param string[] $parameters
	 *
	 * @return Constraint
	 */
	private function getConstraintMock( array $parameters ) {
		$mock = $this
			->getMockBuilder( 'WikibaseQuality\ConstraintReport\Constraint' )
			->disableOriginalConstructor()
			->getMock();
		$mock->expects( $this->any() )
			 ->method( 'getConstraintParameters' )
			 ->will( $this->returnValue( $parameters ) );
		$mock->expects( $this->any() )
			 ->method( 'getConstraintTypeQid' )
			 ->will( $this->returnValue( 'Diff within range' ) );

		return $mock;
	}

}
