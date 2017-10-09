<?php

namespace WikibaseQuality\ConstraintReport\Test\RangeChecker;

use DataValues\DataValue;
use DataValues\DecimalValue;
use DataValues\QuantityValue;
use DataValues\StringValue;
use DataValues\TimeValue;
use DataValues\UnboundedQuantityValue;
use InvalidArgumentException;
use PHPUnit_Framework_TestCase;
use Wikibase\Lib\Units\CSVUnitStorage;
use Wikibase\Lib\Units\UnitConverter;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\RangeCheckerHelper;
use WikibaseQuality\ConstraintReport\Tests\DefaultConfig;

/**
 * @covers \WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\RangeCheckerHelper
 *
 * @group WikibaseQualityConstraints
 *
 * @uses   \WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 * @uses   \WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class RangeCheckerHelperTest extends PHPUnit_Framework_TestCase {

	use DefaultConfig;

	/**
	 * @return RangeCheckerHelper
	 */
	private function getRangeCheckerHelper() {
		return new RangeCheckerHelper(
			$this->getDefaultConfig(),
			new UnitConverter(
				new CSVUnitStorage( __DIR__ . '/units.csv' ),
				''
			)
		);
	}

	/**
	 * @dataProvider getComparisonProvider
	 */
	public function testGetComparison( $expected, DataValue $lhs, DataValue $rhs ) {
		$this->assertContains( $expected, [ -1, 0, 1 ], '$expected must be -1, 0, or 1' );
		$rangeCheckHelper = $this->getRangeCheckerHelper();
		$actual = $rangeCheckHelper->getComparison( $lhs, $rhs );
		switch ( $expected ) {
			case -1:
				$this->assertLessThan( 0, $actual );
				break;
			case 0:
				$this->assertSame( 0, $actual );
				break;
			case 1:
				$this->assertGreaterThan( 0, $actual );
				break;
		}
	}

	public function getComparisonProvider() {
		$cases = [
			[ -1, $this->getTimeValue( 1970 ), $this->getTimeValue( 1971 ) ],
			[ 0, $this->getTimeValue( 1970 ), $this->getTimeValue( 1970 ) ],
			[ 1, $this->getTimeValue( 1971 ), $this->getTimeValue( 1970 ) ],
			[ -1, $this->getQuantityValue( 42.0 ), $this->getQuantityValue( 1337.0 ) ],
			[ 0, $this->getQuantityValue( 42.0 ), $this->getQuantityValue( 42.0 ) ],
			[ 1, $this->getQuantityValue( 1337.0 ), $this->getQuantityValue( 42.0 ) ],
			[ -1, $this->getUnboundedQuantityValue( 42.0 ), $this->getUnboundedQuantityValue( 1337.0 ) ],
			[ 0, $this->getUnboundedQuantityValue( 42.0 ), $this->getUnboundedQuantityValue( 42.0 ) ],
			[ 1, $this->getUnboundedQuantityValue( 1337.0 ), $this->getUnboundedQuantityValue( 42.0 ) ],
			[ -1, $this->getQuantityValue( 500.0, 'g' ), $this->getQuantityValue( 1.0, 'kg' ) ],
			[ 0, $this->getQuantityValue( 1000.0, 'g' ), $this->getQuantityValue( 1.0, 'kg' ) ],
			[ 1, $this->getQuantityValue( 1.5, 'kg' ), $this->getQuantityValue( 1000, 'g' ) ],
		];

		return $cases;
	}

	/**
	 * @dataProvider getDifferenceProvider
	 */
	public function testGetDifference( $expected, DataValue $minuend, DataValue $subtrahend ) {
		$rangeCheckHelper = $this->getRangeCheckerHelper();
		$diff = $rangeCheckHelper->getDifference( $minuend, $subtrahend );
		$actual = $diff->getAmount()->getValueFloat();
		$this->assertSame( (float) $expected, $actual );
	}

	public function getDifferenceProvider() {
		$secondsPerYear = 60 * 60 * 24 * 365;
		$secondsPerLeapYear = 60 * 60 * 24 * 366;
		$cases = [
			'negative year difference, no leap year' => [
				-$secondsPerYear, $this->getTimeValue( 1970 ), $this->getTimeValue( 1971 )
			],
			'positive year difference, no leap year' => [
				$secondsPerYear, $this->getTimeValue( 1971 ), $this->getTimeValue( 1970 )
			],
			'negative year difference, leap year' => [
				-$secondsPerLeapYear, $this->getTimeValue( 1972 ), $this->getTimeValue( 1973 )
			],
			'positive year difference, leap year' => [
				$secondsPerLeapYear, $this->getTimeValue( 1973 ), $this->getTimeValue( 1972 )
			],
			'positive year difference, leap year, excluding leap day' => [
				$secondsPerYear, $this->getTimeValue( 1973, 6 ), $this->getTimeValue( 1972, 6 )
			],
			'negative year difference, across epoch (1 BCE is leap year)' => [
				-$secondsPerLeapYear, $this->getTimeValue( -1 ), $this->getTimeValue( 1 )
			],
			'positive year difference, across epoch (1 BCE is leap year)' => [
				$secondsPerLeapYear, $this->getTimeValue( 1 ), $this->getTimeValue( -1 )
			],
			'negative year difference, before Common Era' => [
				-$secondsPerYear, $this->getTimeValue( -1971 ), $this->getTimeValue( -1970 )
			],
			'positive year difference, before Common Era' => [
				$secondsPerYear, $this->getTimeValue( -1970 ), $this->getTimeValue( -1971 )
			],
			'negative quantity difference' => [
			    -1295, $this->getQuantityValue( 42.0 ), $this->getQuantityValue( 1337.0 )
			],
			'positive quantity difference' => [
				1295, $this->getQuantityValue( 1337.0 ), $this->getQuantityValue( 42.0 )
			],
			'negative quantity difference, bounded/unbounded' => [
				-1295, $this->getQuantityValue( 42.0 ), $this->getUnboundedQuantityValue( 1337.0 )
			],
			'negative quantity difference, unbounded/bounded' => [
				-1295, $this->getUnboundedQuantityValue( 42.0 ), $this->getQuantityValue( 1337.0 )
			],
			'negative quantity difference, gram/kilogram' => [
				-0.5, $this->getQuantityValue( 500.0, 'g' ), $this->getQuantityValue( 1.0, 'kg' )
			],
			'positive quantity difference, gram/gram' => [
				1.0, $this->getQuantityValue( 2000.0, 'g' ), $this->getQuantityValue( 1000, 'g' )
			],
		];

		return $cases;
	}

	/**
	 * @expectedException InvalidArgumentException
	 */
	public function testGetComparison_unsupportedDataValueTypeThrowsException() {
		$rangeCheckHelper = $this->getRangeCheckerHelper();
		$rangeCheckHelper->getComparison( new StringValue( 'kittens' ), new StringValue( 'puppies' ) );
	}

	/**
	 * @expectedException InvalidArgumentException
	 */
	public function testGetComparison_differingDataValueTypeThrowsException() {
		$rangeCheckHelper = $this->getRangeCheckerHelper();
		$rangeCheckHelper->getComparison( $this->getQuantityValue( 42.0 ), $this->getTimeValue( 1970 ) );
	}

	public function testParseTime_year() {
		$rangeCheckHelper = $this->getRangeCheckerHelper();
		$this->assertSame( '+1970-00-00T00:00:00Z', $rangeCheckHelper->parseTime( '1970' )->getTime() );
	}

	public function testParseTime_yearMonthDay() {
		$rangeCheckHelper = $this->getRangeCheckerHelper();
		$this->assertSame( '+1970-01-01T00:00:00Z', $rangeCheckHelper->parseTime( '1970-01-01' )->getTime() );
	}

	/**
	 * @param int $year
	 * @param int $month
	 * @param int $day
	 *
	 * @return TimeValue
	 */
	private function getTimeValue( $year, $month = 1, $day = 1 ) {
		$yearString = $year > 0 ? "+$year" : "$year";
		$monthString = $month < 10 ? "0$month" : "$month";
		$dayString = $day < 10 ? "0$day" : "$day";
		return new TimeValue(
			"{$yearString}-{$monthString}-{$dayString}T00:00:00Z",
			0,
			0,
			0,
			11,
			'http://www.wikidata.org/entity/Q1985727'
		);
	}

	/**
	 * @param float $amount
	 * @param string $unit
	 *
	 * @return QuantityValue
	 */
	private function getQuantityValue( $amount, $unit = '1' ) {
		$decimalValue = new DecimalValue( $amount );

		return new QuantityValue( $decimalValue, $unit, $decimalValue, $decimalValue );
	}

	/**
	 * @param float $amount
	 * @param string $unit
	 *
	 * @return UnboundedQuantityValue
	 */
	private function getUnboundedQuantityValue( $amount, $unit = '1' ) {
		return UnboundedQuantityValue::newFromNumber( $amount, $unit );
	}

}
