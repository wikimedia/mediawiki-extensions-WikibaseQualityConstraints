<?php

namespace WikibaseQuality\ConstraintReport\Test\QualifierChecker;

use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Statement\StatementListProvider;
use Wikibase\Repo\Tests\NewItem;
use Wikibase\Repo\Tests\NewStatement;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\QualifierChecker;
use WikibaseQuality\ConstraintReport\Tests\ResultAssertions;
use WikibaseQuality\Tests\Helper\JsonFileEntityLookup;

/**
 * @covers \WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\QualifierChecker
 *
 * @group WikibaseQualityConstraints
 *
 * @uses   \WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class QualifierCheckerTest extends \MediaWikiTestCase {

	use ResultAssertions;

	/**
	 * @var JsonFileEntityLookup
	 */
	private $lookup;

	protected function setUp() {
		parent::setUp();
		$this->lookup = new JsonFileEntityLookup( __DIR__ );
	}

	/**
	 * @param StatementListProvider $entity
	 *
	 * @return Statement|false
	 */
	private function getFirstStatement( StatementListProvider $entity ) {
		$statements = $entity->getStatements()->toArray();
		return reset( $statements );
	}

	public function testQualifierConstraintQualifierProperty() {
		/** @var Item $entity */
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
		$qualifierChecker = new QualifierChecker();
		$checkResult = $qualifierChecker->checkConstraint( $this->getFirstStatement( $entity ), $this->getConstraintMock( [] ), $entity );
		$this->assertViolation( $checkResult, 'wbqc-violation-message-qualifier' );
	}

	public function testQualifierConstraintDeprecatedStatement() {
		$checker = new QualifierChecker();
		$statement = NewStatement::noValueFor( 'P1' )
				   ->withDeprecatedRank()
				   ->build();
		$constraint = $this->getConstraintMock( [] );
		$entity = NewItem::withId( 'Q1' )
				->build();

		$checkResult = $checker->checkConstraint( $statement, $constraint, $entity );

		// this constraint is still checked on deprecated statements
		$this->assertViolation( $checkResult, 'wbqc-violation-message-qualifier' );
	}

	public function testCheckConstraintParameters() {
		$checker = new QualifierChecker();
		$constraint = $this->getConstraintMock( [] );

		$result = $checker->checkConstraintParameters( $constraint );

		$this->assertCount( 0, $result );
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
			 ->will( $this->returnValue( 'Qualifier' ) );

		return $mock;
	}

}
