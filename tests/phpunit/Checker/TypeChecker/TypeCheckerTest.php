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

	// relation instance
	public function testCheckTypeConstraintInstanceValid() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
		$constraintParameters = array(
			'statements' => $entity->getStatements(),
			'class' => array ( 'Q100', 'Q101' ),
			'relation' => 'instance'
		);
		$checkResult = $this->checker->checkConstraint( $this->typeStatement, $constraintParameters, $entity );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testCheckTypeConstraintInstanceValidWithIndirection() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q2' ) );
		$constraintParameters = array(
			'statements' => $entity->getStatements(),
			'class' => array ( 'Q100', 'Q101' ),
			'relation' => 'instance'
		);
		$checkResult = $this->checker->checkConstraint( $this->typeStatement, $constraintParameters, $entity );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testCheckTypeConstraintInstanceValidWithMoreIndirection() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q3' ) );
		$constraintParameters = array(
			'statements' => $entity->getStatements(),
			'class' => array ( 'Q100', 'Q101' ),
			'relation' => 'instance'
		);
		$checkResult = $this->checker->checkConstraint( $this->typeStatement, $constraintParameters, $entity );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	// relation subclass
	public function testCheckTypeConstraintSubclassValid() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q4' ) );
		$constraintParameters = array(
			'statements' => $entity->getStatements(),
			'class' => array ( 'Q100', 'Q101' ),
			'relation' => 'subclass'
		);
		$checkResult = $this->checker->checkConstraint( $this->typeStatement, $constraintParameters, $entity );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testCheckTypeConstraintSubclassValidWithIndirection() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q5' ) );
		$constraintParameters = array(
			'statements' => $entity->getStatements(),
			'class' => array ( 'Q100', 'Q101' ),
			'relation' => 'subclass'
		);
		$checkResult = $this->checker->checkConstraint( $this->typeStatement, $constraintParameters, $entity );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testCheckTypeConstraintSubclassValidWithMoreIndirection() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q6' ) );
		$constraintParameters = array(
			'statements' => $entity->getStatements(),
			'class' => array ( 'Q100', 'Q101' ),
			'relation' => 'subclass'
		);
		$checkResult = $this->checker->checkConstraint( $this->typeStatement, $constraintParameters, $entity );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	// relation instance, violations
	public function testCheckTypeConstraintInstanceInvalid() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
		$constraintParameters = array(
			'statements' => $entity->getStatements(),
			'class' => array ( 'Q200', 'Q201' ),
			'relation' => 'instance'
		);
		$checkResult = $this->checker->checkConstraint( $this->typeStatement, $constraintParameters, $entity );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testCheckTypeConstraintInstanceInvalidWithIndirection() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q2' ) );
		$constraintParameters = array(
			'statements' => $entity->getStatements(),
			'class' => array ( 'Q200', 'Q201' ),
			'relation' => 'instance'
		);
		$checkResult = $this->checker->checkConstraint( $this->typeStatement, $constraintParameters, $entity );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testCheckTypeConstraintInstanceInvalidWithMoreIndirection() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q3' ) );
		$constraintParameters = array(
			'statements' => $entity->getStatements(),
			'class' => array ( 'Q200', 'Q201' ),
			'relation' => 'instance'
		);
		$checkResult = $this->checker->checkConstraint( $this->typeStatement, $constraintParameters, $entity );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	// relation subclass, violations
	public function testCheckTypeConstraintSubclassInvalid() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q4' ) );
		$constraintParameters = array(
			'statements' => $entity->getStatements(),
			'class' => array ( 'Q200', 'Q201' ),
			'relation' => 'subclass'
		);
		$checkResult = $this->checker->checkConstraint( $this->typeStatement, $constraintParameters, $entity );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testCheckTypeConstraintSubclassInvalidWithIndirection() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q5' ) );
		$constraintParameters = array(
			'statements' => $entity->getStatements(),
			'class' => array ( 'Q200', 'Q201' ),
			'relation' => 'subclass'
		);
		$checkResult = $this->checker->checkConstraint( $this->typeStatement, $constraintParameters, $entity );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testCheckTypeConstraintSubclassInvalidWithMoreIndirection() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q6' ) );
		$constraintParameters = array(
			'statements' => $entity->getStatements(),
			'class' => array ( 'Q200', 'Q201' ),
			'relation' => 'subclass'
		);
		$checkResult = $this->checker->checkConstraint( $this->typeStatement, $constraintParameters, $entity );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	// edge cases
	public function testCheckTypeConstraintMissingRelation() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
		$constraintParameters = array(
			'statements' => $entity->getStatements(),
			'class' => array ( 'Q100', 'Q101' ),
			'relation' => ''
		);
		$checkResult = $this->checker->checkConstraint( $this->typeStatement, $constraintParameters, $entity );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testCheckTypeConstraintMissingClass() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
		$constraintParameters = array(
			'statements' => $entity->getStatements(),
			'class' => array ( '' ),
			'relation' => 'subclass'
		);
		$checkResult = $this->checker->checkConstraint( $this->typeStatement, $constraintParameters, $entity );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	// cyclic subclass chain
	public function testCheckTypeConstraintSubclassCycle() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q7' ) );
		$constraintParameters = array(
			'statements' => $entity->getStatements(),
			'class' => array ( 'Q100', 'Q101' ),
			'relation' => 'instance'
		);
		$checkResult = $this->checker->checkConstraint( $this->typeStatement, $constraintParameters, $entity );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}
}