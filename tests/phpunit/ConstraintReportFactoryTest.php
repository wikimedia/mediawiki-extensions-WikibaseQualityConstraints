<?php

namespace WikibaseQuality\ConstraintReport\Tests;

use WikibaseQuality\ConstraintReport\ConstraintCheck\DelegatingConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintReportFactory;
use WikibaseQuality\ConstraintReport\ConstraintRepository;

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
		$this->assertEquals( [ 'pattern' ], $map['Format'] );
	}

	public function testGetDefaultInstance() {
		$this->assertInstanceOf(
			ConstraintReportFactory::class,
			ConstraintReportFactory::getDefaultInstance()
		);
	}

	public function testGetConstraintRepository() {
		$this->assertInstanceOf(
			ConstraintRepository::class,
			ConstraintReportFactory::getDefaultInstance()->getConstraintRepository()
		);
	}

	public function testGetConstraintChecker() {
		$this->assertInstanceOf(
			DelegatingConstraintChecker::class,
			ConstraintReportFactory::getDefaultInstance()->getConstraintChecker()
		);
	}

}
