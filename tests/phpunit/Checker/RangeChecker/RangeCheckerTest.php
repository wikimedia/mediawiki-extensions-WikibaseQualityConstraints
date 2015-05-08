<?php

namespace WikidataQuality\ConstraintReport\Test\RangeChecker;

use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Claim\Claim;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use DataValues\DecimalValue;
use DataValues\QuantityValue;
use DataValues\StringValue;
use DataValues\TimeValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use WikidataQuality\ConstraintReport\ConstraintCheck\Checker\RangeChecker;
use WikidataQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper;
use WikidataQuality\ConstraintReport\ConstraintCheck\Helper\RangeCheckerHelper;
use WikidataQuality\Tests\Helper\JsonFileEntityLookup;


/**
 * @covers WikidataQuality\ConstraintReport\ConstraintCheck\Checker\RangeChecker
 *
 * @uses   WikidataQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 * @uses   WikidataQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class RangeCheckerTest extends \MediaWikiTestCase {

	private $helper;
	private $lookup;
	private $timeValue;
	private $checker;

	protected function setUp() {
		parent::setUp();
		$this->helper = new ConstraintReportHelper();
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
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );

		$value = new DecimalValue( 3.1415926536 );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P1457' ), new QuantityValue( $value, '1', $value, $value ) ) ) );
		$constraintParameters = array(
			'statements' => $entity->getStatements(),
			'minimum_quantity' => 0,
			'maximum_quantity' => 10,
			'minimum_date' => null,
			'maximum_date' => null
		);
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ) );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testRangeConstraintTooSmall() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q2' ) );

		$value = new DecimalValue( 42 );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P1457' ), new QuantityValue( $value, '1', $value, $value ) ) ) );
		$constraintParameters = array(
			'statements' => $entity->getStatements(),
			'minimum_quantity' => 100,
			'maximum_quantity' => 1000,
			'minimum_date' => null,
			'maximum_date' => null
		);
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ) );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testRangeConstraintTooBig() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q3' ) );

		$value = new DecimalValue( 3.141592 );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P1457' ), new QuantityValue( $value, '1', $value, $value ) ) ) );
		$constraintParameters = array(
			'statements' => $entity->getStatements(),
			'minimum_quantity' => 0,
			'maximum_quantity' => 1,
			'minimum_date' => null,
			'maximum_date' => null
		);
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ) );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testRangeConstraintTimeWithinRange() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );

		$min = new TimeValue( '+00000001960-01-01T00:00:00Z', 0, 0, 0, 11, 'http://www.wikidata.org/entity/Q1985727' );
		$max = new TimeValue( '+00000001980-01-01T00:00:00Z', 0, 0, 0, 11, 'http://www.wikidata.org/entity/Q1985727' );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P1457' ), $this->timeValue ) ) );
		$constraintParameters = array(
			'statements' => $entity->getStatements(),
			'minimum_date' => $min,
			'maximum_date' => $max,
			'minimum_quantity' => null,
			'maximum_quantity' => null
		);
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ) );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testRangeConstraintTimeTooSmall() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );

		$min = new TimeValue( '+00000001975-01-01T00:00:00Z', 0, 0, 0, 11, 'http://www.wikidata.org/entity/Q1985727' );
		$max = new TimeValue( '+00000001980-01-01T00:00:00Z', 0, 0, 0, 11, 'http://www.wikidata.org/entity/Q1985727' );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P1457' ), $this->timeValue ) ) );
		$constraintParameters = array(
			'statements' => $entity->getStatements(),
			'minimum_date' => $min,
			'maximum_date' => $max,
			'minimum_quantity' => null,
			'maximum_quantity' => null
		);
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ) );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testRangeConstraintTimeTooBig() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );

		$min = new TimeValue( '+00000001960-01-01T00:00:00Z', 0, 0, 0, 11, 'http://www.wikidata.org/entity/Q1985727' );
		$max = new TimeValue( '+00000001965-01-01T00:00:00Z', 0, 0, 0, 11, 'http://www.wikidata.org/entity/Q1985727' );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P1457' ), $this->timeValue ) ) );
		$constraintParameters = array(
			'statements' => $entity->getStatements(),
			'minimum_date' => $min,
			'maximum_date' => $max,
			'minimum_quantity' => null,
			'maximum_quantity' => null
		);
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ) );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testRangeConstraintQuantityWrongParameter() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );

		$min = new TimeValue( '+00000001970-01-01T00:00:00Z', 0, 0, 0, 11, 'http://www.wikidata.org/entity/Q1985727' );
		$value = $max = new DecimalValue( 42 );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P1457' ), new QuantityValue( $value, '1', $value, $value ) ) ) );
		$constraintParameters = array(
			'statements' => $entity->getStatements(),
			'minimum_quantity' => $min,
			'maximum_date' => $max,
			'minimum_date' => null,
			'maximum_quantity' => null
		);
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ) );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testRangeConstraintTimeWrongParameter() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );

		$min = new TimeValue( '+00000001970-01-01T00:00:00Z', 0, 0, 0, 11, 'http://www.wikidata.org/entity/Q1985727' );
		$max = new DecimalValue( 42 );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P1457' ), $this->timeValue ) ) );
		$constraintParameters = array(
			'statements' => $entity->getStatements(),
			'minimum_quantity' => $min,
			'maximum_date' => $max,
			'minimum_date' => null,
			'maximum_quantity' => null
		);
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ) );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testRangeConstraintWrongType() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );

		$min = new TimeValue( '+00000001960-01-01T00:00:00Z', 0, 0, 0, 11, 'http://www.wikidata.org/entity/Q1985727' );
		$max = new TimeValue( '+00000001965-01-01T00:00:00Z', 0, 0, 0, 11, 'http://www.wikidata.org/entity/Q1985727' );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P1457' ), new StringValue( '1.1.1970' ) ) ) );
		$constraintParameters = array(
			'statements' => $entity->getStatements(),
			'minimum_date' => $min,
			'maximum_date' => $max,
			'minimum_quantity' => null,
			'maximum_quantity' => null
		);
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ) );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testRangeConstraintNoValueSnak() {
		$statement = new Statement( new Claim( new PropertyNoValueSnak( 1 ) ) );
		$constraintParameters = array();
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ) );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	private function getConstraintMock( $parameter ) {
		$mock = $this
			->getMockBuilder( 'WikidataQuality\ConstraintReport\Constraint' )
			->disableOriginalConstructor()
			->getMock();
		$mock->expects( $this->any() )
			 ->method( 'getConstraintParameter' )
			 ->willReturn( $parameter );
		$mock->expects( $this->any() )
			 ->method( 'getConstraintTypeQid' )
			 ->willReturn( 'Range' );

		return $mock;
	}

}