<?php

namespace WikibaseQuality\ConstraintReport\Test\Violations;

use Language;
use WikibaseQuality\ConstraintReport\Violations\ConstraintViolationContext;


/**
 * @covers WikibaseQuality\ConstraintReport\Violations\ConstraintViolationContext
 *
 * @group WikibaseQualityConstraints
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class ConstraintViolationContextTest extends \MediaWikiTestCase {

    /**
     * @var array
     */
    private $types;

    /**
     * @var ConstraintViolationContext
     */
    private $violationContext;

    public function setUp(){
        parent::setUp();

        $this->types = array(
            'foo',
            'bar',
            'foobar'
        );
        $this->violationContext = new ConstraintViolationContext( $this->types );
    }

    public function tearDown() {
        unset( $this->violationContext );

        parent::tearDown();
    }


    public function testGetTypes() {
        $actualResult = $this->violationContext->getTypes();

        $this->assertArrayEquals( $this->types, $actualResult );
    }


    /**
     * @dataProvider isContextForDataProvider
     */
    public function testIsContextFor( $expectedResult, $violation ){
        $actualResult = $this->violationContext->isContextFor( $violation );

        $this->assertEquals( $expectedResult, $actualResult );
    }

    /**
     * Test cases for testIsContextFor
     * @return array
     */
    public function isContextForDataProvider() {
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
        $actualResult = $this->violationContext->formatAdditionalInformation( $violation );

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


    public function testGetMessage() {
        $actualResult = $this->violationContext->getMessage( $this->getViolationMock() );
        $expectedResult = '(wbqc-violation-message)';

        $this->assertEquals( $expectedResult, $actualResult );
    }


    private function getViolationMock( $constraintId = null, $additionalInformation = null ) {
        $mock = $this->getMockBuilder( 'WikibaseQuality\Violations\Violation' )
            ->setMethods( array( 'getConstraintId', 'getAdditionalInfo' ) )
            ->disableOriginalConstructor()
            ->getMock();
        $mock->expects( $this->any() )
            ->method( 'getConstraintId' )
            ->willReturn( $constraintId );
        $mock->expects( $this->any() )
            ->method( 'getAdditionalInfo' )
            ->willReturn( $additionalInformation );

        return $mock;
    }
}
