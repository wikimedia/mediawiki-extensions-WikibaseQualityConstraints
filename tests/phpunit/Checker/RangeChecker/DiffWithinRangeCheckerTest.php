<?php

namespace WikibaseQuality\ConstraintReport\Tests\RangeChecker;

use DataValues\TimeValue;
use DataValues\UnboundedQuantityValue;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\Lib\Units\CSVUnitStorage;
use Wikibase\Lib\Units\UnitConverter;
use Wikibase\Repo\Tests\NewItem;
use Wikibase\Repo\Tests\NewStatement;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\DiffWithinRangeChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\MainSnakContext;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\RangeCheckerHelper;
use WikibaseQuality\ConstraintReport\Tests\ConstraintParameters;
use WikibaseQuality\ConstraintReport\Tests\ResultAssertions;

/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\DiffWithinRangeChecker
 *
 * @group WikibaseQualityConstraints
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
	 * Constraint parameters specifying a range of [0, 150] years (a, with conversion) for date of birth (P569).
	 *
	 * @var array
	 */
	private $dob0to150Parameters;

	/**
	 * Constraint parameters specifying a range of [0, 150] years (without conversion) for date of birth (P569).
	 *
	 * @var array
	 */
	private $dob0to150YearsParameters;

	/**
	 * Constraint parameters specifying a range of [5, 10] days for date of birth (P569).
	 *
	 * @var array
	 */
	private $dob5to10DaysParameters;

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
		$config = $this->getDefaultConfig();
		$yearUnit = $config->get( 'WBQualityConstraintsYearUnit' );
		$this->dob0to150Parameters = array_merge(
			$this->propertyParameter( 'P569' ),
			$this->rangeParameter(
				'quantity',
				UnboundedQuantityValue::newFromNumber( 0, 'a' ),
				UnboundedQuantityValue::newFromNumber( 150, 'a' )
			)
		);
		$this->dob0to150YearsParameters = array_merge(
			$this->propertyParameter( 'P569' ),
			$this->rangeParameter(
				'quantity',
				UnboundedQuantityValue::newFromNumber( 0, $yearUnit ),
				UnboundedQuantityValue::newFromNumber( 150, $yearUnit )
			)
		);
		$this->dob5to10DaysParameters = array_merge(
			$this->propertyParameter( 'P569' ),
			$this->rangeParameter(
				'quantity',
				UnboundedQuantityValue::newFromNumber( 5, 'd' ),
				UnboundedQuantityValue::newFromNumber( 10, 'd' )
			)
		);
		$rangeCheckerHelper = new RangeCheckerHelper(
			$config,
			new UnitConverter( new CSVUnitStorage( __DIR__ . '/units.csv' ), '' )
		);
		$this->checker = new DiffWithinRangeChecker(
			$this->getConstraintParameterParser(),
			$rangeCheckerHelper,
			$this->getConstraintParameterRenderer(),
			$config
		);
	}

	public function testDiffWithinRangeConstraintWithinRange() {
		$entity = self::$i1970
			->andStatement( NewStatement::forProperty( 'P569' )->withValue( self::$t1900 ) )
			->build();
		$constraint = $this->getConstraintMock( $this->dob0to150Parameters );

		$checkResult = $this->checker->checkConstraint( new MainSnakContext( $entity, self::$s1970 ), $constraint );

		$this->assertCompliance( $checkResult );
	}

	public function testDiffWithinRangeConstraintTooSmall() {
		$entity = self::$i1970
			->andStatement( NewStatement::forProperty( 'P569' )->withValue( self::$t2000 ) )
			->build();
		$constraint = $this->getConstraintMock( $this->dob0to150Parameters );

		$checkResult = $this->checker->checkConstraint( new MainSnakContext( $entity, self::$s1970 ), $constraint );

		$this->assertViolation( $checkResult, 'wbqc-violation-message-diff-within-range' );
	}

	public function testDiffWithinRangeConstraintTooBig() {
		$entity = self::$i1970
			->andStatement( NewStatement::forProperty( 'P569' )->withValue( self::$t1800 ) )
			->build();
		$constraint = $this->getConstraintMock( $this->dob0to150Parameters );

		$checkResult = $this->checker->checkConstraint( new MainSnakContext( $entity, self::$s1970 ), $constraint );

		$this->assertViolation( $checkResult, 'wbqc-violation-message-diff-within-range' );
	}

	public function testDiffWithinRangeConstraintWithinDaysRange() {
		$entity = self::$i1970
			->andStatement( NewStatement::forProperty( 'P569' )->withValue(
				new TimeValue( '+00000001969-12-24T00:00:00Z', 0, 0, 0, 11, 'http://www.wikidata.org/entity/Q1985727' )
			) )
			->build();
		$constraint = $this->getConstraintMock( $this->dob5to10DaysParameters );

		$checkResult = $this->checker->checkConstraint( new MainSnakContext( $entity, self::$s1970 ), $constraint );

		$this->assertCompliance( $checkResult );
	}

	public function testDiffWithinRangeConstraintTooFewDays() {
		$entity = self::$i1970
			->andStatement( NewStatement::forProperty( 'P569' )->withValue(
				new TimeValue( '+00000001969-12-31T00:00:00Z', 0, 0, 0, 11, 'http://www.wikidata.org/entity/Q1985727' )
			) )
			->build();
		$constraint = $this->getConstraintMock( $this->dob5to10DaysParameters );

		$checkResult = $this->checker->checkConstraint( new MainSnakContext( $entity, self::$s1970 ), $constraint );

		$this->assertViolation( $checkResult, 'wbqc-violation-message-diff-within-range' );
	}

	public function testDiffWithinRangeConstraintTooManyDays() {
		$entity = self::$i1970
			->andStatement( NewStatement::forProperty( 'P569' )->withValue(
				new TimeValue( '+00000001969-10-31T00:00:00Z', 0, 0, 0, 11, 'http://www.wikidata.org/entity/Q1985727' )
			) )
			->build();
		$constraint = $this->getConstraintMock( $this->dob5to10DaysParameters );

		$checkResult = $this->checker->checkConstraint( new MainSnakContext( $entity, self::$s1970 ), $constraint );

		$this->assertViolation( $checkResult, 'wbqc-violation-message-diff-within-range' );
	}

	public function testDiffWithinRangeConstraintWithinYearsRange() {
		// DoB June 1820 and DoD January 1970 has diff within range [0, 150] years
		$entity = self::$i1970
			->andStatement( NewStatement::forProperty( 'P569' )->withValue(
				new TimeValue( '+00000001820-06-01T00:00:00Z', 0, 0, 0, 11, 'http://www.wikidata.org/entity/Q1985727' )
			) )
			->build();
		$constraint = $this->getConstraintMock( $this->dob0to150YearsParameters );
		$context = new MainSnakContext( $entity, self::$s1970 );

		$checkResult = $this->checker->checkConstraint( $context, $constraint );

		$this->assertCompliance( $checkResult );
	}

	public function testDiffWithinRangeConstraintTooFewYears() {
		// DoB June 1970 and DoD January 1970 violates diff within range [0, 150] years
		// because June is after January, even though the year difference is 0 (within range)
		$entity = self::$i1970
			->andStatement( NewStatement::forProperty( 'P569' )->withValue(
				new TimeValue( '+00000001970-06-01T00:00:00Z', 0, 0, 0, 11, 'http://www.wikidata.org/entity/Q1985727' )
			) )
			->build();
		$constraint = $this->getConstraintMock( $this->dob0to150YearsParameters );
		$context = new MainSnakContext( $entity, self::$s1970 );

		$checkResult = $this->checker->checkConstraint( $context, $constraint );

		$this->assertViolation( $checkResult, 'wbqc-violation-message-diff-within-range' );
	}

	public function testDiffWithinRangeConstraintTooManyYears() {
		// DoB March 1820 and DoD September 1970 violates diff within range [0, 150] years
		// because March is before September, even though the year difference is 150 (within range)
		$fall1970Statement = NewStatement::forProperty( 'P570' )->withValue(
			new TimeValue( '+00000001970-09-01T00:00:00Z', 0, 0, 0, 11, 'http://www.wikidata.org/entity/Q1985727' )
		)->build();
		$entity = NewItem::withId( 'Q1' )
			->andStatement( $fall1970Statement )
			->andStatement( NewStatement::forProperty( 'P569' )->withValue(
				new TimeValue( '+00000001820-03-01T00:00:00Z', 0, 0, 0, 11, 'http://www.wikidata.org/entity/Q1985727' )
			) )
			->build();
		$constraint = $this->getConstraintMock( $this->dob0to150YearsParameters );
		$context = new MainSnakContext( $entity, $fall1970Statement );

		$checkResult = $this->checker->checkConstraint( $context, $constraint );

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

		$checkResult = $this->checker->checkConstraint( new MainSnakContext( $entity, self::$s1970 ), $constraint );

		$this->assertCompliance( $checkResult );
	}

	public function testDiffWithinRangeConstraintGWithinRange() {
		$entity = NewItem::withId( 'Q2' )
			->andStatement(
				NewStatement::forProperty( 'P2067' )
					->withValue( UnboundedQuantityValue::newFromNumber( 5000, 'g' ) )
			)
			->build();
		$snak = new PropertyValueSnak(
			new PropertyId( 'P2068' ),
			UnboundedQuantityValue::newFromNumber( 5500.0, 'g' )
		);
		$context = new MainSnakContext( $entity, new Statement( $snak ) );
		$constraintParameters = array_merge(
			$this->rangeParameter(
				'quantity',
				UnboundedQuantityValue::newFromNumber( 0, 'kg' ),
				UnboundedQuantityValue::newFromNumber( 1, 'kg' )
			),
			$this->propertyParameter( 'P2067' )
		);
		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( $context, $constraint );

		$this->assertCompliance( $checkResult );
	}

	public function testDiffWithinRangeConstraintGWithinTRange() {
		$entity = NewItem::withId( 'Q2' )
			->andStatement(
				NewStatement::forProperty( 'P2067' )
					->withValue( UnboundedQuantityValue::newFromNumber( 5000, 'g' ) )
			)
			->build();
		$snak = new PropertyValueSnak(
			new PropertyId( 'P2068' ),
			UnboundedQuantityValue::newFromNumber( 5500.0, 'g' )
		);
		$context = new MainSnakContext( $entity, new Statement( $snak ) );
		$constraintParameters = array_merge(
			$this->rangeParameter(
				'quantity',
				UnboundedQuantityValue::newFromNumber( 0, 't' ),
				UnboundedQuantityValue::newFromNumber( 0.001, 't' )
			),
			$this->propertyParameter( 'P2067' )
		);
		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( $context, $constraint );

		$this->assertCompliance( $checkResult );
	}

	public function testDiffWithinRangeConstraintGTooBig() {
		$entity = NewItem::withId( 'Q2' )
			->andStatement(
				NewStatement::forProperty( 'P2067' )
					->withValue( UnboundedQuantityValue::newFromNumber( 5000, 'g' ) )
			)
			->build();
		$snak = new PropertyValueSnak(
			new PropertyId( 'P2068' ),
			UnboundedQuantityValue::newFromNumber( 6500.0, 'g' )
		);
		$context = new MainSnakContext( $entity, new Statement( $snak ) );
		$constraintParameters = array_merge(
			$this->rangeParameter(
				'quantity',
				UnboundedQuantityValue::newFromNumber( 0, 'kg' ),
				UnboundedQuantityValue::newFromNumber( 1, 'kg' )
			),
			$this->propertyParameter( 'P2067' )
		);
		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( $context, $constraint );

		$this->assertViolation( $checkResult, 'wbqc-violation-message-diff-within-range' );
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

		$checkResult = $this->checker->checkConstraint( new MainSnakContext( $entity, self::$s1970 ), $constraint );

		$this->assertCompliance( $checkResult );
	}

	public function testDiffWithinRangeConstraintWithoutStatement() {
		$entity = self::$i1970->build();
		$constraint = $this->getConstraintMock( $this->dob0to150Parameters );

		$checkResult = $this->checker->checkConstraint( new MainSnakContext( $entity, self::$s1970 ), $constraint );

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

		$checkResult = $this->checker->checkConstraint( new MainSnakContext( $entity, self::$s1970 ), $constraint );

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

		$checkResult = $this->checker->checkConstraint( new MainSnakContext( $entity, self::$s1970 ), $constraint );

		$this->assertViolation( $checkResult, 'wbqc-violation-message-diff-within-range-property-must-exist' );
	}

	public function testDiffWithinRangeConstraintWrongTypeOfProperty() {
		$entity = self::$i1970
			->andStatement( NewStatement::forProperty( 'P569' )->withValue( '1900' ) )
			->build();
		$constraint = $this->getConstraintMock( $this->dob0to150Parameters );

		$checkResult = $this->checker->checkConstraint( new MainSnakContext( $entity, self::$s1970 ), $constraint );

		$this->assertViolation( $checkResult, 'wbqc-violation-message-diff-within-range-must-have-equal-types' );
	}

	public function testDiffWithinRangeConstraintNoValueSnak() {
		$entity = self::$i1970->build();
		$statement = NewStatement::noValueFor( 'P1' )->build();
		$constraint = $this->getConstraintMock( $this->dob0to150Parameters );

		$checkResult = $this->checker->checkConstraint( new MainSnakContext( $entity, $statement ), $constraint );

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

		$checkResult = $this->checker->checkConstraint( new MainSnakContext( $entity, self::$s1970 ), $constraint );

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

		$checkResult = $this->checker->checkConstraint( new MainSnakContext( $entity, self::$s1970 ), $constraint );

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

		$checkResult = $this->checker->checkConstraint( new MainSnakContext( $entity, self::$s1970 ), $constraint );

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

		$checkResult = $this->checker->checkConstraint( new MainSnakContext( $entity, self::$s1970 ), $constraint );

		$this->assertViolation( $checkResult, 'wbqc-violation-message-diff-within-range-rightopen' );
	}

	public function testDiffWithinRangeConstraintDeprecatedStatement() {
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
