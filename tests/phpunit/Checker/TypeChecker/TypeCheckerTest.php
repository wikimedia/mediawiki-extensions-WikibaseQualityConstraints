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
use WikidataQuality\Tests\Helper\JsonFileEntityLookup;


/**
 * @covers WikidataQuality\ConstraintReport\ConstraintCheck\Checker\TypeChecker
 *
 * @uses   WikidataQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 * @uses   WikidataQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper
 *
 * @group WikidataQualityConstraints
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class TypeCheckerTest extends \MediaWikiTestCase {

	private $helper;
	private $lookup;

	private $typeStatement;
	private $valueTypePropertyId;
	private $valueTypeTypeChecker;

	protected function setUp() {
		parent::setUp();
		$this->helper = new ConstraintReportHelper();
		$this->lookup = new JsonFileEntityLookup( __DIR__ );

		$this->typeStatement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P1' ), new EntityIdValue( new ItemId( 'Q42' ) ) ) ) );
		$this->valueTypePropertyId = new PropertyId( 'P1234' );
		$this->valueTypeTypeChecker = new TypeChecker( new StatementList( array () ), $this->lookup, $this->helper );
	}

	protected function tearDown() {
		unset( $this->helper );
		unset( $this->lookup );
		unset( $this->typeStatement );
		unset( $this->value );
		unset( $this->valueTypePropertyId );
		unset( $this->valueTypeTypeChecker );
		parent::tearDown();
	}

	/*
	 * Following tests are testing the 'Inverse' constraint.
	 */

	// relation instance
	public function testCheckTypeConstraintInstanceValid() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
		$typeChecker = new TypeChecker( $entity->getStatements(), $this->lookup, $this->helper );
		$checkResult = $typeChecker->checkTypeConstraint( $this->typeStatement, array ( 'Q100', 'Q101' ), 'instance' );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testCheckTypeConstraintInstanceValidWithIndirection() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q2' ) );
		$typeChecker = new TypeChecker( $entity->getStatements(), $this->lookup, $this->helper );
		$checkResult = $typeChecker->checkTypeConstraint( $this->typeStatement, array ( 'Q100', 'Q101' ), 'instance' );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testCheckTypeConstraintInstanceValidWithMoreIndirection() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q3' ) );
		$typeChecker = new TypeChecker( $entity->getStatements(), $this->lookup, $this->helper );
		$checkResult = $typeChecker->checkTypeConstraint( $this->typeStatement, array ( 'Q100', 'Q101' ), 'instance' );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	// relation subclass
	public function testCheckTypeConstraintSubclassValid() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q4' ) );
		$typeChecker = new TypeChecker( $entity->getStatements(), $this->lookup, $this->helper );
		$checkResult = $typeChecker->checkTypeConstraint( $this->typeStatement, array ( 'Q100', 'Q101' ), 'subclass' );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testCheckTypeConstraintSubclassValidWithIndirection() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q5' ) );
		$typeChecker = new TypeChecker( $entity->getStatements(), $this->lookup, $this->helper );
		$checkResult = $typeChecker->checkTypeConstraint( $this->typeStatement, array ( 'Q100', 'Q101' ), 'subclass' );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testCheckTypeConstraintSubclassValidWithMoreIndirection() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q6' ) );
		$typeChecker = new TypeChecker( $entity->getStatements(), $this->lookup, $this->helper );
		$checkResult = $typeChecker->checkTypeConstraint( $this->typeStatement, array ( 'Q100', 'Q101' ), 'subclass' );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	// relation instance, violations
	public function testCheckTypeConstraintInstanceInvalid() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
		$typeChecker = new TypeChecker( $entity->getStatements(), $this->lookup, $this->helper );
		$checkResult = $typeChecker->checkTypeConstraint( $this->typeStatement, array ( 'Q200', 'Q201' ), 'instance' );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testCheckTypeConstraintInstanceInvalidWithIndirection() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q2' ) );
		$typeChecker = new TypeChecker( $entity->getStatements(), $this->lookup, $this->helper );
		$checkResult = $typeChecker->checkTypeConstraint( $this->typeStatement, array ( 'Q200', 'Q201' ), 'instance' );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testCheckTypeConstraintInstanceInvalidWithMoreIndirection() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q3' ) );
		$typeChecker = new TypeChecker( $entity->getStatements(), $this->lookup, $this->helper );
		$checkResult = $typeChecker->checkTypeConstraint( $this->typeStatement, array ( 'Q200', 'Q201' ), 'instance' );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	// relation subclass, violations
	public function testCheckTypeConstraintSubclassInvalid() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q4' ) );
		$typeChecker = new TypeChecker( $entity->getStatements(), $this->lookup, $this->helper );
		$checkResult = $typeChecker->checkTypeConstraint( $this->typeStatement, array ( 'Q200', 'Q201' ), 'subclass' );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testCheckTypeConstraintSubclassInvalidWithIndirection() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q5' ) );
		$typeChecker = new TypeChecker( $entity->getStatements(), $this->lookup, $this->helper );
		$checkResult = $typeChecker->checkTypeConstraint( $this->typeStatement, array ( 'Q200', 'Q201' ), 'subclass' );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testCheckTypeConstraintSubclassInvalidWithMoreIndirection() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q6' ) );
		$typeChecker = new TypeChecker( $entity->getStatements(), $this->lookup, $this->helper );
		$checkResult = $typeChecker->checkTypeConstraint( $this->typeStatement, array ( 'Q200', 'Q201' ), 'subclass' );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	// edge cases
	public function testCheckTypeConstraintMissingRelation() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
		$typeChecker = new TypeChecker( $entity->getStatements(), $this->lookup, $this->helper );
		$checkResult = $typeChecker->checkTypeConstraint( $this->typeStatement, array ( '1200', 'Q101' ), null );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testCheckTypeConstraintMissingClass() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
		$typeChecker = new TypeChecker( $entity->getStatements(), $this->lookup, $this->helper );
		$checkResult = $typeChecker->checkTypeConstraint( $this->typeStatement, array ( '' ), 'subclass' );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	// cyclic subclass chain
	public function testCheckTypeConstraintSubclassCycle() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q7' ) );
		$typeChecker = new TypeChecker( $entity->getStatements(), $this->lookup, $this->helper );
		$checkResult = $typeChecker->checkTypeConstraint( $this->typeStatement, array ( 'Q100', 'Q101' ), 'instance' );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	/*
	 * Following tests are testing the 'Value type' constraint.
	 */

	// relation instance
	public function testCheckValueTypeConstraintInstanceValid() {
		$statement = new Statement( new Claim( new PropertyValueSnak( $this->valueTypePropertyId, new EntityIdValue( new ItemId( 'Q1' ) ) ) ) );
		$checkResult = $this->valueTypeTypeChecker->checkValueTypeConstraint( $statement, array (
			'Q100',
			'Q101'
		), 'instance' );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testCheckValueTypeConstraintInstanceValidWithIndirection() {
		$statement = new Statement( new Claim( new PropertyValueSnak( $this->valueTypePropertyId, new EntityIdValue( new ItemId( 'Q2' ) ) ) ) );
		$checkResult = $this->valueTypeTypeChecker->checkValueTypeConstraint( $statement, array (
			'Q100',
			'Q101'
		), 'instance' );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testCheckValueTypeConstraintInstanceValidWithMoreIndirection() {
		$statement = new Statement( new Claim( new PropertyValueSnak( $this->valueTypePropertyId, new EntityIdValue( new ItemId( 'Q3' ) ) ) ) );
		$checkResult = $this->valueTypeTypeChecker->checkValueTypeConstraint( $statement, array (
			'Q100',
			'Q101'
		), 'instance' );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	// relation subclass
	public function testCheckValueTypeConstraintSubclassValid() {
		$statement = new Statement( new Claim( new PropertyValueSnak( $this->valueTypePropertyId, new EntityIdValue( new ItemId( 'Q4' ) ) ) ) );
		$checkResult = $this->valueTypeTypeChecker->checkValueTypeConstraint( $statement, array (
			'Q100',
			'Q101'
		), 'subclass' );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testCheckValueTypeConstraintSubclassValidWithIndirection() {
		$statement = new Statement( new Claim( new PropertyValueSnak( $this->valueTypePropertyId, new EntityIdValue( new ItemId( 'Q5' ) ) ) ) );
		$checkResult = $this->valueTypeTypeChecker->checkValueTypeConstraint( $statement, array (
			'Q100',
			'Q101'
		), 'subclass' );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testCheckValueTypeConstraintSubclassValidWithMoreIndirection() {
		$statement = new Statement( new Claim( new PropertyValueSnak( $this->valueTypePropertyId, new EntityIdValue( new ItemId( 'Q6' ) ) ) ) );
		$checkResult = $this->valueTypeTypeChecker->checkValueTypeConstraint( $statement, array (
			'Q100',
			'Q101'
		), 'subclass' );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	// relation instance, violations
	public function testCheckValueTypeConstraintInstanceInvalid() {
		$statement = new Statement( new Claim( new PropertyValueSnak( $this->valueTypePropertyId, new EntityIdValue( new ItemId( 'Q1' ) ) ) ) );
		$checkResult = $this->valueTypeTypeChecker->checkValueTypeConstraint( $statement, array (
			'Q200',
			'Q201'
		), 'instance' );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testCheckValueTypeConstraintInstanceInvalidWithIndirection() {
		$statement = new Statement( new Claim( new PropertyValueSnak( $this->valueTypePropertyId, new EntityIdValue( new ItemId( 'Q2' ) ) ) ) );
		$checkResult = $this->valueTypeTypeChecker->checkValueTypeConstraint( $statement, array (
			'Q200',
			'Q201'
		), 'instance' );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testCheckValueTypeConstraintInstanceInvalidWithMoreIndirection() {
		$statement = new Statement( new Claim( new PropertyValueSnak( $this->valueTypePropertyId, new EntityIdValue( new ItemId( 'Q3' ) ) ) ) );
		$checkResult = $this->valueTypeTypeChecker->checkValueTypeConstraint( $statement, array (
			'Q200',
			'Q201'
		), 'instance' );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	// relation subclass, violations
	public function testCheckValueTypeConstraintSubclassInvalid() {
		$statement = new Statement( new Claim( new PropertyValueSnak( $this->valueTypePropertyId, new EntityIdValue( new ItemId( 'Q4' ) ) ) ) );
		$checkResult = $this->valueTypeTypeChecker->checkValueTypeConstraint( $statement, array (
			'Q200',
			'Q201'
		), 'subclass' );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testCheckValueTypeConstraintSubclassInvalidWithIndirection() {
		$statement = new Statement( new Claim( new PropertyValueSnak( $this->valueTypePropertyId, new EntityIdValue( new ItemId( 'Q5' ) ) ) ) );
		$checkResult = $this->valueTypeTypeChecker->checkValueTypeConstraint( $statement, array (
			'Q200',
			'Q201'
		), 'subclass' );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testCheckValueTypeConstraintSubclassInvalidWithMoreIndirection() {
		$statement = new Statement( new Claim( new PropertyValueSnak( $this->valueTypePropertyId, new EntityIdValue( new ItemId( 'Q6' ) ) ) ) );
		$checkResult = $this->valueTypeTypeChecker->checkValueTypeConstraint( $statement, array (
			'Q200',
			'Q201'
		), 'subclass' );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	// edge cases
	public function testCheckValueTypeConstraintMissingRelation() {
		$statement = new Statement( new Claim( new PropertyValueSnak( $this->valueTypePropertyId, new EntityIdValue( new ItemId( 'Q1' ) ) ) ) );
		$checkResult = $this->valueTypeTypeChecker->checkValueTypeConstraint( $statement, array (
			'Q100',
			'Q101'
		), null );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testCheckValueTypeConstraintMissingClass() {
		$statement = new Statement( new Claim( new PropertyValueSnak( $this->valueTypePropertyId, new EntityIdValue( new ItemId( 'Q1' ) ) ) ) );
		$checkResult = $this->valueTypeTypeChecker->checkValueTypeConstraint( $statement, array ( '' ), 'subclass' );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testCheckValueTypeConstraintWrongType() {
		$statement = new Statement( new Claim( new PropertyValueSnak( $this->valueTypePropertyId, new StringValue( 'foo bar baz' ) ) ) );
		$checkResult = $this->valueTypeTypeChecker->checkValueTypeConstraint( $statement, array (
			'Q100',
			'Q101'
		), 'instance' );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testCheckValueTypeConstraintNonExistingVale() {
		$statement = new Statement( new Claim( new PropertyValueSnak( $this->valueTypePropertyId, new EntityIdValue( new ItemId( 'Q100' ) ) ) ) );
		$checkResult = $this->valueTypeTypeChecker->checkValueTypeConstraint( $statement, array (
			'Q100',
			'Q101'
		), 'instance' );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

}