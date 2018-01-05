<?php

namespace WikibaseQuality\ConstraintReport\Tests\Api;

use ApiTestCase;
use DataValues\UnknownValue;
use HashConfig;
use MediaWiki\Logger\LoggerFactory;
use NullStatsdDataFactory;
use RequestContext;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\ItemIdParser;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\Lookup\InMemoryEntityLookup;
use Wikibase\DataModel\Services\Statement\StatementGuidValidator;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Statement\StatementList;
use Wikibase\Repo\EntityIdLabelFormatterFactory;
use Wikibase\Repo\WikibaseRepo;
use WikibaseQuality\ConstraintReport\Api\CheckConstraints;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\DelegatingConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\LoggingHelper;
use WikibaseQuality\ConstraintReport\Api\CheckingResultsBuilder;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikibaseQuality\ConstraintReport\Tests\Fake\FakeChecker;
use WikibaseQuality\ConstraintReport\Tests\Fake\InMemoryConstraintLookup;
use Wikibase\LanguageFallbackChainFactory;
use Wikibase\Lib\SnakFormatter;
use Wikibase\Lib\Store\LanguageFallbackLabelDescriptionLookupFactory;
use Wikibase\Lib\OutputFormatValueFormatterFactory;
use ValueFormatters\FormatterOptions;
use Wikimedia\Assert\Assert;
use Language;
use WikibaseQuality\ConstraintReport\ConstraintParameterRenderer;

/**
 * @covers WikibaseQuality\ConstraintReport\Api\CheckConstraints
 *
 * @group API
 * @group Database
 * @group WikibaseAPI
 *
 * @group medium
 *
 * @license GPL-2.0+
 */
class CheckConstraintsTest extends ApiTestCase {

	const NONEXISTENT_ITEM = 'Q99';
	const NONEXISTENT_CLAIM = 'Q99$dfb32791-ffd5-4420-a1d9-2bc2a0775968';

	private static $oldModuleDeclaration;

	/**
	 * @var InMemoryEntityLookup
	 */
	private static $entityLookup;

	/**
	 * @var Constraint[]
	 */
	private static $constraintLookupContents = [];

	/**
	 * @var ConstraintChecker[]
	 */
	private static $checkerMap = [];

	public static function setUpBeforeClass() {
		parent::setUpBeforeClass();
		global $wgAPIModules;

		self::$oldModuleDeclaration = $wgAPIModules['wbcheckconstraints'];

		self::$entityLookup = new InMemoryEntityLookup();

		$wgAPIModules['wbcheckconstraints']['factory'] = function ( $main, $name ) {
			$repo = WikibaseRepo::getDefaultInstance();
			$factory = new EntityIdLabelFormatterFactory();
			$termLookup = $repo->getTermLookup();
			$termBuffer = $repo->getTermBuffer();
			$languageFallbackChainFactory = new LanguageFallbackChainFactory();
			$fallbackLabelDescLookupFactory = new LanguageFallbackLabelDescriptionLookupFactory(
				$languageFallbackChainFactory,
				$termLookup,
				$termBuffer
			);
			$language = new Language();
			$labelLookup = $fallbackLabelDescLookupFactory->newLabelDescriptionLookup( $language );
			$entityIdFormatter = $factory->getEntityIdFormatter( $labelLookup );

			$formatterOptions = new FormatterOptions();
			$factoryFunctions = [];
			Assert::parameterElementType( 'callable', $factoryFunctions, '$factoryFunctions' );
			$formatterOptions->setOption( SnakFormatter::OPT_LANG, $language->getCode() );
			$valueFormatterFactory = new OutputFormatValueFormatterFactory(
				$factoryFunctions,
				$language,
				$languageFallbackChainFactory
			);
			$valueFormatter = $valueFormatterFactory->getValueFormatter( SnakFormatter::FORMAT_HTML, $formatterOptions );

			// we can’t use the DefaultConfig trait because we’re in a static method
			$config = new HashConfig( [
				'WBQualityConstraintsPropertyConstraintId' => 'P1',
				'WBQualityConstraintsExceptionToConstraintId' => 'P2',
				'WBQualityConstraintsConstraintStatusId' => 'P3',
				'WBQualityConstraintsCheckDurationInfoSeconds' => 1.0,
				'WBQualityConstraintsCheckDurationWarningSeconds' => 10.0,
				'WBQualityConstraintsIncludeDetailInApi' => true,
			] );
			$entityIdParser = new ItemIdParser();
			$constraintParameterRenderer = new ConstraintParameterRenderer(
				$entityIdFormatter,
				$valueFormatter,
				$config
			);
			$constraintParameterParser = new ConstraintParameterParser(
				$config,
				$repo->getBaseDataModelDeserializerFactory(),
				$constraintParameterRenderer
			);
			$dataFactory = new NullStatsdDataFactory();
			$constraintChecker = new DelegatingConstraintChecker(
				self::$entityLookup,
				self::$checkerMap,
				new InMemoryConstraintLookup( self::$constraintLookupContents ),
				$constraintParameterParser,
				$repo->getStatementGuidParser(),
				new LoggingHelper(
					$dataFactory,
					LoggerFactory::getInstance( 'WikibaseQualityConstraints' ),
					$config
				),
				false,
				false,
				[]
			);

			return new CheckConstraints(
				$main,
				$name,
				'',
				$entityIdParser,
				new StatementGuidValidator( $entityIdParser ),
				$repo->getApiHelperFactory( RequestContext::getMain() ),
				new CheckingResultsBuilder(
					$constraintChecker,
					$repo->getEntityTitleLookup(),
					$entityIdFormatter,
					$constraintParameterRenderer,
					$config
				),
				$dataFactory
			);
		};
	}

