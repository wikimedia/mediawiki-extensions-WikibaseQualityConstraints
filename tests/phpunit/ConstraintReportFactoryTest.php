<?php

namespace WikibaseQuality\ConstraintReport\Tests;

use WikibaseQuality\ConstraintReport\ConstraintReportFactory;

/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintReportFactory
 *
 * @group WikibaseQualityConstraints
 *
 * @author BP2014N1
 * @license GPL-2.0-or-later
 */
class ConstraintReportFactoryTest extends \MediaWikiTestCase {

	public function testGetDefaultInstance() {
		$this->assertInstanceOf(
			ConstraintReportFactory::class,
			ConstraintReportFactory::getDefaultInstance()
		);
	}

}
