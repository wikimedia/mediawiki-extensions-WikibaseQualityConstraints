<?php

namespace WikibaseQuality\ConstraintReport\Test\RangeChecker;

use DataValues\DecimalValue;
use DataValues\QuantityValue;
use DataValues\TimeValue;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\RangeCheckerHelper;


/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\RangeCheckerHelper
 *
 * @group WikibaseQualityConstraints
 *
 * @uses   WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 * @uses   WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class RangeCheckerHelperTest extends \MediaWikiTestCase {

	private $helper;
	private $time;
	private $quantity;

	protected function setUp() {
		parent::setUp();
		$this->helper = new RangeCheckerHelper();
		$this->time = new TimeValue( '+00000001970-01-01T00:00:00Z', 0, 0, 0, 11, 'http://www.wikidata.org/entity/Q1985727' );
		$value = new DecimalValue( 3.1415926536 );
		$this->quantity = new QuantityValue( $value, '1', $value, $value );
	}

	protected function tearDown() {
		unset( $this->helper );
		parent::tearDown();
	}

	public function testGetComparativeValueTimeValid() {
		$this->assertEquals( '+1970-01-01T00:00:00Z', $this->helper->getComparativeValue( $this->time ) );
	}

	public function testGetComparativeValueTimeInvalid() {
		$this->assertNotEquals( '1.1.1970', $this->helper->getComparativeValue( $this->time ) );

	}

	public function testGetComparativeValueQuantityValid() {
		$this->assertEquals( '3.1415926536', $this->helper->getComparativeValue( $this->quantity ) );
	}

	public function testGetComparativeValueQuantityInvalid() {
		$this->assertNotEquals( $this->quantity, $this->helper->getComparativeValue( $this->quantity ) );
	}
}