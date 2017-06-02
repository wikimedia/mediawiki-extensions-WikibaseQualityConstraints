<?php

namespace WikibaseQuality\ConstraintReport\Test\ConnectionChecker;

use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Snak\PropertySomeValueSnak;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\Statement;
use DataValues\StringValue;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\TargetRequiredClaimChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConnectionCheckerHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;
use WikibaseQuality\ConstraintReport\Tests\ConstraintParameters;
use WikibaseQuality\ConstraintReport\Tests\ResultAssertions;
use WikibaseQuality\Tests\Helper\JsonFileEntityLookup;
use Wikibase\Repo\WikibaseRepo;

/**
 * @covers \WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\TargetRequiredClaimChecker
 *
 * @group WikibaseQualityConstraints
 *
 * @uses   \WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 * @uses   \WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class TargetRequiredClaimCheckerTest extends \MediaWikiTestCase {

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
	 * @var TargetRequiredClaimChecker
	 */
	private $checker;

	protected function setUp() {
		parent::setUp();
		$this->lookup = new JsonFileEntityLookup( __DIR__ );
		$this->connectionCheckerHelper = new ConnectionCheckerHelper();
		$this->checker = new TargetRequiredClaimChecker(
			$this->lookup,
			$this->getConstraintParameterParser(),
			$this->connectionCheckerHelper,
			$this->getConstraintParameterRenderer()
		);
	}

	protected function tearDown() {
		unset( $this->lookup );
		unset( $this->connectionCheckerHelper );
		unset( $this->checker );
		parent::tearDown();
	}

	public function testTargetRequiredClaimConstraintValid() {
		$value = new EntityIdValue( new ItemId( 'Q5' ) );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) );

		$constraintParameters = [
			'property' => 'P2',
			'item' => 'Q42'
		];
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $this->getEntity() );
		$this->assertCompliance( $checkResult );
	}

	public function testTargetRequiredClaimConstraintWrongItem() {
		$value = new EntityIdValue( new ItemId( 'Q5' ) );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) );

		$constraintParameters = [
			'property' => 'P2',
			'item' => 'Q2'
		];
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $this->getEntity() );
		$this->assertViolation( $checkResult, 'wbqc-violation-message-target-required-claim' );
	}

	public function testTargetRequiredClaimConstraintOnlyProperty() {
		$value = new EntityIdValue( new ItemId( 'Q5' ) );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) );

		$constraintParameters = [
			'property' => 'P2'
		];
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $this->getEntity() );
		$this->assertCompliance( $checkResult );
	}

	public function testTargetRequiredClaimConstraintOnlyPropertyButDoesNotExist() {
		$value = new EntityIdValue( new ItemId( 'Q5' ) );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) );

		$constraintParameters = [
			'property' => 'P3'
		];
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $this->getEntity() );
		$this->assertViolation( $checkResult, 'wbqc-violation-message-target-required-claim' );
	}

	public function testTargetRequiredClaimConstraintWrongDataTypeForItem() {
		$value = new StringValue( 'Q5' );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) );

		$constraintParameters = [
			'property' => 'P2'
		];
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $this->getEntity() );
		$this->assertViolation( $checkResult, 'wbqc-violation-message-value-needed-of-type' );
	}

	public function testTargetRequiredClaimConstraintItemDoesNotExist() {
		$value = new EntityIdValue( new ItemId( 'Q100' ) );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) );

		$constraintParameters = [
			'property' => 'P2'
		];
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $this->getEntity() );
		$this->assertViolation( $checkResult, 'wbqc-violation-message-target-entity-must-exist' );
	}

	public function testTargetRequiredClaimConstraintNoValueSnak() {
		$statement = new Statement( new PropertyNoValueSnak( 1 ) );

		$constraintParameters = [
			'property' => 'P2'
		];
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $this->getEntity() );
		$this->assertViolation( $checkResult, 'wbqc-violation-message-value-needed' );
	}

	public function testTargetRequiredClaimConstraintWithStatement() {
		$value = new EntityIdValue( new ItemId( 'Q5' ) );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) );

		$snakSerializer = WikibaseRepo::getDefaultInstance()->getSerializerFactory()->newSnakSerializer();
		$config = $this->getDefaultConfig();
		$propertyId = $config->get( 'WBQualityConstraintsPropertyId' );
		$qualifierId = $config->get( 'WBQualityConstraintsQualifierOfPropertyConstraintId' );
		$constraintParameters = [
			$propertyId => [ $snakSerializer->serialize( new PropertyValueSnak( new PropertyId( $propertyId ), new EntityIdValue( new PropertyId( 'P2' ) ) ) ) ],
			$qualifierId => [ $snakSerializer->serialize( new PropertyValueSnak( new PropertyId( $qualifierId ), new EntityIdValue( new ItemId( 'Q42' ) ) ) ) ]
		];

		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $this->getEntity() );
		$this->assertCompliance( $checkResult );
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
			 ->will( $this->returnValue( 'Target required claim' ) );
		$mock->expects( $this->any() )
			 ->method( 'getConstraintTypeName' )
			 ->will( $this->returnValue( 'Target required claim' ) );

		return $mock;
	}

	/**
	 * @return EntityDocument
	 */
	private function getEntity() {
		return new Item( new ItemId( 'Q1' ) );
	}

}
