<?php

namespace WikibaseQuality\ConstraintReport\Tests\Checker\ConnectionChecker;

use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\Lookup\InMemoryEntityLookup;
use Wikibase\Repo\Tests\NewItem;
use Wikibase\Repo\Tests\NewStatement;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\SymmetricChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\MainSnakContext;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConnectionCheckerHelper;
use WikibaseQuality\ConstraintReport\Tests\ConstraintParameters;
use WikibaseQuality\ConstraintReport\Tests\ResultAssertions;

/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\SymmetricChecker
 *
 * @group WikibaseQualityConstraints
 *
 * @author BP2014N1
 * @license GPL-2.0-or-later
 */
class SymmetricCheckerTest extends \MediaWikiTestCase {

	use ConstraintParameters;
	use ResultAssertions;

	/**
	 * @var InMemoryEntityLookup
	 */
	private $lookup;

	/**
	 * @var ConnectionCheckerHelper
	 */
	private $connectionCheckerHelper;

	/**
	 * @var SymmetricChecker
	 */
	private $checker;

	protected function setUp() : void {
		parent::setUp();
		$this->lookup = new InMemoryEntityLookup();
		$this->connectionCheckerHelper = new ConnectionCheckerHelper();
		$this->checker = new SymmetricChecker(
			$this->lookup,
			$this->connectionCheckerHelper
		);
	}

	public function testSymmetricConstraintWithCorrectSpouse() {
		$entityId = new ItemId( 'Q1' );
		$otherEntityId = new ItemId( 'Q3' );
		$otherEntity = NewItem::withId( $otherEntityId )
			->andStatement(
				NewStatement::forProperty( 'P188' )
					->withValue( $entityId )
			)
			->build();
		$this->lookup->addEntity( $otherEntity );
		$statement = NewStatement::forProperty( 'P188' )
			->withValue( $otherEntityId )
			->build();
		$entity = NewItem::withId( $entityId )
			->andStatement( $statement )
			->build();

		$constraint = $this->getConstraintMock();

		$checkResult = $this->checker->checkConstraint( new MainSnakContext( $entity, $statement ), $constraint );

		$this->assertCompliance( $checkResult );
	}

	public function testSymmetricConstraintOnProperty() {
		$entityId = new PropertyId( 'P1' );
		$otherEntityId = new PropertyId( 'P2' );
		$otherEntity = new Property( $otherEntityId, null, 'wikibase-property' );
		$otherEntity->getStatements()->addStatement(
			NewStatement::forProperty( 'P3' )
				->withValue( $entityId )
				->build()
		);
		$this->lookup->addEntity( $otherEntity );
		$statement = NewStatement::forProperty( 'P3' )
			->withValue( $otherEntityId )
			->build();
		$entity = new Property( $entityId, null, 'wikibase-property' );
		$entity->getStatements()->addStatement(
			$statement
		);

		$constraint = $this->getConstraintMock();

		$checkResult = $this->checker->checkConstraint( new MainSnakContext( $entity, $statement ), $constraint );

		$this->assertCompliance( $checkResult );
	}

	public function testSymmetricConstraintWithWrongSpouse() {
		$entityId = new ItemId( 'Q1' );
		$otherEntityId = new ItemId( 'Q3' );
		$otherEntity = NewItem::withId( $otherEntityId )
			->andStatement(
				NewStatement::forProperty( 'P188' )
					->withValue( new ItemId( 'Q42' ) ) // should be $entityId
			)
			->build();
		$this->lookup->addEntity( $otherEntity );
		$statement = NewStatement::forProperty( 'P188' )
			->withValue( $otherEntityId )
			->build();
		$entity = NewItem::withId( $entityId )
			->andStatement( $statement )
			->build();

		$constraint = $this->getConstraintMock();

		$checkResult = $this->checker->checkConstraint( new MainSnakContext( $entity, $statement ), $constraint );

		$this->assertViolation( $checkResult, 'wbqc-violation-message-symmetric' );
	}

