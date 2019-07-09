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
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\ConflictsWithChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\MainSnakContext;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConnectionCheckerHelper;
use WikibaseQuality\ConstraintReport\Tests\ConstraintParameters;
use WikibaseQuality\ConstraintReport\Tests\Helper\JsonFileEntityLookup;
use WikibaseQuality\ConstraintReport\Tests\ResultAssertions;

/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\ConflictsWithChecker
 *
 * @group WikibaseQualityConstraints
 *
 * @author BP2014N1
 * @license GPL-2.0-or-later
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
			$this->getConstraintParameterParser(),
			$this->connectionCheckerHelper
		);
	}

	public function testConflictsWithConstraintValid() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q4' ) );

		$value = new EntityIdValue( new ItemId( 'Q100' ) );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) );

		$constraintParameters = $this->propertyParameter( 'P2' );

		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new MainSnakContext( $entity, $statement ), $constraint );

		$this->assertCompliance( $checkResult );
	}

	public function testConflictsWithConstraintProperty() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q5' ) );

		$value = new EntityIdValue( new ItemId( 'Q100' ) );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) );

		$constraintParameters = $this->propertyParameter( 'P2' );

		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new MainSnakContext( $entity, $statement ), $constraint );

		$this->assertViolation( $checkResult, 'wbqc-violation-message-conflicts-with-property' );
	}

	public function testConflictsWithConstraintPropertyButNotItem() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q5' ) );

		$value = new EntityIdValue( new ItemId( 'Q100' ) );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) );

		$constraintParameters = array_merge(
			$this->propertyParameter( 'P2' ),
			$this->itemsParameter( [ 'Q1' ] )
		);

		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new MainSnakContext( $entity, $statement ), $constraint );

		$this->assertCompliance( $checkResult );
	}

	public function testConflictsWithConstraintPropertyAndItem() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q5' ) );

		$value = new EntityIdValue( new ItemId( 'Q100' ) );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) );

		$constraintParameters = array_merge(
			$this->propertyParameter( 'P2' ),
			$this->itemsParameter( [ 'Q42' ] )
		);

		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new MainSnakContext( $entity, $statement ), $constraint );

		$this->assertViolation( $checkResult, 'wbqc-violation-message-conflicts-with-claim' );
	}

	public function testConflictsWithConstraintPropertyAndNoValue() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q6' ) );

		$value = new EntityIdValue( new ItemId( 'Q100' ) );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) );

		$constraintParameters = array_merge(
			$this->propertyParameter( 'P2' ),
			$this->itemsParameter( [ 'Q42' ] )
		);

		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new MainSnakContext( $entity, $statement ), $constraint );

		$this->assertCompliance( $checkResult );
	}

	public function testConflictsWithConstraintPropertyButDeprecated() {
		$notConflictingStatement = NewStatement::noValueFor( 'P1' )
			->withDeprecatedRank()
			->build();
		$statement = NewStatement::noValueFor( 'P2' )
			->build();
		$entity = NewItem::withId( 'Q1' )
			->andStatement( $notConflictingStatement )
			->andStatement( $statement )
			->build();

		$constraint = $this->getConstraintMock(
			$this->propertyParameter( 'P1' )
		);
		$context = new MainSnakContext( $entity, $statement );

		$checkResult = $this->checker->checkConstraint( $context, $constraint );

		$this->assertCompliance( $checkResult );
	}

	public function testConflictsWithConstraintPropertyAndItemButDeprecated() {
		$notConflictingStatement = NewStatement::forProperty( 'P1' )
			->withValue( new ItemId( 'Q10' ) )
			->withDeprecatedRank()
			->build();
		$statement = NewStatement::noValueFor( 'P2' )
			->build();
		$entity = NewItem::withId( 'Q1' )
			->andStatement( $notConflictingStatement )
			->andStatement( $statement )
			->build();

		$constraint = $this->getConstraintMock( array_merge(
			$this->propertyParameter( 'P1' ),
			$this->itemsParameter( [ 'Q10' ] )
		) );
		$context = new MainSnakContext( $entity, $statement );

		$checkResult = $this->checker->checkConstraint( $context, $constraint );

		$this->assertCompliance( $checkResult );
	}

	public function testConflictsWithConstraintDeprecatedStatement() {
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
			 ->will( $this->returnValue( 'Q21502838' ) );

		return $mock;
	}

}
