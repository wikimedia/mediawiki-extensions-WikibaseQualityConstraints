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
use WikidataQuality\ConstraintReport\ConstraintCheck\Checker\DiffWithinRangeChecker;
use WikidataQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper;
use WikidataQuality\ConstraintReport\ConstraintCheck\Helper\RangeCheckerHelper;
use WikidataQuality\Tests\Helper\JsonFileEntityLookup;


/**
 * @covers WikidataQuality\ConstraintReport\ConstraintCheck\Checker\DiffWithinRangeChecker
 *
 * @uses   WikidataQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 * @uses   WikidataQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class DiffWithinRangeCheckerTest extends \MediaWikiTestCase {

	private $helper;
	private $lookup;
	private $timeValue;
	private $checker;

	protected function setUp() {
		parent::setUp();
		$this->helper = new ConstraintReportHelper();
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
			'statements' => $entity->getStatements(),
			'property' => array( 'P569' ),
			'minimum_quantity' => 0,
			'maximum_quantity' => 150
		);
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P570' ), $this->timeValue ) ) );

		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ) );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testDiffWithinRangeConstraintTooSmall() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q5' ) );
		$constraintParameters = array(
			'statements' => $entity->getStatements(),
			'property' => array( 'P569' ),
			'minimum_quantity' => 50,
			'maximum_quantity' => 150
		);
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P570' ), $this->timeValue ) ) );

		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ) );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testDiffWithinRangeConstraintTooBig() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q6' ) );
		$constraintParameters = array(
			'statements' => $entity->getStatements(),
			'property' => array( 'P569' ),
			'minimum_quantity' => 0,
			'maximum_quantity' => 150
		);
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P570' ), $this->timeValue ) ) );

		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ) );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testDiffWithinRangeConstraintWithoutProperty() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
		$constraintParameters = array(
			'statements' => $entity->getStatements(),
			'property' => array( '' ),
			'minimum_quantity' => null,
			'maximum_quantity' => null
		);
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P1457' ), $this->timeValue ) ) );

		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ) );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testDiffWithinRangeConstraintWrongType() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
		$constraintParameters = array(
			'statements' => $entity->getStatements(),
			'property' => array( 'P1' ),
			'minimum_quantity' => null,
			'maximum_quantity' => null
		);
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P1457' ), new StringValue( '1.1.1970' ) ) ) );

		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ) );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testDiffWithinRangeConstraintWrongTypeOfProperty() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q7' ) );
		$constraintParameters = array(
			'statements' => $entity->getStatements(),
			'property' => array( 'P569' ),
			'minimum_quantity' => 1,
			'maximum_quantity' => 100
		);
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P570' ), $this->timeValue ) ) );

		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ) );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testDiffWithinRangeConstraintWithoutBaseProperty() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q4' ) );
		$constraintParameters = array(
			'statements' => $entity->getStatements(),
			'property' => array( 'P1000' ),
			'minimum_quantity' => 0,
			'maximum_quantity' => 150
		);
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P570' ), $this->timeValue ) ) );

		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ) );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testDiffWithinRangeConstraintNoValueSnak() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q4' ) );
		$statement = new Statement( new Claim( new PropertyNoValueSnak( 1 ) ) );
		$constraintParameters = array(
			'statements' => $entity->getStatements(),
			'property' => array( 'P1000' ),
			'minimum_quantity' => 0,
			'maximum_quantity' => 150
		);
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
			 ->willReturn( 'Diff within range' );

		return $mock;
	}

}