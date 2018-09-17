<?php


namespace WikibaseQuality\ConstraintReport\Tests;

use MediaWiki\MediaWikiServices;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConnectionCheckerHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\LoggingHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\RangeCheckerHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\SparqlHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\TypeCheckerHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageDeserializer;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageSerializer;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResultDeserializer;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResultSerializer;
use WikibaseQuality\ConstraintReport\ConstraintLookup;
use WikibaseQuality\ConstraintReport\ConstraintRepository;
use WikibaseQuality\ConstraintReport\ConstraintsServices;
use WikibaseQuality\ConstraintReport\WikibaseServices;

/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintsServices
 *
 * @group WikibaseQualityConstraints
 *
 * @license GPL-2.0-or-later
 */
class ServicesTest extends \PHPUnit\Framework\TestCase {

	public function provideConstraintsServiceClasses() {
		return [
			[ LoggingHelper::class ],
			[ ConstraintRepository::class ],
			[ ConstraintLookup::class ],
			[ CheckResultSerializer::class ],
			[ CheckResultDeserializer::class ],
			[ ViolationMessageSerializer::class ],
			[ ViolationMessageDeserializer::class ],
			[ ConstraintParameterParser::class ],
			[ ConnectionCheckerHelper::class ],
			[ RangeCheckerHelper::class ],
			[ SparqlHelper::class ],
			[ TypeCheckerHelper::class ],
		];
	}

	public function provideWikibaseServiceClasses() {
		return [
			[ EntityLookup::class ],
			[ PropertyDataTypeLookup::class ],
		];
	}

	public function provideAllServiceClasses() {
		yield from $this->provideConstraintsServiceClasses();
		yield from $this->provideWikibaseServiceClasses();
	}

	/**
	 * @dataProvider provideAllServiceClasses
	 */
	public function testServiceWiring( $serviceClass ) {
		$serviceClassParts = explode( '\\', $serviceClass );
		$serviceName = 'WBQC_' . end( $serviceClassParts );

		$service = MediaWikiServices::getInstance()->getService( $serviceName );

		$this->assertInstanceOf( $serviceClass, $service );
	}

	/**
	 * @dataProvider provideConstraintsServiceClasses
	 */
	public function testConstraintsServices( $serviceClass ) {
		$serviceClassParts = explode( '\\', $serviceClass );
		$getterName = 'get' . end( $serviceClassParts );

		$service = ConstraintsServices::$getterName();

		$this->assertInstanceOf( $serviceClass, $service );
	}

	/**
	 * @dataProvider provideWikibaseServiceClasses
	 */
	public function testWikibaseServices( $serviceClass ) {
		$serviceClassParts = explode( '\\', $serviceClass );
		$getterName = 'get' . end( $serviceClassParts );

		$service = WikibaseServices::$getterName();

		$this->assertInstanceOf( $serviceClass, $service );
	}

}
