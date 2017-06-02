<?php

namespace WikibaseQuality\ConstraintReport\Test\ConnectionChecker;

use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Snak\PropertySomeValueSnak;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\ConflictsWithChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConnectionCheckerHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;
use WikibaseQuality\ConstraintReport\Tests\ConstraintParameters;
use WikibaseQuality\ConstraintReport\Tests\ResultAssertions;
use WikibaseQuality\Tests\Helper\JsonFileEntityLookup;
use Wikibase\Repo\WikibaseRepo;

/**
 * @covers \WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\ConflictsWithChecker
 *
 * @group WikibaseQualityConstraints
 *
 * @uses   \WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 * @uses   \WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\constraintParameterParser
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class ConflictsWithCheckerTest extends \MediaWikiTestCase {

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
	 * @var ConflictsWithChecker
	 */
	private $checker;

	protected function setUp() {
		parent::setUp();
		$this->lookup = new JsonFileEntityLookup( __DIR__ );
		$this->connectionCheckerHelper = new ConnectionCheckerHelper();
		$this->checker = new ConflictsWithChecker(
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

	public function testConflictsWithConstraintValid() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q4' ) );

		$value = new EntityIdValue( new ItemId( 'Q100' ) );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) );

		$constraintParameters = [
			'property' => 'P2'
		];

		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertCompliance( $checkResult );
	}

	public function testConflictsWithConstraintProperty() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q5' ) );

		$value = new EntityIdValue( new ItemId( 'Q100' ) );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) );

		$constraintParameters = [
			'property' => 'P2'
		];

		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertViolation( $checkResult, 'wbqc-violation-message-conflicts-with-property' );
	}

	public function testConflictsWithConstraintPropertyButNotItem() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q5' ) );

		$value = new EntityIdValue( new ItemId( 'Q100' ) );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) );

		$constraintParameters = [
			'item' => 'Q1',
			'property' => 'P2'
		];

		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertCompliance( $checkResult );
	}

	public function testConflictsWithConstraintPropertyAndItem() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q5' ) );

		$value = new EntityIdValue( new ItemId( 'Q100' ) );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) );

		$constraintParameters = [
			'item' => 'Q42',
			'property' => 'P2'
		];

		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertViolation( $checkResult, 'wbqc-violation-message-conflicts-with-claim' );
	}

	public function testConflictsWithConstraintPropertyAndNoValue() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q6' ) );

		$value = new EntityIdValue( new ItemId( 'Q100' ) );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) );

		$constraintParameters = [
			'item' => 'Q42',
			'property' => 'P2'
		];

		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertCompliance( $checkResult );
	}

	public function testConflictsWithConstraintWithStatement() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q5' ) );

		$value = new EntityIdValue( new ItemId( 'Q100' ) );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) );

		$snakSerializer = WikibaseRepo::getDefaultInstance()->getSerializerFactory()->newSnakSerializer();
		$config = $this->getDefaultConfig();
		$propertyId = $config->get( 'WBQualityConstraintsPropertyId' );
		$qualifierId = $config->get( 'WBQualityConstraintsQualifierOfPropertyConstraintId' );
		$constraintParameters = [
			$propertyId => [ $snakSerializer->serialize( new PropertyValueSnak( new PropertyId( $propertyId ), new EntityIdValue( new PropertyId( 'P2' ) ) ) ) ],
			$qualifierId => [ $snakSerializer->serialize( new PropertyValueSnak( new PropertyId( $qualifierId ), new EntityIdValue( new ItemId( 'Q42' ) ) ) ) ]
		];

		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertViolation( $checkResult, 'wbqc-violation-message-conflicts-with-claim' );
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
			 ->will( $this->returnValue( 'Conflicts with' ) );

		return $mock;
	}

}
