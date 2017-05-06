<?php

namespace WikibaseQuality\ConstraintReport\Test\Helper;

use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;

/**
 * @covers \WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser
 *
 * @group WikibaseQualityConstraints
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class ConstraintParameterParserTest extends \MediaWikiLangTestCase {

	/**
	 * @var ConstraintParameterParser
	 */
	private $helper;

	protected function setUp() {
		parent::setUp();
		$this->helper = new ConstraintParameterParser();
	}

	protected function tearDown() {
		parent::tearDown();
		unset( $this->helper );
	}

	public function testParseSingleParameter() {
		$parameter = 'P1';
		$this->assertEquals( [ new PropertyId( $parameter ) ], $this->helper->parseSingleParameter( $parameter ) );
	}

	public function testParseNullParameter() {
		$parameter = null;
		$this->assertEquals( [ 'none' ], $this->helper->parseSingleParameter( $parameter ) );
	}

	public function testParseNullParameterArray() {
		$parameter = [ '' ];
		$this->assertEquals( [ 'none' ], $this->helper->parseParameterArray( $parameter ) );
	}

	public function testParseParameterArray() {
		$parameter = [ 'Q1', 'Q2' ];
		$this->assertEquals( [
			new ItemId( 'Q1' ),
			new ItemId( 'Q2' )
		], $this->helper->parseParameterArray( $parameter ) );
	}

	public function testParseParameterString() {
		$parameter = 'instance';
		$this->assertEquals( [ 'instance' ], $this->helper->parseSingleParameter( $parameter, true ) );
	}

	public function testParseParameterUnknownParameter() {
		$parameter = 'R1';
		$this->assertEquals( [ '' ], $this->helper->parseSingleParameter( $parameter ) );
	}

}
