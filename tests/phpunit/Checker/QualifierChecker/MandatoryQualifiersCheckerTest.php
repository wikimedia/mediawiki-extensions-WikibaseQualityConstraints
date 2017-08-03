<?php

namespace WikibaseQuality\ConstraintReport\Test\QualifierChecker;

use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Snak\SnakList;
use Wikibase\DataModel\Statement\Statement;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\MandatoryQualifiersChecker;
use WikibaseQuality\ConstraintReport\Tests\ConstraintParameters;
use WikibaseQuality\ConstraintReport\Tests\ResultAssertions;
use Wikibase\Repo\Tests\NewItem;
use Wikibase\Repo\Tests\NewStatement;

/**
 * @covers \WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\MandatoryQualifiersChecker
 *
 * @group WikibaseQualityConstraints
 *
 * @uses   \WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 * @uses   \WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class MandatoryQualifiersCheckerTest extends \MediaWikiTestCase {

	use ConstraintParameters, ResultAssertions;

	/**
	 * @var MandatoryQualifiersChecker
	 */
	private $checker;

	protected function setUp() {
		parent::setUp();
		$this->checker = new MandatoryQualifiersChecker(
			$this->getConstraintParameterParser(),
			$this->getConstraintParameterRenderer()
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
		$parameters = $this->propertyParameter( 'P2' );

		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $parameters ), $entity );

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
		$parameters = $this->propertyParameter( 'P3' );

		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $parameters ), $entity );

		$this->assertViolation( $checkResult, 'wbqc-violation-message-mandatory-qualifier' );
	}

	public function testMandatoryQualifiersConstraintDeprecatedStatement() {
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
			 ->will( $this->returnValue( 'Mandatory qualifiers' ) );

		return $mock;
	}

}
