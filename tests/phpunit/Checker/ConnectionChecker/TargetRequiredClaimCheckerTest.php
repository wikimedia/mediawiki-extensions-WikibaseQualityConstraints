<?php

namespace WikibaseQuality\ConstraintReport\Test\ConnectionChecker;

use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use DataValues\StringValue;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\TargetRequiredClaimChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConnectionCheckerHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper;
use WikibaseQuality\Tests\Helper\JsonFileEntityLookup;


/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\TargetRequiredClaimChecker
 *
 * @group WikibaseQualityConstraints
 *
 * @uses   WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 * @uses   WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class TargetRequiredClaimCheckerTest extends \MediaWikiTestCase {

	private $lookup;
	private $helper;
	private $connectionCheckerHelper;
	private $checker;
	private $entity;

	protected function setUp() {
		parent::setUp();
		$this->lookup = new JsonFileEntityLookup( __DIR__ );
		$this->helper = new ConstraintReportHelper();
		$this->connectionCheckerHelper = new ConnectionCheckerHelper();
		$this->checker = new TargetRequiredClaimChecker( $this->lookup, $this->helper, $this->connectionCheckerHelper );
		$this->entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
	}

	protected function tearDown() {
		unset( $this->lookup );
		unset( $this->helper );
		unset( $this->connectionCheckerHelper );
		unset( $this->checker );
		unset( $this->entity );
		parent::tearDown();
	}

	public function testTargetRequiredClaimConstraintValid() {
		$value = new EntityIdValue( new ItemId( 'Q5' ) );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) );

		$constraintParameters = array(
			'property' => 'P2',
			'item' => 'Q42'
		);
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ) );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testTargetRequiredClaimConstraintWrongItem() {
		$value = new EntityIdValue( new ItemId( 'Q5' ) );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) );

		$constraintParameters = array(
			'property' => 'P2',
			'item' => 'Q2'
		);
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ) );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testTargetRequiredClaimConstraintOnlyProperty() {
		$value = new EntityIdValue( new ItemId( 'Q5' ) );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) );

		$constraintParameters = array(
			'property' => 'P2'
		);
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ) );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testTargetRequiredClaimConstraintOnlyPropertyButDoesNotExist() {
		$value = new EntityIdValue( new ItemId( 'Q5' ) );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) );

		$constraintParameters = array(
			'property' => 'P3'
		);
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ) );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testTargetRequiredClaimConstraintWithoutProperty() {
		$value = new EntityIdValue( new ItemId( 'Q5' ) );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) );

		$constraintParameters = array();
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ) );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testTargetRequiredClaimConstraintWrongDataTypeForItem() {
		$value = new StringValue( 'Q5' );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) );

		$constraintParameters = array(
			'property' => 'P2'
		);
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ) );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testTargetRequiredClaimConstraintItemDoesNotExist() {
		$value = new EntityIdValue( new ItemId( 'Q100' ) );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) );

		$constraintParameters = array(
			'property' => 'P2'
		);
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ) );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testTargetRequiredClaimConstraintNoValueSnak() {
		$statement = new Statement( new PropertyNoValueSnak( 1 ) );

		$constraintParameters = array(
			'property' => 'P2'
		);
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ) );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
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
			 ->will( $this->returnValue( 'Target required claim' ) );

		return $mock;
	}

}