<?php

declare( strict_types = 1 );

namespace WikibaseQuality\ConstraintReport\Tests\Unit\Cache;

use InvalidArgumentException;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachingMetadata;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\DependencyMetadata;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\Metadata;
use Wikimedia\Assert\ParameterElementTypeException;

/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachingMetadata
 *
 * @group WikibaseQualityConstraints
 *
 * @author Lucas Werkmeister
 * @license GPL-2.0-or-later
 */
class CachingMetadataTest extends \MediaWikiUnitTestCase {

	public function testFresh() {
		$cm = CachingMetadata::fresh();

		$this->assertFalse( $cm->isCached() );
		$this->assertSame( 0, $cm->getMaximumAgeInSeconds() );
	}

	public function testOfMaximumAgeInSeconds() {
		$cm = CachingMetadata::ofMaximumAgeInSeconds( 42 );

		$this->assertTrue( $cm->isCached() );
		$this->assertSame( 42, $cm->getMaximumAgeInSeconds() );
	}

	public function testOfMaximumAgeInSeconds_zero() {
		$this->expectException( InvalidArgumentException::class );

		CachingMetadata::ofMaximumAgeInSeconds( 0 );
	}

	public function testOfMaximumAgeInSeconds_negative() {
		$this->expectException( InvalidArgumentException::class );

		CachingMetadata::ofMaximumAgeInSeconds( -42 );
	}

	public function testMerge() {
		$cm = CachingMetadata::merge( [
			CachingMetadata::fresh(),
			CachingMetadata::ofMaximumAgeInSeconds( 10 ),
			CachingMetadata::ofMaximumAgeInSeconds( 42 ),
			CachingMetadata::fresh(),
			CachingMetadata::ofMaximumAgeInSeconds( 13 ),
		] );

		$this->assertTrue( $cm->isCached() );
		$this->assertSame( 42, $cm->getMaximumAgeInSeconds() );
	}

	public function testMerge_fresh() {
		$cm = CachingMetadata::merge( [
			CachingMetadata::fresh(),
			CachingMetadata::fresh(),
		] );

		$this->assertFalse( $cm->isCached() );
		$this->assertSame( 0, $cm->getMaximumAgeInSeconds() );
	}

	public function testMerge_invalid() {
		$this->expectException( ParameterElementTypeException::class );

		CachingMetadata::merge( [
			10,
			Metadata::blank(),
			DependencyMetadata::blank(),
		] );
	}

	public function testToArray_fresh() {
		$cm = CachingMetadata::fresh();

		$array = $cm->toArray();

		$this->assertNull( $array );
	}

	public function testToArray_ofMaximumAgeInSeconds() {
		$cm = CachingMetadata::ofMaximumAgeInSeconds( 42 );

		$array = $cm->toArray();

		$this->assertSame( [ 'maximumAgeInSeconds' => 42 ], $array );
	}

}
