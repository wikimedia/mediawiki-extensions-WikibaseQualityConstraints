<?php

namespace WikibaseQuality\ConstraintReport\Test\ValueCountChecker;

use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\Repo\Tests\NewItem;
use Wikibase\Repo\Tests\NewStatement;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\SingleValueChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\StatementContext;
use WikibaseQuality\ConstraintReport\Tests\ResultAssertions;
use WikibaseQuality\Tests\Helper\JsonFileEntityLookup;

/**
 * @covers \WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\SingleValueChecker
 *
 * @group WikibaseQualityConstraints
 *
 * @uses   \WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class SingleValueCheckerTest extends \MediaWikiTestCase {

	use ResultAssertions;

	/**
	 * @var PropertyId
	 */
	private $singlePropertyId;

	/**
	 * @var SingleValueChecker
	 */
	private $checker;

	/**
	 * @var JsonFileEntityLookup
	 */
	private $lookup;

	protected function setUp() {
		parent::setUp();

		$this->singlePropertyId = new PropertyId( 'P36' );
		$this->checker = new SingleValueChecker();
		$this->lookup = new JsonFileEntityLookup( __DIR__ );
	}

	public function testSingleValueConstraintOne() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
		$statement = new Statement( new PropertyValueSnak( $this->singlePropertyId, new EntityIdValue( new ItemId( 'Q1384' ) ) ) );
		$constraint = $this->getConstraintMock( [] );

		$checkResult = $this->checker->checkConstraint( new StatementContext( $entity, $statement ), $constraint );

		$this->assertCompliance( $checkResult );
	}

	public function testSingleValueConstraintTwo() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q2' ) );
		$statement = new Statement( new PropertyValueSnak( $this->singlePropertyId, new EntityIdValue( new ItemId( 'Q1384' ) ) ) );
		$constraint = $this->getConstraintMock( [] );

		$checkResult = $this->checker->checkConstraint( new StatementContext( $entity, $statement ), $constraint );

		$this->assertViolation( $checkResult, 'wbqc-violation-message-single-value' );
	}

	public function testSingleValueConstraintTwoButOneDeprecated() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q3' ) );
		$statement = new Statement( new PropertyValueSnak( $this->singlePropertyId, new EntityIdValue( new ItemId( 'Q1384' ) ) ) );
		$constraint = $this->getConstraintMock( [] );

		$checkResult = $this->checker->checkConstraint( new StatementContext( $entity, $statement ), $constraint );

		$this->assertCompliance( $checkResult );
	}

	public function testSingleValueConstraintDeprecatedStatement() {
		$statement = NewStatement::noValueFor( 'P1' )
				   ->withDeprecatedRank()
				   ->build();
		$constraint = $this->getConstraintMock( [] );
		$entity = NewItem::withId( 'Q1' )
				->build();

		$checkResult = $this->checker->checkConstraint( new StatementContext( $entity, $statement ), $constraint );

		$this->assertDeprecation( $checkResult );
	}

	public function testCheckConstraintParameters() {
		$constraint = $this->getConstraintMock( [] );

		$result = $this->checker->checkConstraintParameters( $constraint );

		$this->assertEmpty( $result );
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
			 ->method( 'getConstraintParameter' )
			 ->will( $this->returnValue( $parameters ) );
		$mock->expects( $this->any() )
			 ->method( 'getConstraintTypeItemId' )
			 ->will( $this->returnValue( 'Single value' ) );

		return $mock;
	}

}
