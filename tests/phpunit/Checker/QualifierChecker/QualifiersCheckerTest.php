<?php

namespace WikibaseQuality\ConstraintReport\Test\QualifierChecker;

use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Statement\StatementListProvider;
use Wikibase\Repo\Tests\NewItem;
use Wikibase\Repo\Tests\NewStatement;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\QualifiersChecker;
use WikibaseQuality\ConstraintReport\Tests\ConstraintParameters;
use WikibaseQuality\ConstraintReport\Tests\ResultAssertions;
use WikibaseQuality\Tests\Helper\JsonFileEntityLookup;

/**
 * @covers \WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\QualifiersChecker
 *
 * @group WikibaseQualityConstraints
 *
 * @uses   \WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 * @uses   \WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class QualifiersCheckerTest extends \MediaWikiTestCase {

	use ConstraintParameters, ResultAssertions;

	/**
	 * @var string[]
	 */
	private $qualifiersList;

	/**
	 * @var JsonFileEntityLookup
	 */
	private $lookup;

	/**
	 * @var QualifiersChecker
	 */
	private $checker;

	protected function setUp() {
		parent::setUp();
		$this->qualifiersList = [ 'P580', 'P582', 'P1365', 'P1366', 'P642', 'P805' ];
		$this->lookup = new JsonFileEntityLookup( __DIR__ );
		$this->checker = new QualifiersChecker( $this->getConstraintParameterParser(), $this->getConstraintParameterRenderer() );
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

	public function testQualifiersConstraint() {
		/** @var Item $entity */
		$entity = $this->lookup->getEntity( new ItemId( 'Q2' ) );
		$statement = $this->getFirstStatement( $entity );
		$constraint = $this->getConstraintMock( $this->propertiesParameter( $this->qualifiersList ) );

		$checkResult = $this->checker->checkConstraint( $statement, $constraint, $entity );

		$this->assertCompliance( $checkResult );
	}

	public function testQualifiersConstraintTooManyQualifiers() {
		/** @var Item $entity */
		$entity = $this->lookup->getEntity( new ItemId( 'Q3' ) );
		$statement = $this->getFirstStatement( $entity );
		$constraint = $this->getConstraintMock( $this->propertiesParameter( $this->qualifiersList ) );

		$checkResult = $this->checker->checkConstraint( $statement, $constraint, $entity );

		$this->assertViolation( $checkResult, 'wbqc-violation-message-qualifiers' );
	}

	public function testQualifiersConstraintNoQualifiers() {
		/** @var Item $entity */
		$entity = $this->lookup->getEntity( new ItemId( 'Q4' ) );
		$statement = $this->getFirstStatement( $entity );
		$constraint = $this->getConstraintMock( $this->propertiesParameter( $this->qualifiersList ) );

		$checkResult = $this->checker->checkConstraint( $statement, $constraint, $entity );

		$this->assertCompliance( $checkResult );
	}

	public function testQualifiersConstraintDeprecatedStatement() {
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
			 ->will( $this->returnValue( 'Qualifiers' ) );

		return $mock;
	}

}
