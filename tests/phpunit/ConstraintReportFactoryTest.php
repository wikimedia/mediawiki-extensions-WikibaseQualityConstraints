<?php

namespace WikibaseQuality\ConstraintReport\Test;

use WikibaseQuality\ConstraintReport\ConstraintReportFactory;


/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintReportFactory
 *
 * @group WikibaseQualityConstraints
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class ConstraintReportFactoryTest extends \MediaWikiTestCase {

	public function testGetMap() {
		$map = ConstraintReportFactory::getDefaultInstance()->getConstraintParameterMap();
		$this->assertEquals( array( 'pattern' ), $map['Format'] );
	}

	public function testGetDefaultInstance() {
		$this->assertInstanceOf(
			'WikibaseQuality\ConstraintReport\ConstraintReportFactory',
			ConstraintReportFactory::getDefaultInstance()
		);
	}

	public function testGetConstraintRepository() {
		$this->assertInstanceOf(
			'WikibaseQuality\ConstraintReport\ConstraintRepository',
			ConstraintReportFactory::getDefaultInstance()->getConstraintRepository()
		);
	}

	public function testGetConstraintChecker() {
		$this->assertInstanceOf(
			'WikibaseQuality\ConstraintReport\ConstraintCheck\DelegatingConstraintChecker',
			ConstraintReportFactory::getDefaultInstance()->getConstraintChecker()
		);
	}

}