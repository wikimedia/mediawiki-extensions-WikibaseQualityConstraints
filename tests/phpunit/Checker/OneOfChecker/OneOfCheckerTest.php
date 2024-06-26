<?php

namespace WikibaseQuality\ConstraintReport\Tests\Checker\OneOfChecker;

use DataValues\StringValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\NumericPropertyId;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Snak\PropertySomeValueSnak;
use Wikibase\DataModel\Tests\NewItem;
use Wikibase\DataModel\Tests\NewStatement;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\OneOfChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\MainSnakContext;
use WikibaseQuality\ConstraintReport\Tests\Checker\PropertyResolvingMediaWikiIntegrationTestCase;
use WikibaseQuality\ConstraintReport\Tests\ConstraintParameters;
use WikibaseQuality\ConstraintReport\Tests\Fake\FakeSnakContext;
use WikibaseQuality\ConstraintReport\Tests\ResultAssertions;

/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\OneOfChecker
 *
 * @group WikibaseQualityConstraints
 *
 * @author BP2014N1
 * @license GPL-2.0-or-later
 */
class OneOfCheckerTest extends PropertyResolvingMediaWikiIntegrationTestCase {

	use ConstraintParameters;
	use ResultAssertions;

	/**
	 * @var OneOfChecker
	 */
	private $oneOfChecker;

	protected function setUp(): void {
		parent::setUp();
		$this->oneOfChecker = new OneOfChecker(
			$this->getConstraintParameterParser()
		);
	}

	public function testOneOfConstraintValid() {
		$statement = NewStatement::forProperty( 'P123' )
			->withValue( new ItemId( 'Q1' ) )
			->build();

		$result = $this->oneOfChecker->checkConstraint(
			new FakeSnakContext( $statement->getMainSnak() ),
			$this->getConstraintMock( $this->itemsParameter( [ 'Q1', 'Q2', 'Q3' ] ) )
		);

		$this->assertCompliance( $result );
	}

	public function testOneOfConstraintInvalid() {
		$statement = NewStatement::forProperty( 'P123' )
			->withValue( new ItemId( 'Q9' ) )
			->build();

		$result = $this->oneOfChecker->checkConstraint(
			new FakeSnakContext( $statement->getMainSnak() ),
			$this->getConstraintMock( $this->itemsParameter( [ 'Q1', 'Q2', 'Q3' ] ) )
		);

		$this->assertViolation( $result, 'wbqc-violation-message-one-of' );
	}

	public function testOneOfConstraintWrongType() {
		$statement = NewStatement::forProperty( 'P123' )
			->withValue( new StringValue( 'Q1' ) )
			->build();

		$result = $this->oneOfChecker->checkConstraint(
			new FakeSnakContext( $statement->getMainSnak() ),
			$this->getConstraintMock( $this->itemsParameter( [ 'Q1', 'Q2', 'Q3' ] ) )
		);

		$this->assertViolation( $result, 'wbqc-violation-message-one-of' );
	}

	public function testOneOfConstraintArraySomevalueNovalue() {
		$somevalueSnak = new PropertySomeValueSnak( new NumericPropertyId( 'P123' ) );
		$novalueSnak = new PropertyNoValueSnak( new NumericPropertyId( 'P123' ) );

		foreach ( [ $somevalueSnak, $novalueSnak ] as $allowed ) {
			foreach ( [ $somevalueSnak, $novalueSnak ] as $present ) {
				$result = $this->oneOfChecker->checkConstraint(
					new FakeSnakContext( $present ),
					$this->getConstraintMock( $this->itemsParameter( [ $allowed ] ) )
				);
				if ( $allowed === $present ) {
					$this->assertCompliance( $result );
				} else {
					$this->assertViolation( $result, 'wbqc-violation-message-one-of' );
				}
			}
		}
	}

	public function testOneOfConstraintDeprecatedStatement() {
		$statement = NewStatement::noValueFor( 'P1' )
			->withDeprecatedRank()
			->build();
		$constraint = $this->getConstraintMock( [] );
		$entity = NewItem::withId( 'Q1' )
			->build();

		$checkResult = $this->oneOfChecker->checkConstraint( new MainSnakContext( $entity, $statement ), $constraint );

		$this->assertDeprecation( $checkResult );
	}

	public function testCheckConstraintParameters() {
		$constraint = $this->getConstraintMock( [] );

		$result = $this->oneOfChecker->checkConstraintParameters( $constraint );

		$this->assertCount( 1, $result );
	}

	/**
	 * @param string[] $parameters
	 *
	 * @return Constraint
	 */
	private function getConstraintMock( array $parameters ) {
		$mock = $this->createMock( Constraint::class );
		$mock->method( 'getConstraintParameters' )
			 ->willReturn( $parameters );
		$mock->method( 'getConstraintTypeItemId' )
			 ->willReturn( 'Q21510859' );

		return $mock;
	}

}
