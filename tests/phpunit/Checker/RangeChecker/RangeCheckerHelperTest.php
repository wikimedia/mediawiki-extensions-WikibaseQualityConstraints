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
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\RangeCheckerHelper;

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

	/**
	 * @dataProvider getComparisonProvider
	 */
	public function testGetComparison( $expected, DataValue $lhs, DataValue $rhs ) {
		$this->assertContains( $expected, [ -1, 0, 1 ], '$expected must be -1, 0, or 1' );
		$rangeCheckHelper = new RangeCheckerHelper();
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
		];

		return $cases;
	}

	/**
	 * @dataProvider getDifferenceProvider
	 */
	public function testGetDifference( $expected, DataValue $minuend, DataValue $subtrahend ) {
		$rangeCheckHelper = new RangeCheckerHelper();
		$actual = $rangeCheckHelper->getDifference( $minuend, $subtrahend );
		$this->assertSame( $expected, $actual );
	}

	public function getDifferenceProvider() {
		$cases = [
			[ -1.0, $this->getTimeValue( 1970 ), $this->getTimeValue( 1971 ) ],
			[ 1.0, $this->getTimeValue( 1971 ), $this->getTimeValue( 1970 ) ],
			[ -1.0, $this->getTimeValue( -1 ), $this->getTimeValue( 1 ) ],
			[ 1.0, $this->getTimeValue( 1 ), $this->getTimeValue( -1 ) ],
			[ -1.0, $this->getTimeValue( -1971 ), $this->getTimeValue( -1970 ) ],
			[ 1.0, $this->getTimeValue( -1970 ), $this->getTimeValue( -1971 ) ],
			[ -1295.0, $this->getQuantityValue( 42.0 ), $this->getQuantityValue( 1337.0 ) ],
			[ 1295.0, $this->getQuantityValue( 1337.0 ), $this->getQuantityValue( 42.0 ) ],
			[ -1295.0, $this->getQuantityValue( 42.0 ), $this->getUnboundedQuantityValue( 1337.0 ) ],
			[ -1295.0, $this->getUnboundedQuantityValue( 42.0 ), $this->getQuantityValue( 1337.0 ) ]
		];

		return $cases;
	}

	/**
	 * @expectedException InvalidArgumentException
	 */
	public function testGetComparison_unsupportedDataValueTypeThrowsException() {
		$rangeCheckHelper = new RangeCheckerHelper();
		$rangeCheckHelper->getComparison( new StringValue( 'kittens' ), new StringValue( 'puppies' ) );
	}

	/**
	 * @expectedException InvalidArgumentException
	 */
	public function testGetComparison_differingDataValueTypeThrowsException() {
		$rangeCheckHelper = new RangeCheckerHelper();
		$rangeCheckHelper->getComparison( $this->getQuantityValue( 42.0 ), $this->getTimeValue( 1970 ) );
	}

	public function testParseTime_year() {
		$rangeCheckHelper = new RangeCheckerHelper();
		$this->assertSame( '+1970-00-00T00:00:00Z', $rangeCheckHelper->parseTime( '1970' )->getTime() );
	}

	public function testParseTime_yearMonthDay() {
		$rangeCheckHelper = new RangeCheckerHelper();
		$this->assertSame( '+1970-01-01T00:00:00Z', $rangeCheckHelper->parseTime( '1970-01-01' )->getTime() );
	}

	/**
	 * @param integer $year
	 *
	 * @return TimeValue
	 */
	private function getTimeValue( $year ) {
		$yearString = $year > 0 ? "+$year" : "$year";
		return new TimeValue(
			"$yearString-01-01T00:00:00Z",
			0,
			0,
			0,
			11,
			'http://www.wikidata.org/entity/Q1985727'
		);
	}

	/**
	 * @param float $amount
	 *
	 * @return QuantityValue
	 */
	private function getQuantityValue( $amount ) {
		$decimalValue = new DecimalValue( $amount );

		return new QuantityValue( $decimalValue, '1', $decimalValue, $decimalValue );
	}

	/**
	 * @param float $amount
	 *
	 * @return UnboundedQuantityValue
	 */
	private function getUnboundedQuantityValue( $amount ) {
		return UnboundedQuantityValue::newFromNumber( $amount );
	}

}
