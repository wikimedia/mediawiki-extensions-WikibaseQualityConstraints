<?php

namespace WikibaseQuality\ConstraintReport\Tests\Api;

use HashConfig;
use IContextSource;
use NullStatsdDataFactory;
use OutputPage;
use PHPUnit4And6Compat;
use PHPUnit_Framework_MockObject_MockObject;
use Psr\Log\NullLogger;
use Title;
use WANObjectCache;
use WebRequest;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\ItemIdParser;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\Lib\Store\Sql\WikiPageEntityMetaDataAccessor;
use Wikibase\Repo\WikibaseRepo;
use WikibaseQuality\ConstraintReport\Api\CachingResultsSource;
use WikibaseQuality\ConstraintReport\Api\CheckConstraintsRdf;
use WikibaseQuality\ConstraintReport\Api\ResultsCache;
use WikibaseQuality\ConstraintReport\Api\ResultsSource;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachedCheckResults;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachingMetadata;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\Metadata;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\MainSnakContextCursor;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\LoggingHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessage;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResultDeserializer;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResultSerializer;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\NullResult;
use WikiPage;

/**
 * @covers \WikibaseQuality\ConstraintReport\Api\CheckConstraintsRdf
 *
 * @group API
 * @group WikibaseAPI
 *
 * @group medium
 *
 * @license GPL-2.0-or-later
 */
class CheckConstraintsRdfTest extends \PHPUnit\Framework\TestCase {
	use PHPUnit4And6Compat;

	/**
	 * @return PHPUnit_Framework_MockObject_MockObject
	 */
	private function getOutput() {
		$output = $this->getMockBuilder( OutputPage::class )
			->disableOriginalConstructor()
			->getMock();

		return $output;
	}

	/**
	 * @param string $entityId entity ID serialization
	 */
	private function getCheckResult( $entityId, $status = CheckResult::STATUS_VIOLATION ) {
		return new CheckResult(
			new MainSnakContextCursor(
				$entityId,
				'P1',
				$entityId . '$00000000-0000-0000-0000-000000000000',
				'0000000000000000000000000000000000000000'
			),
			new Constraint(
				'P1$00000000-0000-0000-0000-000000000000',
				new PropertyId( 'P1' ),
				'Q12345',
				[]
			),
			[],
			$status,
			new ViolationMessage( 'wbqc-violation-message-single-value' )
		);
	}

	private function getNullResult( $entityId ) {
		return new NullResult(
			new MainSnakContextCursor(
				$entityId,
				'P1',
				$entityId . '$00000000-0000-0000-0000-000000000000',
				'0000000000000000000000000000000000000000'
			)
		);
	}

	/**
	 * @param PHPUnit_Framework_MockObject_MockObject $output
	 *
	 * @return IContextSource
	 */
	private function getContext( PHPUnit_Framework_MockObject_MockObject $mockResponse ) {
		$output = $this->getOutput();
		$context = $this->getMock( IContextSource::class );

		$mockRequest = $this->getMock( WebRequest::class );
		$mockRequest->method( 'response' )
			->willReturn( $mockResponse );
		$context->method( 'getRequest' )
			->willReturn( $mockRequest );

		$context->method( 'getOutput' )
			->willReturn( $output );

		return $context;
	}

	/**
	 * @return CheckResultSerializer
	 */
	private function getCheckResultSerializer() {
		return $this->getMockBuilder( CheckResultSerializer::class )
			->disableOriginalConstructor()
			->getMock();
	}

	/**
	 * @return CheckResultDeserializer
	 */
	private function getCheckResultDeserializer() {
		return $this->getMockBuilder( CheckResultDeserializer::class )
			->disableOriginalConstructor()
			->getMock();
	}

	/**
	 * @return LoggingHelper
	 */
	private function getLoggingHelper() {
		return new LoggingHelper(
			new NullStatsdDataFactory(),
			new NullLogger(),
			new HashConfig( [
				'WBQualityConstraintsCheckDurationInfoSeconds' => 5.0,
				'WBQualityConstraintsCheckDurationWarningSeconds' => 10.0,
				'WBQualityConstraintsCheckOnEntityDurationInfoSeconds' => 15.0,
				'WBQualityConstraintsCheckOnEntityDurationWarningSeconds' => 55.0,
			] )
		);
	}

	/**
	 * @return ResultsSource
	 */
	private function getCachingResultsSource() {
		return $this->getMockBuilder( CachingResultsSource::class )
			->setConstructorArgs( [
				$this->getMock( ResultsSource::class ),
				new ResultsCache( WANObjectCache::newEmpty(), 'v2' ),
				$this->getCheckResultSerializer(),
				$this->getCheckResultDeserializer(),
				$this->getMock( WikiPageEntityMetaDataAccessor::class ),
				new ItemIdParser(),
				86400,
				[],
				10000,
				$this->getLoggingHelper(),
			] )
			->getMock();
	}

