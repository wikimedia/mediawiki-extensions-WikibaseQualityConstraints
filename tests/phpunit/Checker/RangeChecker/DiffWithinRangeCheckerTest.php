<?php

namespace WikibaseQuality\ConstraintReport\Test\RangeChecker;

use DataValues\TimeValue;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\Repo\Tests\NewItem;
use Wikibase\Repo\Tests\NewStatement;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\DiffWithinRangeChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\StatementContext;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\RangeCheckerHelper;
use WikibaseQuality\ConstraintReport\Tests\ConstraintParameters;
use WikibaseQuality\ConstraintReport\Tests\ResultAssertions;

/**
 * @covers \WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\DiffWithinRangeChecker
 *
 * @group WikibaseQualityConstraints
 *
 * @uses   \WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 * @uses   \WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class DiffWithinRangeCheckerTest extends \MediaWikiTestCase {

	use ConstraintParameters, ResultAssertions;

	/**
	 * A TimeValue for the year 1.
	 *
	 * @var TimeValue
	 */
	private static $t0001;

	/**
	 * A TimeValue for the year 1800.
	 *
	 * @var TimeValue
	 */
	private static $t1800;

	/**
	 * A TimeValue for the year 1900.
	 *
	 * @var TimeValue
	 */
	private static $t1900;

	/**
	 * A TimeValue for the year 1970.
	 *
	 * @var TimeValue
	 */
	private static $t1970;

	/**
	 * A TimeValue for the year 2000.
	 *
	 * @var TimeValue
	 */
	private static $t2000;

	/**
	 * A Statement for a $t1970 date of death (P570).
	 *
	 * @var Statement
	 */
	private static $s1970;

	/**
	 * Builder for an item with date of death 1970 ($s1970).
	 *
	 * @var NewItem
	 */
	private static $i1970;

	/**
	 * Constraint parameters specifying a range of [0, 150] (years) for date of birth (P569).
	 *
	 * @var array
	 */
	private $dob0to150Parameters;

	/**
	 * @var DiffWithinRangeChecker
	 */
	private $checker;

	public static function setUpBeforeClass() {
		parent::setUpBeforeClass();
		self::$t0001 = new TimeValue( '+00000000001-01-01T00:00:00Z', 0, 0, 0, 11, 'http://www.wikidata.org/entity/Q1985727' );
		self::$t1800 = new TimeValue( '+00000001800-01-01T00:00:00Z', 0, 0, 0, 11, 'http://www.wikidata.org/entity/Q1985727' );
		self::$t1900 = new TimeValue( '+00000001900-01-01T00:00:00Z', 0, 0, 0, 11, 'http://www.wikidata.org/entity/Q1985727' );
		self::$t1970 = new TimeValue( '+00000001970-01-01T00:00:00Z', 0, 0, 0, 11, 'http://www.wikidata.org/entity/Q1985727' );
		self::$t2000 = new TimeValue( '+00000002000-01-01T00:00:00Z', 0, 0, 0, 11, 'http://www.wikidata.org/entity/Q1985727' );
		self::$s1970 = NewStatement::forProperty( 'P570' )->withValue( self::$t1970 )->build();
		self::$i1970 = NewItem::withId( 'Q1' )->andStatement( self::$s1970 );
	}

	protected function setUp() {
		parent::setUp();
		$this->dob0to150Parameters = array_merge(
			$this->propertyParameter( 'P569' ),
			$this->rangeParameter( 'quantity', 0, 150 )
		);
		$this->checker = new DiffWithinRangeChecker(
			$this->getConstraintParameterParser(),
			new RangeCheckerHelper( $this->getDefaultConfig() ),
			$this->getConstraintParameterRenderer()
		);
	}

	public function testDiffWithinRangeConstraintWithinRange() {
		$entity = self::$i1970
			->andStatement( NewStatement::forProperty( 'P569' )->withValue( self::$t1900 ) )
			->build();
		$constraint = $this->getConstraintMock( $this->dob0to150Parameters );

		$checkResult = $this->checker->checkConstraint( new StatementContext( $entity, self::$s1970 ), $constraint );

		$this->assertCompliance( $checkResult );
	}

	public function testDiffWithinRangeConstraintTooSmall() {
		$entity = self::$i1970
			->andStatement( NewStatement::forProperty( 'P569' )->withValue( self::$t2000 ) )
			->build();
		$constraint = $this->getConstraintMock( $this->dob0to150Parameters );

		$checkResult = $this->checker->checkConstraint( new StatementContext( $entity, self::$s1970 ), $constraint );

		$this->assertViolation( $checkResult, 'wbqc-violation-message-diff-within-range' );
	}

	public function testDiffWithinRangeConstraintTooBig() {
		$entity = self::$i1970
			->andStatement( NewStatement::forProperty( 'P569' )->withValue( self::$t1800 ) )
			->build();
		$constraint = $this->getConstraintMock( $this->dob0to150Parameters );

		$checkResult = $this->checker->checkConstraint( new StatementContext( $entity, self::$s1970 ), $constraint );

		$this->assertViolation( $checkResult, 'wbqc-violation-message-diff-within-range' );
	}

	public function testDiffWithinRangeConstraintWithinRangeWithDeprecatedStatement() {
		$deprecatedStatement = NewStatement::forProperty( 'P569' )
			->withValue( self::$t1800 )
			->withRank( Statement::RANK_DEPRECATED );
		$entity = self::$i1970
			->andStatement( $deprecatedStatement ) // should be ignored
			->andStatement( NewStatement::forProperty( 'P569' )->withValue( self::$t1900 ) )
			->build();
		$constraint = $this->getConstraintMock( $this->dob0to150Parameters );

		$checkResult = $this->checker->checkConstraint( new StatementContext( $entity, self::$s1970 ), $constraint );

		$this->assertCompliance( $checkResult );
	}

	public function testDiffWithinRangeConstraintWithinRangeWithOtherSnakTypes() {
		$noValueStatement = NewStatement::noValueFor( 'P569' );
		$someValueStatement = NewStatement::someValueFor( 'P569' );
		$entity = self::$i1970
			->andStatement( $noValueStatement ) // should be ignored
			->andStatement( $someValueStatement ) // should be ignored
			->andStatement( NewStatement::forProperty( 'P569' )->withValue( self::$t1900 ) )
			->build();
		$constraint = $this->getConstraintMock( $this->dob0to150Parameters );

		$checkResult = $this->checker->checkConstraint( new StatementContext( $entity, self::$s1970 ), $constraint );

		$this->assertCompliance( $checkResult );
	}

	public function testDiffWithinRangeConstraintWithoutStatement() {
		$entity = self::$i1970->build();
		$constraint = $this->getConstraintMock( $this->dob0to150Parameters );

		$checkResult = $this->checker->checkConstraint( new StatementContext( $entity, self::$s1970 ), $constraint );

		$this->assertViolation( $checkResult, 'wbqc-violation-message-diff-within-range-property-must-exist' );
	}

	public function testDiffWithinRangeConstraintWithOnlyDeprecatedStatement() {
		$deprecatedStatement = NewStatement::forProperty( 'P569' )
			->withValue( self::$t1900 )
			->withRank( Statement::RANK_DEPRECATED );
		$entity = self::$i1970
			->andStatement( $deprecatedStatement ) // should be ignored
			->build();
		$constraint = $this->getConstraintMock( $this->dob0to150Parameters );

		$checkResult = $this->checker->checkConstraint( new StatementContext( $entity, self::$s1970 ), $constraint );

		$this->assertViolation( $checkResult, 'wbqc-violation-message-diff-within-range-property-must-exist' );
	}

	public function testDiffWithinRangeConstraintWithOnlyOtherSnakTypes() {
		$noValueStatement = NewStatement::noValueFor( 'P569' );
		$someValueStatement = NewStatement::someValueFor( 'P569' );
		$entity = self::$i1970
			->andStatement( $noValueStatement ) // should be ignored
			->andStatement( $someValueStatement ) // should be ignored
			->build();
		$constraint = $this->getConstraintMock( $this->dob0to150Parameters );

		$checkResult = $this->checker->checkConstraint( new StatementContext( $entity, self::$s1970 ), $constraint );

		$this->assertViolation( $checkResult, 'wbqc-violation-message-diff-within-range-property-must-exist' );
	}

	public function testDiffWithinRangeConstraintWrongTypeOfProperty() {
		$entity = self::$i1970
			->andStatement( NewStatement::forProperty( 'P569' )->withValue( '1900' ) )
			->build();
		$constraint = $this->getConstraintMock( $this->dob0to150Parameters );

		$checkResult = $this->checker->checkConstraint( new StatementContext( $entity, self::$s1970 ), $constraint );

		$this->assertViolation( $checkResult, 'wbqc-violation-message-diff-within-range-must-have-equal-types' );
	}

	public function testDiffWithinRangeConstraintNoValueSnak() {
		$entity = self::$i1970->build();
		$statement = NewStatement::noValueFor( 'P1' )->build();
		$constraint = $this->getConstraintMock( $this->dob0to150Parameters );

		$checkResult = $this->checker->checkConstraint( new StatementContext( $entity, $statement ), $constraint );

		$this->assertCompliance( $checkResult );
	}

	public function testDiffWithinRangeConstraintLeftOpenWithinRange() {
		$entity = self::$i1970
			->andStatement( NewStatement::forProperty( 'P569' )->withValue( self::$t1970 ) )
			->build();
		$constraintParameters = array_merge(
			$this->propertyParameter( 'P569' ),
			$this->rangeParameter( 'quantity', null, 150 )
		);
		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new StatementContext( $entity, self::$s1970 ), $constraint );

		$this->assertCompliance( $checkResult );
	}

	public function testDiffWithinRangeConstraintLeftOpenTooBig() {
		$entity = self::$i1970
			->andStatement( NewStatement::forProperty( 'P569' )->withValue( self::$t0001 ) )
			->build();
		$constraintParameters = array_merge(
			$this->propertyParameter( 'P569' ),
			$this->rangeParameter( 'quantity', null, 150 )
		);
		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new StatementContext( $entity, self::$s1970 ), $constraint );

		$this->assertViolation( $checkResult, 'wbqc-violation-message-diff-within-range-leftopen' );
	}

	public function testDiffWithinRangeConstraintRightOpenWithinRange() {
		$entity = self::$i1970
			->andStatement( NewStatement::forProperty( 'P569' )->withValue( self::$t0001 ) )
			->build();
		$constraintParameters = array_merge(
			$this->propertyParameter( 'P569' ),
			$this->rangeParameter( 'quantity', 0, null )
		);
		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new StatementContext( $entity, self::$s1970 ), $constraint );

		$this->assertCompliance( $checkResult );
	}

	public function testDiffWithinRangeConstraintRightOpenTooSmall() {
		$entity = self::$i1970
			->andStatement( NewStatement::forProperty( 'P569' )->withValue( self::$t2000 ) )
			->build();
		$constraintParameters = array_merge(
			$this->propertyParameter( 'P569' ),
			$this->rangeParameter( 'quantity', 0, null )
		);
		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new StatementContext( $entity, self::$s1970 ), $constraint );

		$this->assertViolation( $checkResult, 'wbqc-violation-message-diff-within-range-rightopen' );
	}

	public function testDiffWithinRangeConstraintDeprecatedStatement() {
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

		$this->assertCount( 2, $result );
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
			 ->will( $this->returnValue( 'Diff within range' ) );

		return $mock;
	}

}
