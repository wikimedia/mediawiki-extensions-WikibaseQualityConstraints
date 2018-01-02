<?php

namespace WikibaseQuality\ConstraintReport\Test\Cache;

use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachingMetadata;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\DependencyMetadata;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\Metadata;
use Wikimedia\Assert\ParameterElementTypeException;

/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\DependencyMetadata
 *
 * @group WikibaseQualityConstraints
 *
 * @author Lucas Werkmeister
 * @license GNU GPL v2+
 */
class DependencyMetadataTest extends \PHPUnit_Framework_TestCase {

	public function testBlank() {
		$dm = DependencyMetadata::blank();

		$this->assertSame( [], $dm->getEntityIds() );
	}

	public function testOfEntityId() {
		$q42 = new ItemId( 'Q42' );
		$dm = DependencyMetadata::ofEntityId( $q42 );

		$this->assertSame( [ $q42 ], $dm->getEntityIds() );
	}

	public function testMerge() {
		$q42 = new ItemId( 'Q42' );
		$p31 = new PropertyId( 'P31' );
		$dm = DependencyMetadata::merge( [
			DependencyMetadata::blank(),
			DependencyMetadata::ofEntityId( $q42 ),
			DependencyMetadata::blank(),
			DependencyMetadata::ofEntityId( $p31 ),
		] );

		$expectedEntityIds = [ $q42, $p31 ];
		$actualEntityIds = $dm->getEntityIds();
		sort( $expectedEntityIds );
		sort( $actualEntityIds );
		$this->assertSame( $expectedEntityIds, $actualEntityIds );
	}

	public function testMerge_blank() {
		$cm = DependencyMetadata::merge( [
			DependencyMetadata::blank(),
			DependencyMetadata::blank(),
		] );

		$this->assertSame( [], $cm->getEntityIds() );
	}

	public function testMerge_invalid() {
		$this->setExpectedException( ParameterElementTypeException::class );

		DependencyMetadata::merge( [
			[ new ItemId( 'Q42' ) ],
			Metadata::blank(),
			CachingMetadata::fresh(),
		] );
	}

}
