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
	 * @dataProvider getComparativeValueProvider
	 */
	public function testGetComparativeValue( $expected, DataValue $dataValue ) {
		$rangeCheckHelper = new RangeCheckerHelper();
		$comparativeValue = $rangeCheckHelper->getComparativeValue( $dataValue );

		$this->assertSame( $expected, $comparativeValue );
	}

	public function getComparativeValueProvider() {
		$cases = [
			[ '+1970-01-01T00:00:00Z', $this->getTimeValue() ],
			[ '+42', $this->getQuantityValue() ],
			[ '+9000', UnboundedQuantityValue::newFromNumber( '+9000' ) ]
		];

		return $cases;
	}

	/**
	 * @expectedException InvalidArgumentException
	 */
	public function testGetComparativeValue_unsupportedDataValueTypeThrowsException() {
		$rangeCheckHelper = new RangeCheckerHelper();
		$rangeCheckHelper->getComparativeValue( new StringValue( 'kittens' ) );
	}

	private function getTimeValue() {
		return new TimeValue(
			'+00000001970-01-01T00:00:00Z',
			0,
			0,
			0,
			11,
			'http://www.wikidata.org/entity/Q1985727'
		);
	}

	private function getQuantityValue() {
		$decimalValue = new DecimalValue( 42 );

		return new QuantityValue( $decimalValue, '1', $decimalValue, $decimalValue );
	}

}