	private function getCheckConstraintsRdf( \Page $page, $mockResponse, ResultsSource $cachingResultsSource = null ) {
		if ( $cachingResultsSource === null ) {
			$cachingResultsSource = $this->getCachingResultsSource();
		}
		$repo = WikibaseRepo::getDefaultInstance();
		return new CheckConstraintsRdf(
			$page,
			$this->getContext( $mockResponse ),
			$cachingResultsSource,
			$repo->getEntityIdLookup(),
			$repo->getRdfVocabulary()
		);
	}

	public function testShow() {
		$page = new WikiPage( Title::newFromText( 'Property:P1' ) );

		$cachingResultsSource = $this->getCachingResultsSource();
		$cachingResultsSource->expects( $this->once() )->method( 'getStoredResults' )
			->willReturnCallback( function( EntityId $entityId ) {
				$serialization = $entityId->getSerialization();
				return new CachedCheckResults(
					[ $this->getCheckResult( $serialization ) ],
					Metadata::ofCachingMetadata( CachingMetadata::ofMaximumAgeInSeconds( 64800 ) )
				);
			} );

		$mockResponse = $this->getMock( \WebResponse::class );
		$mockResponse->expects( $this->never() )->method( 'statusHeader' );
		$mockResponse->expects( $this->once() )->method( 'header' )
			->with( 'Content-Type: text/turtle; charset=UTF-8' );
		$repo = WikibaseRepo::getDefaultInstance();
		$rdfVocabulary = $repo->getRdfVocabulary();
		$action = $this->getCheckConstraintsRdf( $page, $mockResponse, $cachingResultsSource );

		ob_start();
		$action->onView();
		$actualOutput = ob_get_clean();

		$wdsURI = $rdfVocabulary->getNamespaceURI( 'wds' );
		$expectedOutput = <<<TEXT
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
@prefix wds: <$wdsURI> .
@prefix wikibase: <http://wikiba.se/ontology#> .

wds:P1-00000000-0000-0000-0000-000000000000 wikibase:hasViolationForConstraint wds:P1-00000000-0000-0000-0000-000000000000 .

TEXT;
		$this->assertEquals( $expectedOutput, $actualOutput );
	}

	public function testShow404() {
		$page = new WikiPage( Title::newFromText( 'something strange' ) );
		$mockResponse = $this->getMock( \WebResponse::class );
		$mockResponse->expects( $this->once() )->method( 'statusHeader' )->with( 404 );
		$action = $this->getCheckConstraintsRdf( $page, $mockResponse );

		ob_start();
		$action->onView();
		$actualOutput = ob_get_clean();

		$expectedOutput = '';
		$this->assertEquals( $expectedOutput, $actualOutput );
	}

	public function testShowNoResults() {
		$page = new WikiPage( Title::newFromText( 'Item:Q1' ) );
		$mockResponse = $this->getMock( \WebResponse::class );
		$mockResponse->expects( $this->once() )->method( 'statusHeader' )->with( 204 );
		$action = $this->getCheckConstraintsRdf( $page, $mockResponse );

		ob_start();
		$action->onView();
		$actualOutput = ob_get_clean();

		$expectedOutput = '';
		$this->assertEquals( $expectedOutput, $actualOutput );
	}

	public function testShowNoResultsWithNull() {
		$page = new WikiPage( Title::newFromText( 'Property:P1' ) );

		$cachingResultsSource = $this->getCachingResultsSource();
		$cachingResultsSource->expects( $this->once() )->method( 'getStoredResults' )
			->willReturnCallback( function( EntityId $entityId ) {
				$serialization = $entityId->getSerialization();
				return new CachedCheckResults(
					[
						$this->getNullResult( $serialization ),
						$this->getCheckResult( $serialization, CheckResult::STATUS_BAD_PARAMETERS )
					],
					Metadata::ofCachingMetadata( CachingMetadata::ofMaximumAgeInSeconds( 64800 ) )
				);
			} );

		$mockResponse = $this->getMock( \WebResponse::class );
		$mockResponse->expects( $this->once() )->method( 'statusHeader' )->with( 204 );
		$action = $this->getCheckConstraintsRdf( $page, $mockResponse, $cachingResultsSource );

		ob_start();
		$action->onView();
		$actualOutput = ob_get_clean();

		$expectedOutput = '';
		$this->assertEquals( $expectedOutput, $actualOutput );
	}

}
