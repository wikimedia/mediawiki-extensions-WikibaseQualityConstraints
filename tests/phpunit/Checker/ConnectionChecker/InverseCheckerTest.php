<?php

namespace WikibaseQuality\ConstraintReport\Tests\Checker\ConnectionChecker;

use DataValues\StringValue;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\Repo\Tests\NewItem;
use Wikibase\Repo\Tests\NewStatement;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\InverseChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\MainSnakContext;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConnectionCheckerHelper;
use WikibaseQuality\ConstraintReport\Tests\ConstraintParameters;
use WikibaseQuality\ConstraintReport\Tests\Helper\JsonFileEntityLookup;
use WikibaseQuality\ConstraintReport\Tests\ResultAssertions;

/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\InverseChecker
 *
 * @group WikibaseQualityConstraints
 *
 * @author BP2014N1
 * @license GPL-2.0-or-later
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
			$this->connectionCheckerHelper
		);
	}

	public function testInverseConstraintValid() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );

		$value = new EntityIdValue( new ItemId( 'Q7' ) );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) );

		$constraintParameters = $this->propertyParameter( 'P1' );

		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new MainSnakContext( $entity, $statement ), $constraint );

		$this->assertCompliance( $checkResult );
	}

	public function testInverseConstraintOnProperty() {
		$entity = new Property( new PropertyId( 'P1' ), null, 'wikibase-property' );
		$value = new EntityIdValue( new PropertyId( 'P2' ) );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P3' ), $value ) );
		$constraintParameters = $this->propertyParameter( 'P3' );

		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new MainSnakContext( $entity, $statement ), $constraint );

		$this->assertCompliance( $checkResult );
	}

	public function testInverseConstraintWrongItem() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );

		$value = new EntityIdValue( new ItemId( 'Q8' ) );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) );

		$constraintParameters = $this->propertyParameter( 'P1' );

		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new MainSnakContext( $entity, $statement ), $constraint );

		$this->assertViolation( $checkResult, 'wbqc-violation-message-inverse' );
	}

	public function testInverseConstraintWrongDataTypeForItem() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );

		$value = new StringValue( 'Q7' );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) );

		$constraintParameters = $this->propertyParameter( 'P1' );

		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new MainSnakContext( $entity, $statement ), $constraint );

		$this->assertViolation( $checkResult, 'wbqc-violation-message-value-needed-of-type' );
	}

	public function testInverseConstraintItemDoesNotExist() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );

		$value = new EntityIdValue( new ItemId( 'Q100' ) );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) );

		$constraintParameters = $this->propertyParameter( 'P1' );

		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new MainSnakContext( $entity, $statement ), $constraint );

		$this->assertViolation( $checkResult, 'wbqc-violation-message-target-entity-must-exist' );
	}

	public function testInverseConstraintNoValueSnak() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );

		$statement = NewStatement::noValueFor( 'P1' )->build();

		$constraintParameters = $this->propertyParameter( 'P1' );

		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new MainSnakContext( $entity, $statement ), $constraint );

		$this->assertCompliance( $checkResult );
	}

	public function testInverseConstraintDeprecatedStatement() {
		$statement = NewStatement::noValueFor( 'P1' )
				   ->withDeprecatedRank()
				   ->build();
		$constraint = $this->getConstraintMock( [] );
		$entity = NewItem::withId( 'Q1' )
				->build();

		$checkResult = $this->checker->checkConstraint( new MainSnakContext( $entity, $statement ), $constraint );

		$this->assertDeprecation( $checkResult );
	}

	public function testInverseConstraintDependedEntityIds() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );

		$targetEntityId = new ItemId( 'Q7' );
		$value = new EntityIdValue( $targetEntityId );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) );

		$constraintParameters = $this->propertyParameter( 'P1' );

		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new MainSnakContext( $entity, $statement ), $constraint );

		$dependencyMetadata = $checkResult->getMetadata()->getDependencyMetadata();
		$this->assertSame( [ $targetEntityId ], $dependencyMetadata->getEntityIds() );
	}

	public function testCheckConstraintParameters() {
		$constraint = $this->getConstraintMock( [] );

		$result = $this->checker->checkConstraintParameters( $constraint );

		$this->assertCount( 1, $result );
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
			 ->will( $this->returnValue( 'Q21510855' ) );

		return $mock;
	}

}
