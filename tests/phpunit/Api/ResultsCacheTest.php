<?php

namespace WikibaseQuality\ConstraintReport\Tests\Api;

use HashBagOStuff;
use Language;
use WANObjectCache;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use WikibaseQuality\ConstraintReport\Api\ResultsCache;

/**
 * @covers WikibaseQuality\ConstraintReport\Api\ResultsCache
 *
 * @group WikibaseQualityConstraints
 *
 * @author Lucas Werkmeister
 * @license GNU GPL v2+
 */
class ResultsCacheTest extends \PHPUnit\Framework\TestCase {

	private $originalWgLang;

	public function setUp() {
		global $wgLang;
		parent::setUp();
		$this->originalWgLang = $wgLang;
	}

	public function tearDown() {
		global $wgLang;
		$wgLang = $this->originalWgLang;
		parent::tearDown();
	}

	public function testMakeKey() {
		$resultsCache = new ResultsCache( WANObjectCache::newEmpty() );

		$this->assertSame(
			'local:WikibaseQualityConstraints:checkConstraints:v2:Q5:en',
			$resultsCache->makeKey( new ItemId( 'Q5' ), 'en' )
		);
		$this->assertSame(
			'local:WikibaseQualityConstraints:checkConstraints:v2:P31:de',
			$resultsCache->makeKey( new PropertyId( 'P31' ), 'de' )
		);
	}

	public function testMakeKey_GarbageLanguageCode() {
		$resultsCache = new ResultsCache( WANObjectCache::newEmpty() );
		$languageCode = 'ifThisLanguageCodeAppearedInTheCacheKeyHowCouldWeEverHopeToPurgeIt';

		$key = $resultsCache->makeKey( new ItemId( 'Q5' ), $languageCode );

		$this->assertNotContains( $languageCode, $key );
	}

	public function testMakeKey_qqx() {
		$resultsCache = new ResultsCache( WANObjectCache::newEmpty() );

		$key = $resultsCache->makeKey( new ItemId( 'Q5' ), 'qqx' );

		$this->assertSame( 'local:WikibaseQualityConstraints:checkConstraints:v2:Q5:qqx', $key );
	}

	public function testGet() {
		$cache = new WANObjectCache( [ 'cache' => new HashBagOStuff() ] );
		$q5 = new ItemId( 'Q5' );
		$resultsCache = new ResultsCache( $cache );
		$expectedValue = 'garbage data, should not matter';
		$cache->set( $resultsCache->makeKey( $q5 ), $expectedValue );

		$actualValue = $resultsCache->get( $q5 );

		$this->assertSame( $expectedValue, $actualValue );
	}

	public function testSet() {
		$cache = new WANObjectCache( [ 'cache' => new HashBagOStuff() ] );
		$q5 = new ItemId( 'Q5' );
		$resultsCache = new ResultsCache( $cache );
		$expectedValue = 'garbage data, should not matter';

		$resultsCache->set( $q5, $expectedValue );

		$actualValue = $cache->get( $resultsCache->makeKey( $q5 ) );
		$this->assertSame( $expectedValue, $actualValue );
	}

	public function testGetSet() {
		$cache = new WANObjectCache( [ 'cache' => new HashBagOStuff() ] );
		$q5 = new ItemId( 'Q5' );
		$resultsCache = new ResultsCache( $cache );
		$expectedValue = 'garbage data, should not matter';

		$resultsCache->set( $q5, $expectedValue );
		$actualValue = $resultsCache->get( $q5 );

		$this->assertSame( $expectedValue, $actualValue );
	}

	public function testGetSet_DifferentLanguageCode() {
		global $wgLang;
		$cache = new WANObjectCache( [ 'cache' => new HashBagOStuff() ] );
		$q5 = new ItemId( 'Q5' );
		$resultsCache = new ResultsCache( $cache );

		$wgLang = Language::factory( 'en' );
		$resultsCache->set( $q5, 'cached results' );
		$wgLang = Language::factory( 'de' );
		$result = $resultsCache->get( $q5 );

		$this->assertFalse( $result );
	}

	public function testDelete() {
		$cache = new WANObjectCache( [ 'cache' => new HashBagOStuff() ] );
		$q5 = new ItemId( 'Q5' );
		$resultsCache = new ResultsCache( $cache );
		$expectedValue = 'garbage data, should not matter';
		$cache->set( $resultsCache->makeKey( $q5 ), $expectedValue );

		$resultsCache->delete( $q5 );

		$this->assertFalse( $cache->get( $resultsCache->makeKey( $q5 ) ) );
	}

	public function testDelete_SeveralLanguageCodes() {
		global $wgLang;
		$cache = new WANObjectCache( [ 'cache' => new HashBagOStuff() ] );
		$q5 = new ItemId( 'Q5' );
		$resultsCache = new ResultsCache( $cache );

		$wgLang = Language::factory( 'en' );
		$resultsCache->set( $q5, 'cached results' );
		$wgLang = Language::factory( 'de' );
		$resultsCache->set( $q5, 'gecachte Ergebnisse' );
		$wgLang = Language::factory( 'pt-br' );
		$resultsCache->set( $q5, 'resultados armazenados na cache' );
		$wgLang = Language::factory( 'qqx' );
		$resultsCache->set( $q5, '⧼cached-results⧽' );
		$wgLang = Language::factory( 'bar' );

		$resultsCache->delete( $q5 );

		$this->assertFalse( $resultsCache->get( $q5 ) );
		$wgLang = Language::factory( 'qqx' );
		$this->assertFalse( $resultsCache->get( $q5 ) );
		$wgLang = Language::factory( 'pt-br' );
		$this->assertFalse( $resultsCache->get( $q5 ) );
		$wgLang = Language::factory( 'de' );
		$this->assertFalse( $resultsCache->get( $q5 ) );
		$wgLang = Language::factory( 'en' );
		$this->assertFalse( $resultsCache->get( $q5 ) );
	}

}
