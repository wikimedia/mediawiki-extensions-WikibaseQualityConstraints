<?php

namespace WikibaseQuality\ConstraintReport\Test\TypeChecker;

use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\TypeChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\TypeCheckerHelper;
use WikibaseQuality\ConstraintReport\Tests\ConstraintParameters;
use WikibaseQuality\ConstraintReport\Tests\ResultAssertions;
use WikibaseQuality\Tests\Helper\JsonFileEntityLookup;
use Wikibase\Repo\WikibaseRepo;

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

	use ConstraintParameters, ResultAssertions;

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
			$this->getConstraintParameterParser(),
			new TypeCheckerHelper(
				$this->lookup,
				$this->getDefaultConfig(),
				$this->getConstraintParameterRenderer()
			),
			$this->getDefaultConfig()
		);
		$this->typeStatement = new Statement( new PropertyValueSnak( new PropertyId( 'P1' ), new EntityIdValue( new ItemId( 'Q42' ) ) ) );
	}

	public function testTypeConstraintInstanceValid() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
		$constraintParameters = [
			'class' => 'Q100,Q101',
			'relation' => 'instance'
		];
		$checkResult = $this->checker->checkConstraint( $this->typeStatement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertCompliance( $checkResult );
	}

	public function testTypeConstraintInstanceValidWithIndirection() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q2' ) );
		$constraintParameters = [
			'class' => 'Q100,Q101',
			'relation' => 'instance'
		];
		$checkResult = $this->checker->checkConstraint( $this->typeStatement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertCompliance( $checkResult );
	}

	public function testTypeConstraintInstanceValidWithMoreIndirection() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q3' ) );
		$constraintParameters = [
			'class' => 'Q100,Q101',
			'relation' => 'instance'
		];
		$checkResult = $this->checker->checkConstraint( $this->typeStatement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertCompliance( $checkResult );
	}

	public function testTypeConstraintInstanceValidWithStatement() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
		$snakSerializer = WikibaseRepo::getDefaultInstance()->getBaseDataModelSerializerFactory()->newSnakSerializer();
		$classId = $this->getDefaultConfig()->get( 'WBQualityConstraintsClassId' );
		$relationId = $this->getDefaultConfig()->get( 'WBQualityConstraintsRelationId' );
		$constraintParameters = [
			$classId => [
				$snakSerializer->serialize( new PropertyValueSnak( new PropertyId( $classId ), new EntityIdValue( new ItemId( 'Q100' ) ) ) ),
				$snakSerializer->serialize( new PropertyValueSnak( new PropertyId( $classId ), new EntityIdValue( new ItemId( 'Q101' ) ) ) )
			],
			$relationId => [
				$snakSerializer->serialize( new PropertyValueSnak(
					new PropertyId( $relationId ),
					new EntityIdValue( new ItemId( $this->getDefaultConfig()->get( 'WBQualityConstraintsInstanceOfRelationId' ) ) )
				) )
			]
		];
		$checkResult = $this->checker->checkConstraint( $this->typeStatement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertCompliance( $checkResult );
	}

	public function testTypeConstraintSubclassValid() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q4' ) );
		$constraintParameters = [
			'class' => 'Q100,Q101',
			'relation' => 'subclass'
		];
		$checkResult = $this->checker->checkConstraint( $this->typeStatement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertCompliance( $checkResult );
	}

	public function testTypeConstraintSubclassValidWithIndirection() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q5' ) );
		$constraintParameters = [
			'class' => 'Q100,Q101',
			'relation' => 'subclass'
		];
		$checkResult = $this->checker->checkConstraint( $this->typeStatement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertCompliance( $checkResult );
	}

	public function testTypeConstraintSubclassValidWithMoreIndirection() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q6' ) );
		$constraintParameters = [
			'class' => 'Q100,Q101',
			'relation' => 'subclass'
		];
		$checkResult = $this->checker->checkConstraint( $this->typeStatement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertCompliance( $checkResult );
	}

	public function testTypeConstraintSubclassValidWithStatement() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q4' ) );
		$snakSerializer = WikibaseRepo::getDefaultInstance()->getBaseDataModelSerializerFactory()->newSnakSerializer();
		$classId = $this->getDefaultConfig()->get( 'WBQualityConstraintsClassId' );
		$relationId = $this->getDefaultConfig()->get( 'WBQualityConstraintsRelationId' );
		$constraintParameters = [
			$classId => [
				$snakSerializer->serialize( new PropertyValueSnak( new PropertyId( $classId ), new EntityIdValue( new ItemId( 'Q100' ) ) ) ),
				$snakSerializer->serialize( new PropertyValueSnak( new PropertyId( $classId ), new EntityIdValue( new ItemId( 'Q101' ) ) ) )
			],
			$relationId => [
				$snakSerializer->serialize( new PropertyValueSnak(
					new PropertyId( $relationId ),
					new EntityIdValue( new ItemId( $this->getDefaultConfig()->get( 'WBQualityConstraintsSubclassOfRelationId' ) ) )
				) )
			]
		];
		$checkResult = $this->checker->checkConstraint( $this->typeStatement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertCompliance( $checkResult );
	}

	public function testTypeConstraintInstanceInvalid() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
		$constraintParameters = [
			'class' => 'Q200,Q201',
			'relation' => 'instance'
		];
		$checkResult = $this->checker->checkConstraint( $this->typeStatement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertViolation( $checkResult, 'wbqc-violation-message-type-instance' );
	}

	public function testTypeConstraintInstanceInvalidWithIndirection() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q2' ) );
		$constraintParameters = [
			'class' => 'Q200,Q201',
			'relation' => 'instance'
		];
		$checkResult = $this->checker->checkConstraint( $this->typeStatement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertViolation( $checkResult, 'wbqc-violation-message-type-instance' );
	}

	public function testTypeConstraintInstanceInvalidWithMoreIndirection() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q3' ) );
		$constraintParameters = [
			'class' => 'Q200,Q201',
			'relation' => 'instance'
		];
		$checkResult = $this->checker->checkConstraint( $this->typeStatement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertViolation( $checkResult, 'wbqc-violation-message-type-instance' );
	}

	public function testTypeConstraintSubclassInvalid() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q4' ) );
		$constraintParameters = [
			'class' => 'Q200,Q201',
			'relation' => 'subclass'
		];
		$checkResult = $this->checker->checkConstraint( $this->typeStatement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertViolation( $checkResult, 'wbqc-violation-message-type-subclass' );
	}

	public function testTypeConstraintSubclassInvalidWithIndirection() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q5' ) );
		$constraintParameters = [
			'class' => 'Q200,Q201' ,
			'relation' => 'subclass'
		];
		$checkResult = $this->checker->checkConstraint( $this->typeStatement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertViolation( $checkResult, 'wbqc-violation-message-type-subclass' );
	}

	public function testTypeConstraintSubclassInvalidWithMoreIndirection() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q6' ) );
		$constraintParameters = [
			'class' => 'Q200,Q201',
			'relation' => 'subclass'
		];
		$checkResult = $this->checker->checkConstraint( $this->typeStatement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertViolation( $checkResult, 'wbqc-violation-message-type-subclass' );
	}

	public function testTypeConstraintSubclassCycle() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q7' ) );
		$constraintParameters = [
			'class' => 'Q100,Q101',
			'relation' => 'instance'
		];
		$checkResult = $this->checker->checkConstraint( $this->typeStatement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertViolation( $checkResult, 'wbqc-violation-message-type-instance' );
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
		$mock->expects( $this->any() )
			 ->method( 'getConstraintTypeName' )
			 ->will( $this->returnValue( 'Type' ) );

		return $mock;
	}

}