	public function testSymmetricConstraintWithWrongDataValue() {
		$entityId = new ItemId( 'Q1' );
		$otherEntityId = new ItemId( 'Q3' );
		$otherEntity = NewItem::withId( $otherEntityId )
			->andStatement(
				NewStatement::forProperty( 'P1' )
					->withValue( $entityId )
			)
			->build();
		$this->lookup->addEntity( $otherEntity );
		$statement = NewStatement::forProperty( 'P188' )
			->withValue( $otherEntityId->getSerialization() ) // should be $otherEntityId
			->build();
		$entity = NewItem::withId( $entityId )
			->andStatement( $statement )
			->build();

		$constraint = $this->getConstraintMock();

		$checkResult = $this->checker->checkConstraint( new MainSnakContext( $entity, $statement ), $constraint );

		$this->assertViolation( $checkResult, 'wbqc-violation-message-value-needed-of-type' );
	}

	public function testSymmetricConstraintWithNonExistentEntity() {
		$entityId = new ItemId( 'Q1' );
		$statement = NewStatement::forProperty( 'P188' )
			->withValue( new ItemId( 'Q100' ) )
			->build();
		$entity = NewItem::withId( $entityId )
			->andStatement( $statement )
			->build();

		$constraint = $this->getConstraintMock();

		$checkResult = $this->checker->checkConstraint( new MainSnakContext( $entity, $statement ), $constraint );

		$this->assertViolation( $checkResult, 'wbqc-violation-message-target-entity-must-exist' );
	}

	public function testSymmetricConstraintNoValueSnak() {
		$entityId = new ItemId( 'Q1' );
		$statement = NewStatement::noValueFor( 'P1' )
			->build();
		$entity = NewItem::withId( $entityId )
			->andStatement( $statement )
			->build();

		$constraint = $this->getConstraintMock();

		$checkResult = $this->checker->checkConstraint( new MainSnakContext( $entity, $statement ), $constraint );

		$this->assertCompliance( $checkResult );
	}

	public function testSymmetricConstraintDeprecatedStatement() {
		$statement = NewStatement::noValueFor( 'P1' )
				   ->withDeprecatedRank()
				   ->build();
		$constraint = $this->getConstraintMock( [] );
		$entity = NewItem::withId( 'Q1' )
				->build();

		$checkResult = $this->checker->checkConstraint( new MainSnakContext( $entity, $statement ), $constraint );

		$this->assertDeprecation( $checkResult );
	}

	public function testSymmetricConstraintDependedEntityIds() {
		$entityId = new ItemId( 'Q1' );
		$otherEntityId = new ItemId( 'Q7' );
		$otherEntity = NewItem::withId( $otherEntityId )
			->andStatement(
				NewStatement::forProperty( 'P1' )
					->withValue( $entityId )
			)
			->build();
		$this->lookup->addEntity( $otherEntity );
		$statement = NewStatement::forProperty( 'P188' )
			->withValue( $otherEntityId )
			->build();
		$entity = NewItem::withId( $entityId )
			->andStatement( $statement )
			->build();

		$constraint = $this->getConstraintMock();

		$checkResult = $this->checker->checkConstraint( new MainSnakContext( $entity, $statement ), $constraint );

		$dependencyMetadata = $checkResult->getMetadata()->getDependencyMetadata();
		$this->assertSame( [ $otherEntityId ], $dependencyMetadata->getEntityIds() );
	}

	public function testCheckConstraintParameters() {
		$constraint = $this->getConstraintMock( [] );

		$result = $this->checker->checkConstraintParameters( $constraint );

		$this->assertEmpty( $result );
	}

	/**
	 * @return Constraint
	 */
	private function getConstraintMock() {
		$mock = $this
			->getMockBuilder( Constraint::class )
			->disableOriginalConstructor()
			->getMock();
		$mock->expects( $this->any() )
			 ->method( 'getConstraintParameters' )
			 ->will( $this->returnValue( [] ) );
		$mock->expects( $this->any() )
			 ->method( 'getConstraintTypeItemId' )
			 ->will( $this->returnValue( 'Q21510862' ) );

		return $mock;
	}

}
