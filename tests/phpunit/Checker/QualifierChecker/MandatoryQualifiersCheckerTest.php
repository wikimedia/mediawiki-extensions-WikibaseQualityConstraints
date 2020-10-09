<?php

namespace WikibaseQuality\ConstraintReport\Tests\Checker\QualifierChecker;

use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Snak\SnakList;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\Repo\Tests\NewItem;
use Wikibase\Repo\Tests\NewStatement;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\MandatoryQualifiersChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\MainSnakContext;
use WikibaseQuality\ConstraintReport\Tests\ConstraintParameters;
use WikibaseQuality\ConstraintReport\Tests\ResultAssertions;

/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\MandatoryQualifiersChecker
 *
 * @group WikibaseQualityConstraints
 *
 * @author BP2014N1
 * @license GPL-2.0-or-later
 */
class MandatoryQualifiersCheckerTest extends \MediaWikiTestCase {

	use ConstraintParameters;
	use ResultAssertions;

	/**
	 * @var MandatoryQualifiersChecker
	 */
	private $checker;

	protected function setUp() : void {
		parent::setUp();
		$this->checker = new MandatoryQualifiersChecker(
			$this->getConstraintParameterParser()
		);
	}

	public function testMandatoryQualifiersConstraintValid() {
		$statement = new Statement(
			new PropertyNoValueSnak( new PropertyId( 'P1' ) ),
			new SnakList( [
				new PropertyNoValueSnak( new PropertyId( 'P2' ) )
			] )
		);
		$entity = NewItem::withId( 'Q5' )
			->andStatement( $statement )
			->build();
		$constraintParameters = $this->propertyParameter( 'P2' );

		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new MainSnakContext( $entity, $statement ), $constraint );

		$this->assertCompliance( $checkResult );
	}

	public function testMandatoryQualifiersConstraintInvalid() {
		$statement = new Statement(
			new PropertyNoValueSnak( new PropertyId( 'P1' ) ),
			new SnakList( [
				new PropertyNoValueSnak( new PropertyId( 'P2' ) )
			] )
		);
		$entity = NewItem::withId( 'Q5' )
			->andStatement( $statement )
			->build();
		$constraintParameters = $this->propertyParameter( 'P3' );

		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new MainSnakContext( $entity, $statement ), $constraint );

		$this->assertViolation( $checkResult, 'wbqc-violation-message-mandatory-qualifier' );
	}

	public function testMandatoryQualifiersConstraintDeprecatedStatement() {
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
			 ->will( $this->returnValue( 'Q21510856' ) );

		return $mock;
	}

}
