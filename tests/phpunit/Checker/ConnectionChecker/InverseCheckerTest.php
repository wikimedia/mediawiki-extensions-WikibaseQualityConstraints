<?php

namespace WikibaseQuality\ConstraintReport\Test\ConnectionChecker;

use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use DataValues\StringValue;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\InverseChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConnectionCheckerHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;
use WikibaseQuality\Tests\Helper\JsonFileEntityLookup;


/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\InverseChecker
 *
 * @group WikibaseQualityConstraints
 *
 * @uses   WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 * @uses   WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class InverseCheckerTest extends \MediaWikiTestCase {

	private $lookup;
	private $helper;
	private $connectionCheckerHelper;
	private $checker;

	protected function setUp() {
		parent::setUp();
		$this->lookup = new JsonFileEntityLookup( __DIR__ );
		$this->helper = new ConstraintParameterParser();
		$this->connectionCheckerHelper = new ConnectionCheckerHelper();
		$this->checker = new InverseChecker( $this->lookup, $this->helper, $this->connectionCheckerHelper );
	}

	protected function tearDown() {
		unset( $this->lookup );
		unset( $this->helper );
		unset( $this->connectionCheckerHelper );
		unset( $this->checker );
		parent::tearDown();
	}

	public function testInverseConstraintValid() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
		
		$value = new EntityIdValue( new ItemId( 'Q7' ) );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) );

		$constraintParameters = array(
			'property' => 'P1'
		);
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testInverseConstraintWrongItem() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );

		$value = new EntityIdValue( new ItemId( 'Q8' ) );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) );

		$constraintParameters = array(
			'property' => 'P1'
		);
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testInverseConstraintWithoutProperty() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );

		$value = new EntityIdValue( new ItemId( 'Q7' ) );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) );

		$constraintParameters = array();
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testInverseConstraintWrongDataTypeForItem() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );

		$value = new StringValue( 'Q7' );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) );

		$constraintParameters = array(
			'property' => 'P1'
		);
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testInverseConstraintItemDoesNotExist() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );

		$value = new EntityIdValue( new ItemId( 'Q100' ) );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) );

		$constraintParameters = array(
			'property' => 'P1'
		);
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testInverseConstraintNoValueSnak() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );

		$statement = new Statement( new PropertyNoValueSnak( 1 ) );

		$constraintParameters = array(
			'property' => 'P1'
		);
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	private function getConstraintMock( $parameter ) {
		$mock = $this
			->getMockBuilder( 'WikibaseQuality\ConstraintReport\Constraint' )
			->disableOriginalConstructor()
			->getMock();
		$mock->expects( $this->any() )
			 ->method( 'getConstraintParameters' )
			 ->willReturn( $parameter );
		$mock->expects( $this->any() )
			 ->method( 'getConstraintTypeQid' )
			 ->willReturn( 'Inverse' );

		return $mock;
	}

}