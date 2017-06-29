<?php

namespace WikibaseQuality\ConstraintReport\Test\ConnectionChecker;

use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\ItemChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConnectionCheckerHelper;
use WikibaseQuality\ConstraintReport\Tests\ConstraintParameters;
use WikibaseQuality\ConstraintReport\Tests\ResultAssertions;
use WikibaseQuality\Tests\Helper\JsonFileEntityLookup;
use Wikibase\Repo\Tests\NewItem;
use Wikibase\Repo\WikibaseRepo;

/**
 * @covers \WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\ItemChecker
 *
 * @group WikibaseQualityConstraints
 *
 * @uses   \WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 * @uses   \WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class ItemCheckerTest extends \MediaWikiTestCase {

	use ConstraintParameters, ResultAssertions;

	/**
	 * @var ItemChecker
	 */
	private $checker;

	protected function setUp() {
		parent::setUp();
		$this->checker = new ItemChecker(
			new JsonFileEntityLookup( __DIR__ ),
			$this->getConstraintParameterParser(),
			new ConnectionCheckerHelper(),
			$this->getConstraintParameterRenderer()
		);
	}

	public function testItemConstraintInvalid() {
		$entity = NewItem::withId( 'Q4' )
			->build();
		$constraintParameters = [
			'property' => 'P2'
		];
		$value = new EntityIdValue( new ItemId( 'Q100' ) );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) );

		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertViolation( $checkResult, 'wbqc-violation-message-item' );
	}

	public function testItemConstraintProperty() {
		$entity = NewItem::withId( 'Q5' )
			->andPropertyValueSnak( 'P2', new ItemId( 'Q42' ) )
			->build();
		$constraintParameters = [
			'property' => 'P2'
		];

		$value = new EntityIdValue( new ItemId( 'Q100' ) );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) );

		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertCompliance( $checkResult );
	}

	public function testItemConstraintPropertyButNotItem() {
		$entity = NewItem::withId( 'Q5' )
			->andPropertyValueSnak( 'P2', new ItemId( 'Q42' ) )
			->build();
		$constraintParameters = [
			'property' => 'P2',
			'item' => 'Q1'
		];

		$value = new EntityIdValue( new ItemId( 'Q100' ) );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) );

		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertViolation( $checkResult, 'wbqc-violation-message-item' );
	}

	public function testItemConstraintPropertyAndItem() {
		$entity = NewItem::withId( 'Q5' )
			->andPropertyValueSnak( 'P2', new ItemId( 'Q42' ) )
			->build();
		$constraintParameters = [
			'property' => 'P2',
			'item' => 'Q42'
		];
		$value = new EntityIdValue( new ItemId( 'Q100' ) );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) );

		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertCompliance( $checkResult );
	}

	public function testItemConstraintPropertyAndItemWithStatement() {
		$entity = NewItem::withId( 'Q5' )
			->andPropertyValueSnak( 'P2', new ItemId( 'Q42' ) )
			->build();

		$snakSerializer = WikibaseRepo::getDefaultInstance()->getBaseDataModelSerializerFactory()->newSnakSerializer();
		$config = $this->getDefaultConfig();
		$propertyId = $config->get( 'WBQualityConstraintsPropertyId' );
		$qualifierId = $config->get( 'WBQualityConstraintsQualifierOfPropertyConstraintId' );
		$constraintParameters = [
			$propertyId => [ $snakSerializer->serialize( new PropertyValueSnak( new PropertyId( $propertyId ), new EntityIdValue( new PropertyId( 'P2' ) ) ) ) ],
			$qualifierId => [ $snakSerializer->serialize( new PropertyValueSnak( new PropertyId( $qualifierId ), new EntityIdValue( new ItemId( 'Q42' ) ) ) ) ]
		];

		$value = new EntityIdValue( new ItemId( 'Q100' ) );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) );

		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $entity );
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
			 ->method( 'getConstraintTypeItemId' )
			 ->will( $this->returnValue( 'Item' ) );

		return $mock;
	}

}
