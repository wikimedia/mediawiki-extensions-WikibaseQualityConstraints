<?php

namespace WikibaseQuality\ConstraintReport\Test\Violations;

use Language;
use WikibaseQuality\ConstraintReport\Violations\ConstraintViolationFormatter;


/**
 * @covers WikibaseQuality\ConstraintReport\Violations\ConstraintViolationFormatter
 *
 * @group WikibaseQualityConstraints
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class ConstraintViolationFormatterTest extends \MediaWikiTestCase {

    /**
     * @var array
     */
    private $types;

    /**
     * @var ConstraintViolationFormatter
     */
    private $violationFormatter;

    public function setUp(){
        parent::setUp();

        $this->types = array(
            'foo',
            'bar',
            'foobar'
        );
        $this->violationFormatter = new ConstraintViolationFormatter( $this->types );
    }

    public function tearDown() {
        unset( $this->violationFormatter );

        parent::tearDown();
    }

    /**
     * @dataProvider isFormatterForDataProvider
     */
    public function testIsFormatterFor( $expectedResult, $violation ){
        $actualResult = $this->violationFormatter->isFormatterFor( $violation );

        $this->assertEquals( $expectedResult, $actualResult );
    }

    /**
     * Test cases for testIsFormatterFor
     * @return array
     */
    public function isFormatterForDataProvider() {
        return array(
            array(
                true,
                $this->getViolationMock( 'wbqc|foobar' )
            ),
            array(
                false,
                $this->getViolationMock( 'wbqev|foobar' )
            )
        );
    }


    /**
     * @dataProvider formatAdditionalInformationDataProvider
     */
    public function testFormatAdditionalInformation( $expectedResult, $violation, $expectedException = null ) {
        $this->setExpectedException( $expectedException );

        global $wgLang;
        $wgLang = Language::factory( 'qqx' );
        $actualResult = $this->violationFormatter->formatAdditionalInformation( $violation );

        $this->assertEquals( $expectedResult, $actualResult );
    }

    /**
     * Test cases for testFormatAdditionalInformation
     * @return array
     */
    public function formatAdditionalInformationDataProvider() {
        return array(
            array(
                '<p><span class="wbq-violations-additional-information-header">(wbqc-violation-header-parameters)</span><br />none</p>',
                $this->getViolationMock(
                    'wbqc|foobar',
                    array()
                )
            ),
            array(
                '<p><span class="wbq-violations-additional-information-header">(wbqc-violation-header-parameters)</span><br />foo: bar</p>',
                $this->getViolationMock(
                    'wbqc|foobar',
                    array(
                        'parameters' => array(
                            'foo' => 'bar'
                        )
                    )
                )
            ),
            array(
                '<p><span class="wbq-violations-additional-information-header">(wbqc-violation-header-parameters)</span><br />foo: bar<br />foobar: fubar</p>',
                $this->getViolationMock(
                    'wbqc|foobar',
                    array(
                        'parameters' => array(
                            'foo' => 'bar',
                            'foobar' => 'fubar'
                        )
                    )
                )
            ),
            array(
                '',
                $this->getViolationMock(
                    'wbqev|foobar',
                    array()
                ),
                'InvalidArgumentException'
            )
        );
    }

    public function testGetIconClass() {
        $actualResult = $this->violationFormatter->getIconClass( $this->getViolationMock( 'wbqc|foobar', array() ) );

		$this->assertTrue( is_string( $actualResult ) );
    }

    public function testGetShortMessage() {
        $actualResult = $this->violationFormatter->getShortMessage( $this->getViolationMock() );
        $expectedResult = '(wbqc-violation-message)';

        $this->assertEquals( $expectedResult, $actualResult );
    }

    public function testGetLongMessage() {
        $actualResult = $this->violationFormatter->getLongMessage( $this->getViolationMock(), true );
        $expectedResult = '(wbqc-violation-message)';

        $this->assertEquals( $expectedResult, $actualResult );
    }


    private function getViolationMock( $constraintId = null, $additionalInformation = null ) {
        $mock = $this->getMockBuilder( 'WikibaseQuality\Violations\Violation' )
            ->setMethods( array( 'getConstraintId', 'getAdditionalInfo', 'getConstraintTypeEntityId' ) )
            ->disableOriginalConstructor()
            ->getMock();
        $mock->expects( $this->any() )
            ->method( 'getConstraintId' )
            ->willReturn( $constraintId );
        $mock->expects( $this->any() )
            ->method( 'getAdditionalInfo' )
            ->willReturn( $additionalInformation );
        $mock->expects( $this->any() )
            ->method( 'getConstraintTypeEntityId' )
            ->willReturn( 'Range' );

        return $mock;
    }
}
