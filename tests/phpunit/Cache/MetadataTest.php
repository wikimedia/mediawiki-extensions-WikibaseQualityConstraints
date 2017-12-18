<?php

namespace WikibaseQuality\ConstraintReport\Test\Cache;

use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachingMetadata;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\DependencyMetadata;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\Metadata;
use Wikimedia\Assert\ParameterElementTypeException;

/**
 * @covers \WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\Metadata
 *
 * @group WikibaseQualityConstraints
 *
 * @author Lucas Werkmeister
 * @license GNU GPL v2+
 */
class MetadataTest extends \PHPUnit_Framework_TestCase {

	public function testBlank() {
		$m = Metadata::blank();

		$this->assertFalse( $m->getCachingMetadata()->isCached() );
		$this->assertSame( [], $m->getDependencyMetadata()->getEntityIds() );
	}

	public function testOfCachingMetadata() {
		$cm = CachingMetadata::ofMaximumAgeInSeconds( 42 );
		$m = Metadata::ofCachingMetadata( $cm );

		$this->assertSame( $cm, $m->getCachingMetadata() );
	}

	public function testOfDependencyMetadata() {
		$dm = DependencyMetadata::ofEntityId( new ItemId( 'Q42' ) );
		$m = Metadata::ofDependencyMetadata( $dm );

		$this->assertSame( $dm, $m->getDependencyMetadata() );
	}

	public function testMerge() {
		$q42 = new ItemId( 'Q42' );
		$p31 = new PropertyId( 'P31' );
		$m = Metadata::merge( [
			Metadata::blank(),
			Metadata::ofCachingMetadata( CachingMetadata::fresh() ),
			Metadata::ofCachingMetadata( CachingMetadata::ofMaximumAgeInSeconds( 10 ) ),
			Metadata::ofCachingMetadata( CachingMetadata::ofMaximumAgeInSeconds( 42 ) ),
			Metadata::ofDependencyMetadata( DependencyMetadata::ofEntityId( $q42 ) ),
			Metadata::ofDependencyMetadata( DependencyMetadata::blank() ),
			Metadata::ofCachingMetadata( CachingMetadata::ofMaximumAgeInSeconds( 13 ) ),
			Metadata::ofDependencyMetadata( DependencyMetadata::ofEntityId( $p31 ) ),
		] );

		$this->assertTrue( $m->getCachingMetadata()->isCached() );
		$this->assertSame( 42, $m->getCachingMetadata()->getMaximumAgeInSeconds() );
		$expectedEntityIds = [ $q42, $p31 ];
		$actualEntityIds = $m->getDependencyMetadata()->getEntityIds();
		sort( $expectedEntityIds );
		sort( $actualEntityIds );
		$this->assertSame( $expectedEntityIds, $actualEntityIds );
	}

	public function testMerge_blank() {
		$m = Metadata::merge( [
			Metadata::blank(),
			Metadata::blank(),
		] );

		$this->assertFalse( $m->getCachingMetadata()->isCached() );
		$this->assertSame( [], $m->getDependencyMetadata()->getEntityIds() );
	}

	public function testMerge_invalid() {
		$this->setExpectedException( ParameterElementTypeException::class );

		Metadata::merge( [
			10,
			[ new ItemId( 'Q42' ) ],
			CachingMetadata::fresh(),
			DependencyMetadata::blank(),
		] );
	}

}
