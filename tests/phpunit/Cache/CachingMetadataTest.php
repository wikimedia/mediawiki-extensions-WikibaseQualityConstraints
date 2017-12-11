<?php

namespace WikibaseQuality\ConstraintReport\Test\Cache;

use InvalidArgumentException;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachingMetadata;
use Wikimedia\Assert\ParameterElementTypeException;
use Wikimedia\Assert\ParameterTypeException;

/**
 * @covers \WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachingMetadata
 *
 * @group WikibaseQualityConstraints
 *
 * @author Lucas Werkmeister
 * @license GNU GPL v2+
 */
class CachingMetadataTest extends \PHPUnit_Framework_TestCase {

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
		$this->setExpectedException( InvalidArgumentException::class );

		CachingMetadata::ofMaximumAgeInSeconds( 0 );
	}

	public function testOfMaximumAgeInSeconds_negative() {
		$this->setExpectedException( InvalidArgumentException::class );

		CachingMetadata::ofMaximumAgeInSeconds( -42 );
	}

	public function testOfMaximumAgeInSeconds_string() {
		$this->setExpectedException( ParameterTypeException::class );

		CachingMetadata::ofMaximumAgeInSeconds( '42' );
	}

	public function testOfEntityId() {
		$q42 = new ItemId( 'Q42' );
		$cm = CachingMetadata::ofEntityId( $q42 );

		$this->assertFalse( $cm->isCached() );
		$this->assertSame( [ $q42 ], $cm->getDependedEntityIds() );
	}

	public function testMerge() {
		$q42 = new ItemId( 'Q42' );
		$p31 = new PropertyId( 'P31' );
		$cm = CachingMetadata::merge( [
			CachingMetadata::fresh(),
			CachingMetadata::ofMaximumAgeInSeconds( 10 ),
			CachingMetadata::ofMaximumAgeInSeconds( 42 ),
			CachingMetadata::ofEntityId( $q42 ),
			CachingMetadata::fresh(),
			CachingMetadata::ofMaximumAgeInSeconds( 13 ),
			CachingMetadata::ofEntityId( $p31 ),
		] );

		$this->assertTrue( $cm->isCached() );
		$this->assertSame( 42, $cm->getMaximumAgeInSeconds() );
		$expectedDependedEntityIds = [ $q42, $p31 ];
		$actualDependedEntityIds = $cm->getDependedEntityIds();
		sort( $expectedDependedEntityIds );
		sort( $actualDependedEntityIds );
		$this->assertSame( $expectedDependedEntityIds, $actualDependedEntityIds );
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
		$this->setExpectedException( ParameterElementTypeException::class );

		CachingMetadata::merge( [
			10,
			42,
			13,
		] );
	}

}
