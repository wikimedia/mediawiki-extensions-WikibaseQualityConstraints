<?php

namespace WikibaseQuality\ConstraintReport\Test\TypeChecker;

use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\TypeChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\TypeCheckerHelper;
use WikibaseQuality\ConstraintReport\ConstraintParameterRenderer;
use WikibaseQuality\ConstraintReport\Tests\ConstraintParameters;
use WikibaseQuality\Tests\Helper\JsonFileEntityLookup;

/**
 * @covers \WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\TypeChecker
 *
 * @group WikibaseQualityConstraints
 *
 * @uses   \WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 * @uses   \WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class TypeCheckerTest extends \MediaWikiTestCase {

	use ConstraintParameters;

	/**
	 * @var JsonFileEntityLookup
	 */
	private $lookup;

	/**
	 * @var TypeChecker
	 */
	private $checker;

	/**
	 * @var Statement
	 */
	private $typeStatement;

	protected function setUp() {
		parent::setUp();
		$this->lookup = new JsonFileEntityLookup( __DIR__ );
		$this->checker = new TypeChecker(
			$this->lookup,
			new ConstraintParameterParser(),
			new TypeCheckerHelper(
				$this->lookup,
				$this->getDefaultConfig(),
				$this->getConstraintParameterRenderer()
			),
			$this->getDefaultConfig()
		);
		$this->typeStatement = new Statement( new PropertyValueSnak( new PropertyId( 'P1' ), new EntityIdValue( new ItemId( 'Q42' ) ) ) );
	}

	protected function tearDown() {
		unset( $this->lookup );
		unset( $this->typeStatement );
		parent::tearDown();
	}

	public function testTypeConstraintInstanceValid() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
		$constraintParameters = [
			'class' => 'Q100,Q101',
			'relation' => 'instance'
		];
		$checkResult = $this->checker->checkConstraint( $this->typeStatement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testTypeConstraintInstanceValidWithIndirection() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q2' ) );
		$constraintParameters = [
			'class' => 'Q100,Q101',
			'relation' => 'instance'
		];
		$checkResult = $this->checker->checkConstraint( $this->typeStatement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testTypeConstraintInstanceValidWithMoreIndirection() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q3' ) );
		$constraintParameters = [
			'class' => 'Q100,Q101',
			'relation' => 'instance'
		];
		$checkResult = $this->checker->checkConstraint( $this->typeStatement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testTypeConstraintSubclassValid() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q4' ) );
		$constraintParameters = [
			'class' => 'Q100,Q101',
			'relation' => 'subclass'
		];
		$checkResult = $this->checker->checkConstraint( $this->typeStatement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testTypeConstraintSubclassValidWithIndirection() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q5' ) );
		$constraintParameters = [
			'class' => 'Q100,Q101',
			'relation' => 'subclass'
		];
		$checkResult = $this->checker->checkConstraint( $this->typeStatement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testTypeConstraintSubclassValidWithMoreIndirection() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q6' ) );
		$constraintParameters = [
			'class' => 'Q100,Q101',
			'relation' => 'subclass'
		];
		$checkResult = $this->checker->checkConstraint( $this->typeStatement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testTypeConstraintInstanceInvalid() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
		$constraintParameters = [
			'class' => 'Q200,Q201',
			'relation' => 'instance'
		];
		$checkResult = $this->checker->checkConstraint( $this->typeStatement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testTypeConstraintInstanceInvalidWithIndirection() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q2' ) );
		$constraintParameters = [
			'class' => 'Q200,Q201',
			'relation' => 'instance'
		];
		$checkResult = $this->checker->checkConstraint( $this->typeStatement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testTypeConstraintInstanceInvalidWithMoreIndirection() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q3' ) );
		$constraintParameters = [
			'class' => 'Q200,Q201',
			'relation' => 'instance'
		];
		$checkResult = $this->checker->checkConstraint( $this->typeStatement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testTypeConstraintSubclassInvalid() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q4' ) );
		$constraintParameters = [
			'class' => 'Q200,Q201',
			'relation' => 'subclass'
		];
		$checkResult = $this->checker->checkConstraint( $this->typeStatement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testTypeConstraintSubclassInvalidWithIndirection() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q5' ) );
		$constraintParameters = [
			'class' => 'Q200,Q201' ,
			'relation' => 'subclass'
		];
		$checkResult = $this->checker->checkConstraint( $this->typeStatement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testTypeConstraintSubclassInvalidWithMoreIndirection() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q6' ) );
		$constraintParameters = [
			'class' => 'Q200,Q201',
			'relation' => 'subclass'
		];
		$checkResult = $this->checker->checkConstraint( $this->typeStatement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testTypeConstraintMissingRelation() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
		$constraintParameters = [
			'class' => 'Q100,Q101'
		];
		$checkResult = $this->checker->checkConstraint( $this->typeStatement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testTypeConstraintMissingClass() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
		$constraintParameters = [
			'relation' => 'subclass'
		];
		$checkResult = $this->checker->checkConstraint( $this->typeStatement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testTypeConstraintSubclassCycle() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q7' ) );
		$constraintParameters = [
			'class' => 'Q100,Q101',
			'relation' => 'instance'
		];
		$checkResult = $this->checker->checkConstraint( $this->typeStatement, $this->getConstraintMock( $constraintParameters ), $entity );
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
			 ->will( $this->returnValue( 'Type' ) );

		return $mock;
	}

}
