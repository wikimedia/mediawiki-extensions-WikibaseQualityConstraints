<?php

namespace WikibaseQuality\ConstraintReport\Test\ConnectionChecker;

use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\Statement;
use DataValues\StringValue;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\TargetRequiredClaimChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\MainSnakContext;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConnectionCheckerHelper;
use WikibaseQuality\ConstraintReport\Tests\ConstraintParameters;
use WikibaseQuality\ConstraintReport\Tests\ResultAssertions;
use WikibaseQuality\Tests\Helper\JsonFileEntityLookup;
use Wikibase\Repo\Tests\NewItem;
use Wikibase\Repo\Tests\NewStatement;

/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\TargetRequiredClaimChecker
 *
 * @group WikibaseQualityConstraints
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

	public function testTargetRequiredClaimConstraintValid() {
		$value = new EntityIdValue( new ItemId( 'Q5' ) );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) );
		$constraintParameters = array_merge(
			$this->propertyParameter( 'P2' ),
			$this->itemsParameter( [ 'Q42' ] )
		);

		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new MainSnakContext( $this->getEntity(), $statement ), $constraint );

		$this->assertCompliance( $checkResult );
	}

	public function testTargetRequiredClaimConstraintWrongItem() {
		$value = new EntityIdValue( new ItemId( 'Q5' ) );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) );
		$constraintParameters = array_merge(
			$this->propertyParameter( 'P2' ),
			$this->itemsParameter( [ 'Q2' ] )
		);

		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new MainSnakContext( $this->getEntity(), $statement ), $constraint );

		$this->assertViolation( $checkResult, 'wbqc-violation-message-target-required-claim' );
	}

	public function testTargetRequiredClaimConstraintOnlyProperty() {
		$value = new EntityIdValue( new ItemId( 'Q5' ) );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) );
		$constraintParameters = $this->propertyParameter( 'P2' );

		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new MainSnakContext( $this->getEntity(), $statement ), $constraint );

		$this->assertCompliance( $checkResult );
	}

	public function testTargetRequiredClaimConstraintOnlyPropertyButDoesNotExist() {
		$value = new EntityIdValue( new ItemId( 'Q5' ) );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) );
		$constraintParameters = $this->propertyParameter( 'P3' );

		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new MainSnakContext( $this->getEntity(), $statement ), $constraint );

		$this->assertViolation( $checkResult, 'wbqc-violation-message-target-required-claim' );
	}

	public function testTargetRequiredClaimConstraintWrongDataTypeForItem() {
		$value = new StringValue( 'Q5' );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) );
		$constraintParameters = $this->propertyParameter( 'P2' );

		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new MainSnakContext( $this->getEntity(), $statement ), $constraint );

		$this->assertViolation( $checkResult, 'wbqc-violation-message-value-needed-of-type' );
	}

	public function testTargetRequiredClaimConstraintItemDoesNotExist() {
		$value = new EntityIdValue( new ItemId( 'Q100' ) );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) );
		$constraintParameters = $this->propertyParameter( 'P2' );

		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new MainSnakContext( $this->getEntity(), $statement ), $constraint );

		$this->assertViolation( $checkResult, 'wbqc-violation-message-target-entity-must-exist' );
	}

	public function testTargetRequiredClaimConstraintNoValueSnak() {
		$statement = NewStatement::noValueFor( 'P1' )->build();
		$constraintParameters = $this->propertyParameter( 'P2' );

		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new MainSnakContext( $this->getEntity(), $statement ), $constraint );

		$this->assertCompliance( $checkResult );
	}

	public function testTargetRequiredClaimConstraintDeprecatedStatement() {
		$statement = NewStatement::noValueFor( 'P1' )
				   ->withDeprecatedRank()
				   ->build();
		$constraint = $this->getConstraintMock( [] );
		$entity = NewItem::withId( 'Q1' )
				->build();

		$checkResult = $this->checker->checkConstraint( new MainSnakContext( $entity, $statement ), $constraint );

		$this->assertDeprecation( $checkResult );
	}

	public function testTargetRequiredClaimConstraintDependedEntityIds() {
		$targetEntityId = new ItemId( 'Q5' );
		$value = new EntityIdValue( $targetEntityId );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) );
		$constraintParameters = array_merge(
			$this->propertyParameter( 'P2' ),
			$this->itemsParameter( [ 'Q42' ] )
		);

		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new MainSnakContext( $this->getEntity(), $statement ), $constraint );

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
