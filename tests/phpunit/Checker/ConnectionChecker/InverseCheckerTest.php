<?php

namespace WikibaseQuality\ConstraintReport\Test\ConnectionChecker;

use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use DataValues\StringValue;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\Repo\WikibaseRepo;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\InverseChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConnectionCheckerHelper;
use WikibaseQuality\ConstraintReport\Tests\ConstraintParameters;
use WikibaseQuality\ConstraintReport\Tests\ResultAssertions;
use WikibaseQuality\Tests\Helper\JsonFileEntityLookup;

/**
 * @covers \WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\InverseChecker
 *
 * @group WikibaseQualityConstraints
 *
 * @uses   \WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 * @uses   \WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class InverseCheckerTest extends \MediaWikiTestCase {

	use ConstraintParameters, ResultAssertions;

	/**
	 * @var JsonFileEntityLookup
	 */
	private $lookup;

	/**
	 * @var ConnectionCheckerHelper
	 */
	private $connectionCheckerHelper;

	/**
	 * @var InverseChecker
	 */
	private $checker;

	protected function setUp() {
		parent::setUp();
		$this->lookup = new JsonFileEntityLookup( __DIR__ );
		$this->connectionCheckerHelper = new ConnectionCheckerHelper();
		$this->checker = new InverseChecker(
			$this->lookup,
			$this->getConstraintParameterParser(),
			$this->connectionCheckerHelper,
			$this->getConstraintParameterRenderer()
		);
	}

	public function testInverseConstraintValid() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );

		$value = new EntityIdValue( new ItemId( 'Q7' ) );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) );

		$constraintParameters = [
			'property' => 'P1'
		];
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertCompliance( $checkResult );
	}

	public function testInverseConstraintValidWithStatement() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );

		$value = new EntityIdValue( new ItemId( 'Q7' ) );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) );

		$snakSerializer = WikibaseRepo::getDefaultInstance()->getBaseDataModelSerializerFactory()->newSnakSerializer();
		$propertyId = $this->getDefaultConfig()->get( 'WBQualityConstraintsPropertyId' );
		$constraintParameters = [
			$propertyId => [ $snakSerializer->serialize( new PropertyValueSnak( new PropertyId( $propertyId ), new EntityIdValue( new PropertyId( 'P1' ) ) ) ) ]
		];
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertCompliance( $checkResult );
	}

	public function testInverseConstraintWrongItem() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );

		$value = new EntityIdValue( new ItemId( 'Q8' ) );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) );

		$constraintParameters = [
			'property' => 'P1'
		];
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertViolation( $checkResult, 'wbqc-violation-message-inverse' );
	}

	public function testInverseConstraintWrongDataTypeForItem() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );

		$value = new StringValue( 'Q7' );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) );

		$constraintParameters = [
			'property' => 'P1'
		];
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertViolation( $checkResult, 'wbqc-violation-message-value-needed-of-type' );
	}

	public function testInverseConstraintItemDoesNotExist() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );

		$value = new EntityIdValue( new ItemId( 'Q100' ) );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) );

		$constraintParameters = [
			'property' => 'P1'
		];
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertViolation( $checkResult, 'wbqc-violation-message-target-entity-must-exist' );
	}

	public function testInverseConstraintNoValueSnak() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );

		$statement = new Statement( new PropertyNoValueSnak( 1 ) );

		$constraintParameters = [
			'property' => 'P1'
		];
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertViolation( $checkResult, 'wbqc-violation-message-value-needed' );
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
			 ->method( 'getConstraintTypeItemId' )
			 ->will( $this->returnValue( 'Inverse' ) );

		return $mock;
	}

}
