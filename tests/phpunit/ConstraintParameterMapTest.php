<?php

namespace WikidataQuality\ConstraintReport\Tests;

use WikidataQuality\ConstraintReport\ConstraintParameterMap;


/**
 * @covers WikidataQuality\ConstraintReport\ConstraintParameterMap
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class ConstraintParameterMapTest extends \MediaWikiTestCase {

	public function testGetMap() {
		$map = ConstraintParameterMap::getMap();
		$this->assertEquals( array( 'pattern' ), $map['Format'] );
	}

}
