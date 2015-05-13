<?php

namespace WikidataQuality\ConstraintReport\Test;

use WikidataQuality\ConstraintReport\ConstraintReportFactory;


/**
 * @covers WikidataQuality\ConstraintReport\ConstraintReportFactory
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
			'WikidataQuality\ConstraintReport\ConstraintReportFactory',
			ConstraintReportFactory::getDefaultInstance()
		);
	}

	public function testGetConstraintRepository() {
		$this->assertInstanceOf(
			'WikidataQuality\ConstraintReport\ConstraintRepository',
			ConstraintReportFactory::getDefaultInstance()->getConstraintRepository()
		);
	}

	public function testGetConstraintChecker() {
		$this->assertInstanceOf(
			'WikidataQuality\ConstraintReport\ConstraintCheck\DelegatingConstraintChecker',
			ConstraintReportFactory::getDefaultInstance()->getConstraintChecker()
		);
	}

}