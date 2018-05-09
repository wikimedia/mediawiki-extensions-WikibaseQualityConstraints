<?php

namespace WikibaseQuality\ConstraintReport\Tests\RangeChecker;

use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Services\Lookup\InMemoryDataTypeLookup;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use DataValues\DecimalValue;
use DataValues\QuantityValue;
use DataValues\TimeValue;
use DataValues\UnboundedQuantityValue;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\Lib\Units\CSVUnitStorage;
use Wikibase\Lib\Units\UnitConverter;
use Wikibase\Repo\Tests\NewItem;
use Wikibase\Repo\Tests\NewStatement;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\RangeChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\MainSnakContext;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\RangeCheckerHelper;
use WikibaseQuality\ConstraintReport\Tests\ConstraintParameters;
use WikibaseQuality\ConstraintReport\Tests\Fake\FakeSnakContext;
use WikibaseQuality\ConstraintReport\Tests\ResultAssertions;

/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\RangeChecker
 *
 * @group WikibaseQualityConstraints
 *
 * @author BP2014N1
 * @license GPL-2.0-or-later
 */
class RangeCheckerTest extends \MediaWikiTestCase {

	use ConstraintParameters, ResultAssertions;

	/**
	 * @var TimeValue
	 */
	private $timeValue;

	/**
	 * @var RangeChecker
	 */
	private $checker;

	protected function setUp() {
		parent::setUp();
		$this->timeValue = new TimeValue( '+00000001970-01-01T00:00:00Z', 0, 0, 0, TimeValue::PRECISION_DAY, TimeValue::CALENDAR_GREGORIAN );
		$rangeCheckerHelper = new RangeCheckerHelper(
			$this->getDefaultConfig(),
			new UnitConverter( new CSVUnitStorage( __DIR__ . '/units.csv' ), '' )
		);
		$dataTypeLookup = new InMemoryDataTypeLookup();
		$dataTypeLookup->setDataTypeForProperty( new PropertyId( 'P1' ), 'time' );
		$dataTypeLookup->setDataTypeForProperty( new PropertyId( 'P2' ), 'quantity' );
		$this->checker = new RangeChecker(
			$dataTypeLookup,
			$this->getConstraintParameterParser(),
			$rangeCheckerHelper
		);
	}

