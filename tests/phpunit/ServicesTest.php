<?php


namespace WikibaseQuality\ConstraintReport\Tests;

use MediaWiki\MediaWikiServices;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use WikibaseQuality\ConstraintReport\Api\ExpiryLock;
use WikibaseQuality\ConstraintReport\Api\ResultsSource;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\AllowedUnitsChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\CitationNeededChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\CommonsLinkChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\ConflictsWithChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\ContemporaryChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\DiffWithinRangeChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\EntityTypeChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\FormatChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\IntegerChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\InverseChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\ItemChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\MandatoryQualifiersChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\MultiValueChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\NoBoundsChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\NoneOfChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\OneOfChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\PropertyScopeChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\QualifierChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\QualifiersChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\RangeChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\ReferenceChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\SingleBestValueChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\SingleValueChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\SymmetricChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\TargetRequiredClaimChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\TypeChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\UniqueValueChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\ValueOnlyChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\ValueTypeChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\DelegatingConstraintChecker;
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
use WikibaseQuality\ConstraintReport\ConstraintCheckerServices;
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
			[ ExpiryLock::class ],
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
			[ DelegatingConstraintChecker::class ],
			[ ResultsSource::class ],
		];
	}

	public function provideConstraintCheckerServiceClasses() {
		return [
			[ ConflictsWithChecker::class ],
			[ ItemChecker::class ],
			[ TargetRequiredClaimChecker::class ],
			[ SymmetricChecker::class ],
			[ InverseChecker::class ],
			[ QualifierChecker::class ],
			[ QualifiersChecker::class ],
			[ MandatoryQualifiersChecker::class ],
			[ RangeChecker::class ],
			[ DiffWithinRangeChecker::class ],
			[ TypeChecker::class ],
			[ ValueTypeChecker::class ],
			[ SingleValueChecker::class ],
			[ MultiValueChecker::class ],
			[ UniqueValueChecker::class ],
			[ FormatChecker::class ],
			[ CommonsLinkChecker::class ],
			[ OneOfChecker::class ],
			[ ValueOnlyChecker::class ],
			[ ReferenceChecker::class ],
			[ NoBoundsChecker::class ],
			[ AllowedUnitsChecker::class ],
			[ SingleBestValueChecker::class ],
			[ EntityTypeChecker::class ],
			[ NoneOfChecker::class ],
			[ IntegerChecker::class ],
			[ CitationNeededChecker::class ],
			[ PropertyScopeChecker::class ],
			[ ContemporaryChecker::class ],
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
		yield from $this->provideConstraintCheckerServiceClasses();
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
	 * @dataProvider provideConstraintCheckerServiceClasses
	 */
	public function testConstraintCheckerServices( $serviceClass ) {
		$serviceClassParts = explode( '\\', $serviceClass );
		$getterName = 'get' . end( $serviceClassParts );

		$service = ConstraintCheckerServices::$getterName();

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
