<?php
namespace WikibaseQuality\ConstraintReport\Test\Helper;

use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper;


/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper
 *
 * @group WikibaseQualityConstraints
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class ConstraintReportHelperTest extends \MediaWikiTestCase {

	private $helper;

	protected function setUp() {
		parent::setUp();
		$this->helper = new ConstraintReportHelper();
	}

	protected function tearDown() {
		parent::tearDown();
		unset( $this->helper );
	}

	public function testRemoveBrackets() {
		$templateString = '{{Q|1234}}, {{Q|42}}';
		$expected = 'Q1234, Q42';
		$this->assertEquals( $expected, $this->helper->removeBrackets( $templateString ) );
	}

	public function testStringToArray() {
		$templateString = '{{Q|1234}}, {{Q|42}}';
		$expected = array ( 'Q1234', 'Q42' );
		$this->assertEquals( $expected, $this->helper->stringToArray( $templateString ) );
	}

	public function testEmptyStringToArray() {
		$templateString = '';
		$expected = array ( '' );
		$this->assertEquals( $expected, $this->helper->stringToArray( $templateString ) );
	}

	public function testGetPropertyOfJson() {
		$json = json_decode( json_encode( array ( 'namespace' => 'File' ) ) );
		$this->assertEquals( 'File', $this->helper->getParameterFromJson( $json, 'namespace' ) );
		$this->assertEquals( null, $this->helper->getParameterFromJson( $json, 'Does not exist' ) );
	}

	public function testParseSingleParameter() {
		$parameter = 'P1';
		$this->assertEquals( array ( new PropertyId( $parameter ) ), $this->helper->parseSingleParameter( $parameter ) );
	}

	public function testParseNullParameter() {
		$parameter = null;
		$this->assertEquals( array ( 'none' ), $this->helper->parseSingleParameter( $parameter ) );
	}

	public function testParseNullParameterArray() {
		$parameter = array ( '' );
		$this->assertEquals( array ( 'none' ), $this->helper->parseParameterArray( $parameter ) );
	}

	public function testParseParameterArray() {
		$parameter = array ( 'Q1', 'Q2' );
		$this->assertEquals( array (
								 new ItemId( 'Q1' ),
								 new ItemId( 'Q2' )
							 ), $this->helper->parseParameterArray( $parameter ) );
	}

	public function testParseParameterString() {
		$parameter = 'instance';
		$this->assertEquals( array ( 'instance' ), $this->helper->parseSingleParameter( $parameter, true ) );
	}

	public function testParseParameterUnknownParameter() {
		$parameter = 'R1';
		$this->assertEquals( array ( '' ), $this->helper->parseSingleParameter( $parameter ) );
	}
}