<?php
namespace WikidataQuality\ConstraintReport\Test\TypeChecker;

use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Statement\StatementList;
use Wikibase\DataModel\Claim\Claim;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use DataValues\StringValue;
use WikidataQuality\ConstraintReport\ConstraintCheck\Checker\TypeChecker;
use WikidataQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper;
use WikidataQuality\ConstraintReport\ConstraintCheck\Helper\TypeCheckerHelper;
use WikidataQuality\Tests\Helper\JsonFileEntityLookup;


/**
 * @covers WikidataQuality\ConstraintReport\ConstraintCheck\Checker\TypeChecker
 *
 * @uses   WikidataQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 * @uses   WikidataQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class TypeCheckerTest extends \MediaWikiTestCase {

	private $lookup;
	private $checker;
	private $typeStatement;

	protected function setUp() {
		parent::setUp();
		$this->lookup = new JsonFileEntityLookup( __DIR__ );
		$this->checker = new TypeChecker( $this->lookup, new ConstraintReportHelper(), new TypeCheckerHelper( $this->lookup ) );
		$this->typeStatement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P1' ), new EntityIdValue( new ItemId( 'Q42' ) ) ) ) );
	}

	protected function tearDown() {
		unset( $this->lookup );
		unset( $this->typeStatement );
		unset( $this->typeStatement );
		parent::tearDown();
	}

	// relation 'instance'
	public function testTypeConstraintInstanceValid() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
		$constraintParameters = array(
			'class' => array ( 'Q100', 'Q101' ),
			'relation' => array( 'instance' )
		);
		$checkResult = $this->checker->checkConstraint( $this->typeStatement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testTypeConstraintInstanceValidWithIndirection() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q2' ) );
		$constraintParameters = array(
			'class' => array ( 'Q100', 'Q101' ),
			'relation' => array( 'instance' )
		);
		$checkResult = $this->checker->checkConstraint( $this->typeStatement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testTypeConstraintInstanceValidWithMoreIndirection() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q3' ) );
		$constraintParameters = array(
			'class' => array ( 'Q100', 'Q101' ),
			'relation' => array( 'instance' )
		);
		$checkResult = $this->checker->checkConstraint( $this->typeStatement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	// relation 'subclass'
	public function testTypeConstraintSubclassValid() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q4' ) );
		$constraintParameters = array(
			'class' => array ( 'Q100', 'Q101' ),
			'relation' => array( 'subclass' )
		);
		$checkResult = $this->checker->checkConstraint( $this->typeStatement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testTypeConstraintSubclassValidWithIndirection() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q5' ) );
		$constraintParameters = array(
			'class' => array ( 'Q100', 'Q101' ),
			'relation' => array( 'subclass' )
		);
		$checkResult = $this->checker->checkConstraint( $this->typeStatement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testTypeConstraintSubclassValidWithMoreIndirection() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q6' ) );
		$constraintParameters = array(
			'class' => array ( 'Q100', 'Q101' ),
			'relation' => array( 'subclass' )
		);
		$checkResult = $this->checker->checkConstraint( $this->typeStatement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	// relation 'instance', violations
	public function testTypeConstraintInstanceInvalid() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
		$constraintParameters = array(
			'class' => array ( 'Q200', 'Q201' ),
			'relation' => array( 'instance' )
		);
		$checkResult = $this->checker->checkConstraint( $this->typeStatement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testTypeConstraintInstanceInvalidWithIndirection() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q2' ) );
		$constraintParameters = array(
			'class' => array ( 'Q200', 'Q201' ),
			'relation' => array( 'instance' )
		);
		$checkResult = $this->checker->checkConstraint( $this->typeStatement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testTypeConstraintInstanceInvalidWithMoreIndirection() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q3' ) );
		$constraintParameters = array(
			'class' => array ( 'Q200', 'Q201' ),
			'relation' => array( 'instance' )
		);
		$checkResult = $this->checker->checkConstraint( $this->typeStatement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	// relation 'subclass', violations
	public function testTypeConstraintSubclassInvalid() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q4' ) );
		$constraintParameters = array(
			'class' => array ( 'Q200', 'Q201' ),
			'relation' => array( 'subclass' )
		);
		$checkResult = $this->checker->checkConstraint( $this->typeStatement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testTypeConstraintSubclassInvalidWithIndirection() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q5' ) );
		$constraintParameters = array(
			'class' => array ( 'Q200', 'Q201' ),
			'relation' => array( 'subclass' )
		);
		$checkResult = $this->checker->checkConstraint( $this->typeStatement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testTypeConstraintSubclassInvalidWithMoreIndirection() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q6' ) );
		$constraintParameters = array(
			'class' => array ( 'Q200', 'Q201' ),
			'relation' => array( 'subclass' )
		);
		$checkResult = $this->checker->checkConstraint( $this->typeStatement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	// edge cases
	public function testTypeConstraintMissingRelation() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
		$constraintParameters = array(
			'class' => array ( 'Q100', 'Q101' ),
			'relation' => array( '' )
		);
		$checkResult = $this->checker->checkConstraint( $this->typeStatement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testTypeConstraintMissingClass() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
		$constraintParameters = array(
			'class' => array ( '' ),
			'relation' => array( 'subclass' )
		);
		$checkResult = $this->checker->checkConstraint( $this->typeStatement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	// cyclic subclass chain
	public function testTypeConstraintSubclassCycle() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q7' ) );
		$constraintParameters = array(
			'class' => array ( 'Q100', 'Q101' ),
			'relation' => array( 'instance' )
		);
		$checkResult = $this->checker->checkConstraint( $this->typeStatement, $this->getConstraintMock( $constraintParameters ), $entity );
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
			 ->willReturn( 'Type' );

		return $mock;
	}

}