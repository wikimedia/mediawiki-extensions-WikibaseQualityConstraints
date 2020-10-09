<?php

namespace WikibaseQuality\ConstraintReport\Tests\Checker\ConnectionChecker;

use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\Repo\Tests\NewItem;
use Wikibase\Repo\Tests\NewStatement;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\ItemChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\MainSnakContext;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConnectionCheckerHelper;
use WikibaseQuality\ConstraintReport\Tests\ConstraintParameters;
use WikibaseQuality\ConstraintReport\Tests\ResultAssertions;

/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\ItemChecker
 *
 * @group WikibaseQualityConstraints
 *
 * @author BP2014N1
 * @license GPL-2.0-or-later
 */
class ItemCheckerTest extends \MediaWikiTestCase {

	use ConstraintParameters;
	use ResultAssertions;

	/**
	 * @var ItemChecker
	 */
	private $checker;

	protected function setUp() : void {
		parent::setUp();
		$this->checker = new ItemChecker(
			$this->getConstraintParameterParser(),
			new ConnectionCheckerHelper()
		);
	}

	public function testItemConstraintInvalid() {
		$entity = NewItem::withId( 'Q4' )
			->build();
		$constraintParameters = $this->propertyParameter( 'P2' );

		$value = new EntityIdValue( new ItemId( 'Q100' ) );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) );

		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new MainSnakContext( $entity, $statement ), $constraint );

		$this->assertViolation( $checkResult, 'wbqc-violation-message-item' );
	}

	public function testItemConstraintProperty() {
		$entity = NewItem::withId( 'Q5' )
			->andStatement( NewStatement::forProperty( 'P2' )->withValue( new ItemId( 'Q42' ) ) )
			->build();
		$constraintParameters = $this->propertyParameter( 'P2' );

		$value = new EntityIdValue( new ItemId( 'Q100' ) );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) );

		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new MainSnakContext( $entity, $statement ), $constraint );

		$this->assertCompliance( $checkResult );
	}

	public function testItemConstraintPropertyButNotItem() {
		$entity = NewItem::withId( 'Q5' )
			->andStatement( NewStatement::forProperty( 'P2' )->withValue( new ItemId( 'Q42' ) ) )
			->build();
		$constraintParameters = array_merge(
			$this->propertyParameter( 'P2' ),
			$this->itemsParameter( [ 'Q1' ] )
		);

		$value = new EntityIdValue( new ItemId( 'Q100' ) );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) );

		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new MainSnakContext( $entity, $statement ), $constraint );

		$this->assertViolation( $checkResult, 'wbqc-violation-message-item' );
	}

	public function testItemConstraintPropertyAndItem() {
		$entity = NewItem::withId( 'Q5' )
			->andStatement( NewStatement::forProperty( 'P2' )->withValue( new ItemId( 'Q42' ) ) )
			->build();
		$constraintParameters = array_merge(
			$this->propertyParameter( 'P2' ),
			$this->itemsParameter( [ 'Q42' ] )
		);

		$value = new EntityIdValue( new ItemId( 'Q100' ) );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) );

		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new MainSnakContext( $entity, $statement ), $constraint );

		$this->assertCompliance( $checkResult );
	}

	public function testItemConstraintDeprecatedStatement() {
		$statement = NewStatement::noValueFor( 'P1' )
				   ->withDeprecatedRank()
				   ->build();
		$constraint = $this->getConstraintMock( [] );
		$entity = NewItem::withId( 'Q1' )
				->build();

		$checkResult = $this->checker->checkConstraint( new MainSnakContext( $entity, $statement ), $constraint );

		$this->assertDeprecation( $checkResult );
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
			 ->will( $this->returnValue( 'Q21503247' ) );

		return $mock;
	}

}
