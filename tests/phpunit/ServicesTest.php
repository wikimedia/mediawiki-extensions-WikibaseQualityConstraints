<?php


namespace WikibaseQuality\ConstraintReport\Tests;

use MediaWiki\MediaWikiServices;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\LoggingHelper;
use WikibaseQuality\ConstraintReport\ConstraintsServices;

/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintsServices
 *
 * @group WikibaseQualityConstraints
 *
 * @license GPL-2.0-or-later
 */
class ServicesTest extends \PHPUnit\Framework\TestCase {

	public function provideServiceClasses() {
		return [
			[ LoggingHelper::class ],
		];
	}

	/**
	 * @dataProvider provideServiceClasses
	 */
	public function testServiceWiring( $serviceClass ) {
		$serviceClassParts = explode( '\\', $serviceClass );
		$serviceName = 'WBQC_' . end( $serviceClassParts );

		$service = MediaWikiServices::getInstance()->getService( $serviceName );

		$this->assertInstanceOf( $serviceClass, $service );
	}

	/**
	 * @dataProvider provideServiceClasses
	 */
	public function testConstraintsServices( $serviceClass ) {
		$serviceClassParts = explode( '\\', $serviceClass );
		$getterName = 'get' . end( $serviceClassParts );

		$service = ConstraintsServices::$getterName();

		$this->assertInstanceOf( $serviceClass, $service );
	}

}
