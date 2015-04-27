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
use WikidataQuality\ConstraintReport\ConstraintCheck\Checker\ValueTypeChecker;
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
class ValueTypeCheckerTest extends \MediaWikiTestCase {

	private $lookup;
	private $checker;
	private $valueTypePropertyId;

	protected function setUp() {
		parent::setUp();
		$this->helper = new ConstraintReportHelper();
		$this->lookup = new JsonFileEntityLookup( __DIR__ );
		$this->checker = new ValueTypeChecker( $this->lookup, new ConstraintReportHelper(), new TypeCheckerHelper( $this->lookup ) );
		$this->valueTypePropertyId = new PropertyId( 'P1234' );
	}

	protected function tearDown() {
		unset( $this->lookup );
		unset( $this->valueTypePropertyId );
		unset( $this->valueTypeTypeChecker );
		parent::tearDown();
	}


	// relation instance
	public function testCheckValueTypeConstraintInstanceValid() {
		$statement = new Statement( new Claim( new PropertyValueSnak( $this->valueTypePropertyId, new EntityIdValue( new ItemId( 'Q1' ) ) ) ) );
		$constraintParameters = array(
			'relation' => 'instance',
			'class' => array( 'Q100', 'Q101' )
		);
		$checkResult = $this->checker->checkConstraint( $statement, $constraintParameters );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testCheckValueTypeConstraintInstanceValidWithIndirection() {
		$statement = new Statement( new Claim( new PropertyValueSnak( $this->valueTypePropertyId, new EntityIdValue( new ItemId( 'Q2' ) ) ) ) );
		$constraintParameters = array(
			'relation' => 'instance',
			'class' => array( 'Q100', 'Q101' )
		);
		$checkResult = $this->checker->checkConstraint( $statement, $constraintParameters );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testCheckValueTypeConstraintInstanceValidWithMoreIndirection() {
		$statement = new Statement( new Claim( new PropertyValueSnak( $this->valueTypePropertyId, new EntityIdValue( new ItemId( 'Q3' ) ) ) ) );
		$constraintParameters = array(
			'relation' => 'instance',
			'class' => array( 'Q100', 'Q101' )
		);
		$checkResult = $this->checker->checkConstraint( $statement, $constraintParameters );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	// relation subclass
	public function testCheckValueTypeConstraintSubclassValid() {
		$statement = new Statement( new Claim( new PropertyValueSnak( $this->valueTypePropertyId, new EntityIdValue( new ItemId( 'Q4' ) ) ) ) );
		$constraintParameters = array(
			'relation' => 'subclass',
			'class' => array( 'Q100', 'Q101' )
		);
		$checkResult = $this->checker->checkConstraint( $statement, $constraintParameters );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testCheckValueTypeConstraintSubclassValidWithIndirection() {
		$statement = new Statement( new Claim( new PropertyValueSnak( $this->valueTypePropertyId, new EntityIdValue( new ItemId( 'Q5' ) ) ) ) );
		$constraintParameters = array(
			'relation' => 'subclass',
			'class' => array( 'Q100', 'Q101' )
		);
		$checkResult = $this->checker->checkConstraint( $statement, $constraintParameters );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testCheckValueTypeConstraintSubclassValidWithMoreIndirection() {
		$statement = new Statement( new Claim( new PropertyValueSnak( $this->valueTypePropertyId, new EntityIdValue( new ItemId( 'Q6' ) ) ) ) );
		$constraintParameters = array(
			'relation' => 'subclass',
			'class' => array( 'Q100', 'Q101' )
		);
		$checkResult = $this->checker->checkConstraint( $statement, $constraintParameters );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	// relation instance, violations
	public function testCheckValueTypeConstraintInstanceInvalid() {
		$statement = new Statement( new Claim( new PropertyValueSnak( $this->valueTypePropertyId, new EntityIdValue( new ItemId( 'Q1' ) ) ) ) );
		$constraintParameters = array(
			'relation' => 'instance',
			'class' => array( 'Q200', 'Q201' )
		);
		$checkResult = $this->checker->checkConstraint( $statement, $constraintParameters );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testCheckValueTypeConstraintInstanceInvalidWithIndirection() {
		$statement = new Statement( new Claim( new PropertyValueSnak( $this->valueTypePropertyId, new EntityIdValue( new ItemId( 'Q2' ) ) ) ) );
		$constraintParameters = array(
			'relation' => 'instance',
			'class' => array( 'Q200', 'Q201' )
		);
		$checkResult = $this->checker->checkConstraint( $statement, $constraintParameters );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testCheckValueTypeConstraintInstanceInvalidWithMoreIndirection() {
		$statement = new Statement( new Claim( new PropertyValueSnak( $this->valueTypePropertyId, new EntityIdValue( new ItemId( 'Q3' ) ) ) ) );
		$constraintParameters = array(
			'relation' => 'instance',
			'class' => array( 'Q200', 'Q201' )
		);
		$checkResult = $this->checker->checkConstraint( $statement, $constraintParameters );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	// relation subclass, violations
	public function testCheckValueTypeConstraintSubclassInvalid() {
		$statement = new Statement( new Claim( new PropertyValueSnak( $this->valueTypePropertyId, new EntityIdValue( new ItemId( 'Q4' ) ) ) ) );
		$constraintParameters = array(
			'relation' => 'subclass',
			'class' => array( 'Q200', 'Q201' )
		);
		$checkResult = $this->checker->checkConstraint( $statement, $constraintParameters );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testCheckValueTypeConstraintSubclassInvalidWithIndirection() {
		$statement = new Statement( new Claim( new PropertyValueSnak( $this->valueTypePropertyId, new EntityIdValue( new ItemId( 'Q5' ) ) ) ) );
		$constraintParameters = array(
			'relation' => 'subclass',
			'class' => array( 'Q200', 'Q201' )
		);
		$checkResult = $this->checker->checkConstraint( $statement, $constraintParameters );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testCheckValueTypeConstraintSubclassInvalidWithMoreIndirection() {
		$statement = new Statement( new Claim( new PropertyValueSnak( $this->valueTypePropertyId, new EntityIdValue( new ItemId( 'Q6' ) ) ) ) );
		$constraintParameters = array(
			'relation' => 'subclass',
			'class' => array( 'Q200', 'Q201' )
		);
		$checkResult = $this->checker->checkConstraint( $statement, $constraintParameters );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	// edge cases
	public function testCheckValueTypeConstraintMissingRelation() {
		$statement = new Statement( new Claim( new PropertyValueSnak( $this->valueTypePropertyId, new EntityIdValue( new ItemId( 'Q1' ) ) ) ) );
		$constraintParameters = array(
			'relation' => '',
			'class' => array( 'Q100', 'Q101' )
		);
		$checkResult = $this->checker->checkConstraint( $statement, $constraintParameters );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testCheckValueTypeConstraintMissingClass() {
		$statement = new Statement( new Claim( new PropertyValueSnak( $this->valueTypePropertyId, new EntityIdValue( new ItemId( 'Q1' ) ) ) ) );
		$constraintParameters = array(
			'relation' => 'instance',
			'class' => array( '' )
		);
		$checkResult = $this->checker->checkConstraint( $statement, $constraintParameters );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testCheckValueTypeConstraintWrongType() {
		$statement = new Statement( new Claim( new PropertyValueSnak( $this->valueTypePropertyId, new StringValue( 'foo bar baz' ) ) ) );
		$constraintParameters = array(
			'relation' => 'instance',
			'class' => array( 'Q100', 'Q101' )
		);
		$checkResult = $this->checker->checkConstraint( $statement, $constraintParameters );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testCheckValueTypeConstraintNonExistingValue() {
		$statement = new Statement( new Claim( new PropertyValueSnak( $this->valueTypePropertyId, new EntityIdValue( new ItemId( 'Q100' ) ) ) ) );
		$constraintParameters = array(
			'relation' => 'instance',
			'class' => array( 'Q100', 'Q101' )
		);
		$checkResult = $this->checker->checkConstraint( $statement, $constraintParameters );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

}