	public function testRangeConstraintWithinRange() {
		$value = new DecimalValue( 3.1415926536 );
		$snak = new PropertyValueSnak( new PropertyId( 'P1457' ), new QuantityValue( $value, '1', $value, $value ) );
		$constraintParameters = $this->rangeParameter( 'quantity', 0, 10 );
		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new FakeSnakContext( $snak ), $constraint );
		$this->assertCompliance( $checkResult );
	}

	public function testRangeConstraintTooSmall() {
		$value = new DecimalValue( 42 );
		$snak = new PropertyValueSnak( new PropertyId( 'P1457' ), new QuantityValue( $value, '1', $value, $value ) );
		$constraintParameters = $this->rangeParameter( 'quantity', 100, 1000 );
		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new FakeSnakContext( $snak ), $constraint );
		$this->assertViolation( $checkResult, 'wbqc-violation-message-range-quantity-closed' );
	}

	public function testRangeConstraintTooBig() {
		$value = new DecimalValue( 3.141592 );
		$snak = new PropertyValueSnak( new PropertyId( 'P1457' ), new QuantityValue( $value, '1', $value, $value ) );
		$constraintParameters = $this->rangeParameter( 'quantity', 0, 1 );
		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new FakeSnakContext( $snak ), $constraint );
		$this->assertViolation( $checkResult, 'wbqc-violation-message-range-quantity-closed' );
	}

	public function testRangeConstraintGWithinRange() {
		$value = new DecimalValue( 500.0 );
		$snak = new PropertyValueSnak(
			new PropertyId( 'P2067' ),
			new QuantityValue( $value, 'g', $value, $value )
		);
		$constraint = $this->getConstraintMock( $this->rangeParameter( 'quantity', 0, 1 ) );

		$checkResult = $this->checker->checkConstraint( new FakeSnakContext( $snak ), $constraint );

		$this->assertCompliance( $checkResult );
	}

	public function testRangeConstraintGTooBig() {
		$value = new DecimalValue( 2000.0 );
		$snak = new PropertyValueSnak(
			new PropertyId( 'P2067' ),
			new QuantityValue( $value, 'g', $value, $value )
		);
		$constraint = $this->getConstraintMock( $this->rangeParameter( 'quantity', 0, 1 ) );

		$checkResult = $this->checker->checkConstraint( new FakeSnakContext( $snak ), $constraint );

		$this->assertViolation( $checkResult, 'wbqc-violation-message-range-quantity-closed' );
	}

	public function testRangeConstraintTimeWithinRange() {
		$snak = new PropertyValueSnak( new PropertyId( 'P1457' ), $this->timeValue );
		$constraintParameters = $this->rangeParameter( 'date', '1960', '1980' );
		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new FakeSnakContext( $snak ), $constraint );
		$this->assertCompliance( $checkResult );
		$this->assertNull( $checkResult->getMetadata()->getDependencyMetadata()->getFutureTime() );
	}

	public function testRangeConstraintTimeWithinRangeToNow() {
		$snak = new PropertyValueSnak( new PropertyId( 'P1457' ), $this->timeValue );
		$constraintParameters = $this->rangeParameter( 'date', '1960', 'now' );
		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new FakeSnakContext( $snak ), $constraint );
		$this->assertCompliance( $checkResult );
		$this->assertNull( $checkResult->getMetadata()->getDependencyMetadata()->getFutureTime() );
	}

	public function testRangeConstraintTimeWithinYearRange() {
		$snak = new PropertyValueSnak( new PropertyId( 'P1457' ), $this->timeValue );
		$constraintParameters = $this->rangeParameter( 'date', '1960', '1980' );
		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new FakeSnakContext( $snak ), $constraint );
		$this->assertCompliance( $checkResult );
	}

	public function testRangeConstraintTimeWithinYearMonthDayRange() {
		$snak = new PropertyValueSnak( new PropertyId( 'P1457' ), $this->timeValue );
		$constraintParameters = $this->rangeParameter( 'date', '1969-12-31', '1970-01-02' );
		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new FakeSnakContext( $snak ), $constraint );
		$this->assertCompliance( $checkResult );
	}

	public function testRangeConstraintTimeTooEarly() {
		$snak = new PropertyValueSnak( new PropertyId( 'P1457' ), $this->timeValue );
		$constraintParameters = $this->rangeParameter( 'date', '1975', '1980' );
		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new FakeSnakContext( $snak ), $constraint );
		$this->assertViolation( $checkResult, 'wbqc-violation-message-range-time-closed' );
	}

	public function testRangeConstraintTimeTooLate() {
		$snak = new PropertyValueSnak( new PropertyId( 'P1457' ), $this->timeValue );
		$constraintParameters = $this->rangeParameter( 'date', '1960', '1965' );
		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new FakeSnakContext( $snak ), $constraint );
		$this->assertViolation( $checkResult, 'wbqc-violation-message-range-time-closed' );
	}

	public function testRangeConstraintNoValueSnak() {
		$snak = new PropertyNoValueSnak( new PropertyId( 'P1' ) );
		$constraintParameters = [];
		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new FakeSnakContext( $snak ), $constraint );
		$this->assertCompliance( $checkResult );
	}

	public function testRangeConstraintLeftOpenWithinRange() {
		$snak = new PropertyValueSnak( new PropertyId( 'P1457' ), UnboundedQuantityValue::newFromNumber( -10 ) );
		$constraintParameters = $this->rangeParameter( 'quantity', null, 0 );

		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new FakeSnakContext( $snak ), $constraint );

		$this->assertCompliance( $checkResult );
	}

	public function testRangeConstraintLeftOpenTooSmall() {
		$snak = new PropertyValueSnak( new PropertyId( 'P1457' ), UnboundedQuantityValue::newFromNumber( 10 ) );
		$constraintParameters = $this->rangeParameter( 'quantity', null, 0 );

		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new FakeSnakContext( $snak ), $constraint );

		$this->assertViolation( $checkResult, 'wbqc-violation-message-range-quantity-leftopen' );
	}

	public function testRangeConstraintRightOpenWithinRange() {
		$snak = new PropertyValueSnak( new PropertyId( 'P1457' ), UnboundedQuantityValue::newFromNumber( 10 ) );
		$constraintParameters = $this->rangeParameter( 'quantity', 0, null );

		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new FakeSnakContext( $snak ), $constraint );

		$this->assertCompliance( $checkResult );
	}

	public function testRangeConstraintRightOpenTooSmall() {
		$snak = new PropertyValueSnak( new PropertyId( 'P1457' ), UnboundedQuantityValue::newFromNumber( -10 ) );
		$constraintParameters = $this->rangeParameter( 'quantity', 0, null );

		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new FakeSnakContext( $snak ), $constraint );

		$this->assertViolation( $checkResult, 'wbqc-violation-message-range-quantity-rightopen' );
	}

	public function testRangeConstraintLeftOpenTimeWithinRange() {
		$nineteenFourtyNine = new TimeValue(
			'+00000001949-01-01T00:00:00Z',
			0, 0, 0,
			TimeValue::PRECISION_YEAR,
			TimeValue::CALENDAR_GREGORIAN
		);
		$snak = new PropertyValueSnak( new PropertyId( 'P1457' ), $nineteenFourtyNine );
		$constraintParameters = $this->rangeParameter( 'time', null, $this->timeValue );

		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new FakeSnakContext( $snak ), $constraint );

		$this->assertCompliance( $checkResult );
	}

	public function testRangeConstraintLeftOpenTimeTooLate() {
		$nineteenEightyFour = new TimeValue(
			'+00000001984-01-01T00:00:00Z',
			0, 0, 0,
			TimeValue::PRECISION_YEAR,
			TimeValue::CALENDAR_GREGORIAN
		);
		$snak = new PropertyValueSnak( new PropertyId( 'P1457' ), $nineteenEightyFour );
		$constraintParameters = $this->rangeParameter( 'time', null, $this->timeValue );

		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new FakeSnakContext( $snak ), $constraint );

		$this->assertViolation( $checkResult, 'wbqc-violation-message-range-time-leftopen' );
	}

	public function testRangeConstraintRightOpenTimeWithinRange() {
		$nineteenEightyFour = new TimeValue(
			'+00000001984-01-01T00:00:00Z',
			0, 0, 0,
			TimeValue::PRECISION_YEAR,
			TimeValue::CALENDAR_GREGORIAN
		);
		$snak = new PropertyValueSnak( new PropertyId( 'P1457' ), $nineteenEightyFour );
		$constraintParameters = $this->rangeParameter( 'time', $this->timeValue, null );

		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new FakeSnakContext( $snak ), $constraint );

		$this->assertCompliance( $checkResult );
	}

	public function testRangeConstraintRightOpenTimeTooEarly() {
		$nineteenFourtyNine = new TimeValue(
			'+00000001949-01-01T00:00:00Z',
			0, 0, 0,
			TimeValue::PRECISION_YEAR,
			TimeValue::CALENDAR_GREGORIAN
		);
		$snak = new PropertyValueSnak( new PropertyId( 'P1457' ), $nineteenFourtyNine );
		$constraintParameters = $this->rangeParameter( 'time', $this->timeValue, null );

		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new FakeSnakContext( $snak ), $constraint );

		$this->assertViolation( $checkResult, 'wbqc-violation-message-range-time-rightopen' );
	}

	public function testRangeConstraintOutsideClosedRangeToNow() {
		$misspelledNineteenEightyFour = new TimeValue(
			'+00000019984-01-01T00:00:00Z',
			0, 0, 0,
			TimeValue::PRECISION_YEAR,
			TimeValue::CALENDAR_GREGORIAN
		);
		$snak = new PropertyValueSnak( new PropertyId( 'P1457' ), $misspelledNineteenEightyFour );
		$constraintParameters = $this->rangeParameter( 'time', $this->timeValue, 'now' );

		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new FakeSnakContext( $snak ), $constraint );

		$this->assertViolation( $checkResult, 'wbqc-violation-message-range-time-closed-rightnow' );
		$futureTime = $checkResult->getMetadata()->getDependencyMetadata()->getFutureTime();
		$this->assertNotNull( $futureTime );
		$this->assertSame( $misspelledNineteenEightyFour->getTime(), $futureTime->getTime() );
	}

	public function testRangeConstraintOutsideOpenRangeToNow() {
		$misspelledNineteenEightyFour = new TimeValue(
			'+00000019984-01-01T00:00:00Z',
			0, 0, 0,
			TimeValue::PRECISION_YEAR,
			TimeValue::CALENDAR_GREGORIAN
		);
		$snak = new PropertyValueSnak( new PropertyId( 'P1457' ), $misspelledNineteenEightyFour );
		$constraintParameters = $this->rangeParameter( 'time', null, 'now' );

		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new FakeSnakContext( $snak ), $constraint );

		$this->assertViolation( $checkResult, 'wbqc-violation-message-range-time-leftopen-rightnow' );
		$futureTime = $checkResult->getMetadata()->getDependencyMetadata()->getFutureTime();
		$this->assertNotNull( $futureTime );
		$this->assertSame( $misspelledNineteenEightyFour->getTime(), $futureTime->getTime() );
	}

	public function testRangeConstraintOutsideClosedRangeFromNow() {
		$farFuture = new TimeValue(
			'+00000019984-01-01T00:00:00Z',
			0, 0, 0,
			TimeValue::PRECISION_YEAR,
			TimeValue::CALENDAR_GREGORIAN
		);
		$nineteenEightyFour = new TimeValue(
			'+00000001984-01-01T00:00:00Z',
			0, 0, 0,
			TimeValue::PRECISION_YEAR,
			TimeValue::CALENDAR_GREGORIAN
		);
		$snak = new PropertyValueSnak( new PropertyId( 'P1457' ), $nineteenEightyFour );
		$constraintParameters = $this->rangeParameter( 'time', 'now', $farFuture );

		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new FakeSnakContext( $snak ), $constraint );

		$this->assertViolation( $checkResult, 'wbqc-violation-message-range-time-closed-leftnow' );
		$this->assertNull( $checkResult->getMetadata()->getDependencyMetadata()->getFutureTime() );
	}

	public function testRangeConstraintOutsideOpenRangeFromNow() {
		$nineteenEightyFour = new TimeValue(
			'+00000001984-01-01T00:00:00Z',
			0, 0, 0,
			TimeValue::PRECISION_YEAR,
			TimeValue::CALENDAR_GREGORIAN
		);
		$snak = new PropertyValueSnak( new PropertyId( 'P1457' ), $nineteenEightyFour );
		$constraintParameters = $this->rangeParameter( 'time', 'now', null );

		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new FakeSnakContext( $snak ), $constraint );

		$this->assertViolation( $checkResult, 'wbqc-violation-message-range-time-rightopen-leftnow' );
		$this->assertNull( $checkResult->getMetadata()->getDependencyMetadata()->getFutureTime() );
	}

	public function testRangeConstraintDeprecatedStatement() {
		$statement = NewStatement::noValueFor( 'P1' )
				   ->withDeprecatedRank()
				   ->build();
		$constraint = $this->getConstraintMock( [] );
		$entity = NewItem::withId( 'Q1' )
				->build();

		$checkResult = $this->checker->checkConstraint( new MainSnakContext( $entity, $statement ), $constraint );

		$this->assertDeprecation( $checkResult );
	}

	/**
	 * @dataProvider providePropertyIds
	 */
	public function testCheckConstraintParameters( $propertyId ) {
		$constraint = $this->getConstraintMock( [], $propertyId );

		$result = $this->checker->checkConstraintParameters( $constraint );

		$this->assertCount( 1, $result );
	}

	public function providePropertyIds() {
		return [ [ 'P1' ], [ 'P2' ] ];
	}

	/**
	 * @param string[] $parameters
	 * @param string $propertyId
	 *
	 * @return Constraint
	 */
	private function getConstraintMock( array $parameters, $propertyId = 'P1' ) {
		$mock = $this
			->getMockBuilder( Constraint::class )
			->disableOriginalConstructor()
			->getMock();
		$mock->expects( $this->any() )
			 ->method( 'getConstraintParameters' )
			 ->will( $this->returnValue( $parameters ) );
		$mock->expects( $this->any() )
			 ->method( 'getConstraintTypeItemId' )
			 ->will( $this->returnValue( 'Q21510860' ) );
		$mock->expects( $this->any() )
			 ->method( 'getPropertyId' )
			 ->will( $this->returnValue( new PropertyId( $propertyId ) ) );

		return $mock;
	}

	/**
	 * @return EntityDocument
	 */
	private function getEntity() {
		return new Item( new ItemId( 'Q1' ) );
	}

}
