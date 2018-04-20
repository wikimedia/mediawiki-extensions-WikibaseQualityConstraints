<?php

namespace WikibaseQuality\ConstraintReport\Tests\ValueCountChecker;

use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Reference;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Snak\PropertySomeValueSnak;
use Wikibase\Repo\Tests\NewItem;
use Wikibase\Repo\Tests\NewStatement;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\SingleValueChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\MainSnakContext;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\QualifierContext;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\ReferenceContext;
use WikibaseQuality\ConstraintReport\Tests\ResultAssertions;

/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\SingleValueChecker
 *
 * @group WikibaseQualityConstraints
 *
 * @author Lucas Werkmeister
 * @license GPL-2.0-or-later
 */
class SingleValueCheckerTest extends \PHPUnit\Framework\TestCase {

	use ResultAssertions;

	/**
	 * @var Constraint
	 */
	private $constraint;

	/**
	 * @var SingleValueChecker
	 */
	private $checker;

	protected function setUp() {
		parent::setUp();

		$this->constraint = $this->getMockBuilder( Constraint::class )
			->disableOriginalConstructor()
			->getMock();
		$this->checker = new SingleValueChecker();
	}

	public function testSingleValueConstraint_One() {
		$statement = NewStatement::noValueFor( 'P1' )->build();
		$item = NewItem::withId( 'Q1' )
			->andStatement( $statement )
			->build();
		$context = new MainSnakContext( $item, $statement );

		$checkResult = $this->checker->checkConstraint( $context, $this->constraint );

		$this->assertCompliance( $checkResult );
	}

	public function testSingleValueConstraint_Two() {
		$statement1 = NewStatement::noValueFor( 'P1' )->build();
		$statement2 = NewStatement::noValueFor( 'P1' )->build();
		$item = NewItem::withId( 'Q1' )
			->andStatement( $statement1 )
			->andStatement( $statement2 )
			->build();
		$context = new MainSnakContext( $item, $statement1 );

		$checkResult = $this->checker->checkConstraint( $context, $this->constraint );

		$this->assertViolation( $checkResult, 'wbqc-violation-message-single-value' );
	}

	public function testSingleValueConstraint_TwoButOneDeprecated() {
		$statement1 = NewStatement::noValueFor( 'P1' )->build();
		$statement2 = NewStatement::noValueFor( 'P1' )
			->withDeprecatedRank()
			->build();
		$item = NewItem::withId( 'Q1' )
			->andStatement( $statement1 )
			->andStatement( $statement2 )
			->build();
		$context = new MainSnakContext( $item, $statement1 );

		$checkResult = $this->checker->checkConstraint( $context, $this->constraint );

		$this->assertCompliance( $checkResult );
	}

	public function testSingleValueConstraint_One_Qualifier() {
		$qualifier1 = new PropertyNoValueSnak( new PropertyId( 'P1' ) );
		$qualifier2 = new PropertyNoValueSnak( new PropertyId( 'P2' ) );
		$statement = NewStatement::someValueFor( 'P10' )->build();
		$statement->getQualifiers()->addSnak( $qualifier1 );
		$statement->getQualifiers()->addSnak( $qualifier2 );
		$item = NewItem::withId( 'Q1' )
			->andStatement( $statement )
			->build();
		$context = new QualifierContext( $item, $statement, $qualifier1 );

		$checkResult = $this->checker->checkConstraint( $context, $this->constraint );

		$this->assertCompliance( $checkResult );
	}

	public function testSingleValueConstraint_Two_Reference() {
		$referenceSnak1 = new PropertyNoValueSnak( new PropertyId( 'P1' ) );
		$referenceSnak2 = new PropertySomeValueSnak( new PropertyId( 'P1' ) );
		$reference = new Reference( [ $referenceSnak1, $referenceSnak2 ] );
		$statement = NewStatement::someValueFor( 'P10' )->build();
		$statement->getReferences()->addReference( $reference );
		$item = NewItem::withId( 'Q1' )
			->andStatement( $statement )
			->build();
		$context = new ReferenceContext( $item, $statement, $reference, $referenceSnak1 );

		$checkResult = $this->checker->checkConstraint( $context, $this->constraint );

		$this->assertViolation( $checkResult, 'wbqc-violation-message-single-value' );
	}

	public function testSingleValueConstraintDeprecatedStatement() {
		$statement = NewStatement::noValueFor( 'P1' )
			->withDeprecatedRank()
			->build();
		$entity = NewItem::withId( 'Q1' )
			->build();
		$context = new MainSnakContext( $entity, $statement );

		$checkResult = $this->checker->checkConstraint( $context, $this->constraint );

		$this->assertDeprecation( $checkResult );
	}

	public function testCheckConstraintParameters() {
		$result = $this->checker->checkConstraintParameters( $this->constraint );

		$this->assertEmpty( $result );
	}

}
