<?php

namespace WikibaseQuality\ConstraintReport\Test\ConnectionChecker;

use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use DataValues\StringValue;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\Repo\Tests\NewItem;
use Wikibase\Repo\Tests\NewStatement;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\SymmetricChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConnectionCheckerHelper;
use WikibaseQuality\ConstraintReport\Tests\ConstraintParameters;
use WikibaseQuality\ConstraintReport\Tests\ResultAssertions;
use WikibaseQuality\Tests\Helper\JsonFileEntityLookup;

/**
 * @covers \WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\SymmetricChecker
 *
 * @group WikibaseQualityConstraints
 *
 * @uses   \WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class SymmetricCheckerTest extends \MediaWikiTestCase {

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
	 * @var SymmetricChecker
	 */
	private $checker;

	protected function setUp() {
		parent::setUp();
		$this->lookup = new JsonFileEntityLookup( __DIR__ );
		$this->connectionCheckerHelper = new ConnectionCheckerHelper();
		$this->checker = new SymmetricChecker(
			$this->lookup,
			$this->connectionCheckerHelper,
			$this->getConstraintParameterRenderer()
		);
	}

	public function testSymmetricConstraintWithCorrectSpouse() {
		$value = new EntityIdValue( new ItemId( 'Q3' ) );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) );

		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock(), $this->getEntity() );
		$this->assertCompliance( $checkResult );
	}

	public function testSymmetricConstraintOnProperty() {
		$entity = new Property( new PropertyId( 'P1' ), null, 'wikibase-property' );
		$value = new EntityIdValue( new PropertyId( 'P2' ) );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P3' ), $value ) );

		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock(), $entity );

		$this->assertCompliance( $checkResult );
	}

	public function testSymmetricConstraintWithWrongSpouse() {
		$value = new EntityIdValue( new ItemId( 'Q2' ) );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) );

		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock(), $this->getEntity() );
		$this->assertViolation( $checkResult, 'wbqc-violation-message-symmetric' );
	}

	public function testSymmetricConstraintWithWrongDataValue() {
		$value = new StringValue( 'Q3' );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) );

		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock(), $this->getEntity() );
		$this->assertViolation( $checkResult, 'wbqc-violation-message-value-needed-of-type' );
	}

	public function testSymmetricConstraintWithNonExistentEntity() {
		$value = new EntityIdValue( new ItemId( 'Q100' ) );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) );

		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock(), $this->getEntity() );
		$this->assertViolation( $checkResult, 'wbqc-violation-message-target-entity-must-exist' );
	}

	public function testSymmetricConstraintNoValueSnak() {
		$statement = NewStatement::noValueFor( 'P1' )->build();

		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock(), $this->getEntity() );
		$this->assertCompliance( $checkResult );
	}

	public function testSymmetricConstraintDeprecatedStatement() {
		$statement = NewStatement::noValueFor( 'P1' )
				   ->withDeprecatedRank()
				   ->build();
		$constraint = $this->getConstraintMock( [] );
		$entity = NewItem::withId( 'Q1' )
				->build();

		$checkResult = $this->checker->checkConstraint( $statement, $constraint, $entity );

		$this->assertDeprecation( $checkResult );
	}

	public function testCheckConstraintParameters() {
		$constraint = $this->getConstraintMock( [] );

		$result = $this->checker->checkConstraintParameters( $constraint );

		$this->assertCount( 0, $result );
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
			 ->will( $this->returnValue( 'Symmetric' ) );

		return $mock;
	}

	/**
	 * @return EntityDocument
	 */
	private function getEntity() {
		return new Item( new ItemId( 'Q1' ) );
	}

}