	protected function tearDown() {
		self::$constraintLookupContents = [];
		self::$checkerMap = [];
		parent::tearDown();
	}

	public static function tearDownAfterClass() {
		global $wgAPIModules;
		$wgAPIModules['wbcheckconstraints'] = self::$oldModuleDeclaration;

		parent::tearDownAfterClass();
	}

	public function testReportForNonexistentItemIsEmpty() {
		$result = $this->doRequest(
			[ CheckConstraints::PARAM_ID => self::NONEXISTENT_ITEM ]
		);

		$this->assertEmpty( $result['wbcheckconstraints'][self::NONEXISTENT_ITEM] );
	}

	public function testReportForNonexistentClaimIsEmpty() {
		$result = $this->doRequest(
			[ CheckConstraints::PARAM_CLAIM_ID => self::NONEXISTENT_CLAIM ]
		);

		$this->assertEmpty( $result['wbcheckconstraints'] );
	}

	public function testItemExistsAndHasViolation_WillGetOnlyThisViolationInTheResult() {
		$this->givenItemWithPropertyExists(
			new ItemId( 'Q1' ),
			new PropertyId( 'P1' ),
			'46fc8ec9-4903-4592-9a0e-afdd1fa03183'
		);
		$this->givenPropertyHasViolation( new PropertyId( 'P1' ) );

		$result = $this->doRequest( [ CheckConstraints::PARAM_ID => 'Q1' ] );

		$this->assertCount( 1, $result['wbcheckconstraints'] );
		$resultStatement = $result['wbcheckconstraints']['Q1']['claims']['P1'][0];
		$this->assertSame( 'Q1$46fc8ec9-4903-4592-9a0e-afdd1fa03183', $resultStatement['id'] );
		$resultsForItem = $resultStatement['mainsnak']['results'];
		$this->assertCount( 1, $resultsForItem );
		$this->assertEquals( CheckResult::STATUS_WARNING, $resultsForItem[0]['status'] );
		$this->assertEquals( 'P1', $resultsForItem[0]['property'] );
	}

	public function testItemWithClaimExistsAndHasViolation_WillGetOnlyThisViolationInTheResult() {
		$this->givenItemWithPropertyExists(
			new ItemId( 'Q1' ),
			new PropertyId( 'P1' ),
			'46fc8ec9-4903-4592-9a0e-afdd1fa03183'
		);
		$this->givenPropertyHasViolation( new PropertyId( 'P1' ) );

		$result = $this->doRequest( [ CheckConstraints::PARAM_CLAIM_ID => 'Q1$46fc8ec9-4903-4592-9a0e-afdd1fa03183' ] );

		$this->assertCount( 1, $result['wbcheckconstraints'] );
		$resultStatement = $result['wbcheckconstraints']['Q1']['claims']['P1'][0];
		$this->assertSame( 'Q1$46fc8ec9-4903-4592-9a0e-afdd1fa03183', $resultStatement['id'] );
		$resultsForItem = $resultStatement['mainsnak']['results'];
		$this->assertCount( 1, $resultsForItem );
		$this->assertEquals( CheckResult::STATUS_WARNING, $resultsForItem[0]['status'] );
		$this->assertEquals( 'P1', $resultsForItem[0]['property'] );
	}

	public function testItemWithAlternativeCaseClaimExistsAndHasViolation_WillGetOnlyThisViolationInTheResult() {
		$itemId = 'Q1';
		$propertyId = 'P1';
		$guid = 'q1$46FC8EC9-4903-4592-9A0E-AFDD1FA03183';
		$item = new Item( new ItemId( $itemId ) );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( $propertyId ), new UnknownValue( null ) ) );
		$statement->setGuid( $guid );
		$item->getStatements()->addStatement( $statement );
		self::$entityLookup->addEntity( $item );
		$this->givenPropertyHasViolation( new PropertyId( $propertyId ) );

		$result = $this->doRequest( [ CheckConstraints::PARAM_CLAIM_ID => $guid ] );

		$this->assertCount( 1, $result['wbcheckconstraints'] );
		$resultStatement = $result['wbcheckconstraints']['Q1']['claims']['P1'][0];
		$this->assertSame( $guid, $resultStatement['id'] );
		$resultsForItem = $resultStatement['mainsnak']['results'];
		$this->assertCount( 1, $resultsForItem );
		$this->assertEquals( CheckResult::STATUS_WARNING, $resultsForItem[0]['status'] );
		$this->assertEquals( $propertyId, $resultsForItem[0]['property'] );
	}

	/**
	 * @param array $params
	 * @return array Array of violations
	 */
	private function doRequest( array $params ) {
		$params['action'] = 'wbcheckconstraints';
		return $this->doApiRequest( $params, [], false, null )[0];
	}

	private function givenPropertyHasViolation( PropertyId $propertyId ) {
		self::$checkerMap['Q1234'] = new FakeChecker( CheckResult::STATUS_VIOLATION );
		self::$constraintLookupContents[] = new Constraint(
			'P1234$6a4d1930-922b-4c2e-b6e1-9a06bf04c2f8',
			$propertyId,
			'Q1234',
			[]
		);
	}

	private function givenItemWithPropertyExists(
		ItemId $itemId,
		PropertyId $propertyId,
		$statementId = 'some-id'
	) {
		$item = new Item(
			$itemId,
			null,
			null,
			new StatementList(
				[
					new Statement(
						new PropertyValueSnak( $propertyId, new UnknownValue( null ) ),
						null,
						null,
						$itemId->getSerialization() . '$' . $statementId
					)
				]
			)
		);
		self::$entityLookup->addEntity( $item );
	}

}
