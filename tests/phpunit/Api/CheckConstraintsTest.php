<?php

namespace WikibaseQuality\ConstraintReport\Tests\Api;

use ApiTestCase;
use DataValues\UnknownValue;
use HashConfig;
use Language;
use MediaWiki\Logger\LoggerFactory;
use MockMessageLocalizer;
use NullStatsdDataFactory;
use RequestContext;
use ValueFormatters\FormatterOptions;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\ItemIdParser;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\Lookup\InMemoryEntityLookup;
use Wikibase\DataModel\Services\Statement\StatementGuidValidator;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Statement\StatementList;
use Wikibase\Lib\Formatters\OutputFormatValueFormatterFactory;
use Wikibase\Lib\Formatters\SnakFormatter;
use Wikibase\Lib\LanguageFallbackChainFactory;
use Wikibase\Repo\EntityIdLabelFormatterFactory;
use Wikibase\Repo\Tests\NewItem;
use Wikibase\Repo\Tests\NewStatement;
use Wikibase\Repo\WikibaseRepo;
use WikibaseQuality\ConstraintReport\Api\CheckConstraints;
use WikibaseQuality\ConstraintReport\Api\CheckingResultsSource;
use WikibaseQuality\ConstraintReport\Api\CheckResultsRenderer;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\DelegatingConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\LoggingHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageRenderer;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikibaseQuality\ConstraintReport\Tests\Fake\FakeChecker;
use WikibaseQuality\ConstraintReport\Tests\Fake\InMemoryConstraintLookup;
use Wikimedia\Assert\Assert;

/**
 * @covers \WikibaseQuality\ConstraintReport\Api\CheckConstraints
 *
 * @group API
 * @group Database
 * @group WikibaseAPI
 *
 * @group medium
 *
 * @license GPL-2.0-or-later
 */
class CheckConstraintsTest extends ApiTestCase {

	const EMPTY_ITEM = 'Q98';
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

	public static function setUpBeforeClass() : void {
		parent::setUpBeforeClass();
		global $wgAPIModules;

		self::$oldModuleDeclaration = $wgAPIModules['wbcheckconstraints'];

		self::$entityLookup = new InMemoryEntityLookup();
		self::$entityLookup->addEntity( new Item( new ItemId( self::EMPTY_ITEM ) ) );

		$wgAPIModules['wbcheckconstraints']['factory'] = function ( $main, $name ) {
			$repo = WikibaseRepo::getDefaultInstance();
			$factory = new EntityIdLabelFormatterFactory();
			$languageFallbackChainFactory = new LanguageFallbackChainFactory();

			$language = Language::factory( 'en' );
			$entityIdFormatter = $factory->getEntityIdFormatter( $language );

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
				'WBQualityConstraintsConstraintScopeId' => 'P4',
				'WBQualityConstraintsConstraintCheckedOnMainValueId' => 'Q1',
				'WBQualityConstraintsConstraintCheckedOnQualifiersId' => 'Q2',
				'WBQualityConstraintsConstraintCheckedOnReferencesId' => 'Q3',
				'WBQualityConstraintsCheckDurationInfoSeconds' => 1.0,
				'WBQualityConstraintsCheckDurationWarningSeconds' => 10.0,
				'WBQualityConstraintsCheckOnEntityDurationInfoSeconds' => 5.0,
				'WBQualityConstraintsCheckOnEntityDurationWarningSeconds' => 55.0,
				'WBQualityConstraintsIncludeDetailInApi' => true,
			] );
			$entityIdParser = new ItemIdParser();
			$constraintParameterParser = new ConstraintParameterParser(
				$config,
				$repo->getBaseDataModelDeserializerFactory(),
				$repo->getEntitySourceDefinitions()->getSourceForEntityType( 'item' )->getConceptBaseUri()
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
				$entityIdParser,
				new StatementGuidValidator( $entityIdParser ),
				$repo->getApiHelperFactory( RequestContext::getMain() ),
				new CheckingResultsSource(
					$constraintChecker
				),
				new CheckResultsRenderer(
					$repo->getEntityTitleLookup(),
					$entityIdFormatter,
					new ViolationMessageRenderer(
						$entityIdFormatter,
						$valueFormatter,
						new MockMessageLocalizer(),
						$config
					)
				),
				$dataFactory
			);
		};
	}

	protected function tearDown() : void {
		self::$constraintLookupContents = [];
		self::$checkerMap = [];
		parent::tearDown();
	}

	public static function tearDownAfterClass() : void {
		global $wgAPIModules;
		$wgAPIModules['wbcheckconstraints'] = self::$oldModuleDeclaration;

		parent::tearDownAfterClass();
	}

	public function testReportForEmptyItemIsEmpty() {
		$entityId = self::EMPTY_ITEM;
		$result = $this->doRequest(
			[ CheckConstraints::PARAM_ID => $entityId ]
		);

		$this->assertArrayHasKey( $entityId, $result['wbcheckconstraints'] );
		$this->assertArrayHasKey( 'claims', $result['wbcheckconstraints'][$entityId] );
		$this->assertEmpty( $result['wbcheckconstraints'][$entityId]['claims'] );
	}

	public function testReportForNonexistentItemIsEmpty() {
		$entityId = self::NONEXISTENT_ITEM;
		$result = $this->doRequest(
			[ CheckConstraints::PARAM_ID => $entityId ]
		);

		$this->assertArrayHasKey( $entityId, $result['wbcheckconstraints'] );
		$this->assertArrayHasKey( 'claims', $result['wbcheckconstraints'][$entityId] );
		$this->assertEmpty( $result['wbcheckconstraints'][$entityId]['claims'] );
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
		$this->givenPropertyHasStatus( new PropertyId( 'P1' ), CheckResult::STATUS_VIOLATION );

		$result = $this->doRequest( [ CheckConstraints::PARAM_ID => 'Q1' ] );

		$this->assertCount( 1, $result['wbcheckconstraints'] );
		$resultStatement = $result['wbcheckconstraints']['Q1']['claims']['P1'][0];
		$this->assertSame( 'Q1$46fc8ec9-4903-4592-9a0e-afdd1fa03183', $resultStatement['id'] );
		$resultsForItem = $resultStatement['mainsnak']['results'];
		$this->assertCount( 1, $resultsForItem );
		$this->assertSame( CheckResult::STATUS_WARNING, $resultsForItem[0]['status'] );
		$this->assertSame( 'P1', $resultsForItem[0]['property'] );
	}

	public function testItemWithClaimExistsAndHasViolation_WillGetOnlyThisViolationInTheResult() {
		$this->givenItemWithPropertyExists(
			new ItemId( 'Q1' ),
			new PropertyId( 'P1' ),
			'46fc8ec9-4903-4592-9a0e-afdd1fa03183'
		);
		$this->givenPropertyHasStatus( new PropertyId( 'P1' ), CheckResult::STATUS_VIOLATION );

		$result = $this->doRequest( [ CheckConstraints::PARAM_CLAIM_ID => 'Q1$46fc8ec9-4903-4592-9a0e-afdd1fa03183' ] );

		$this->assertCount( 1, $result['wbcheckconstraints'] );
		$resultStatement = $result['wbcheckconstraints']['Q1']['claims']['P1'][0];
		$this->assertSame( 'Q1$46fc8ec9-4903-4592-9a0e-afdd1fa03183', $resultStatement['id'] );
		$resultsForItem = $resultStatement['mainsnak']['results'];
		$this->assertCount( 1, $resultsForItem );
		$this->assertSame( CheckResult::STATUS_WARNING, $resultsForItem[0]['status'] );
		$this->assertSame( 'P1', $resultsForItem[0]['property'] );
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
		$this->givenPropertyHasStatus( new PropertyId( $propertyId ), CheckResult::STATUS_VIOLATION );

		$result = $this->doRequest( [ CheckConstraints::PARAM_CLAIM_ID => $guid ] );

		$this->assertCount( 1, $result['wbcheckconstraints'] );
		$resultStatement = $result['wbcheckconstraints']['Q1']['claims']['P1'][0];
		$this->assertSame( $guid, $resultStatement['id'] );
		$resultsForItem = $resultStatement['mainsnak']['results'];
		$this->assertCount( 1, $resultsForItem );
		$this->assertSame( CheckResult::STATUS_WARNING, $resultsForItem[0]['status'] );
		$this->assertSame( $propertyId, $resultsForItem[0]['property'] );
	}

	public function testStatusParameterFiltersResults() {
		$item = NewItem::withId( 'Q1' )
			->andStatement( NewStatement::noValueFor( 'P1' ) )
			->andStatement( NewStatement::noValueFor( 'P2' ) )
			->build();
		self::$entityLookup->addEntity( $item );
		$this->givenPropertyHasStatus( new PropertyId( 'P1' ), CheckResult::STATUS_VIOLATION );
		$this->givenPropertyHasStatus( new PropertyId( 'P2' ), CheckResult::STATUS_COMPLIANCE );

		$result = $this->doRequest( [
			CheckConstraints::PARAM_ID => 'Q1',
			CheckConstraints::PARAM_STATUS => CheckResult::STATUS_VIOLATION . '|' . CheckResult::STATUS_WARNING,
		] );

		$this->assertCount( 1, $result['wbcheckconstraints'] );
		$this->assertCount( 2, $result['wbcheckconstraints']['Q1']['claims'] );

		$violatingStatement = $result['wbcheckconstraints']['Q1']['claims']['P1'][0];
		$violatingStatementResults = $violatingStatement['mainsnak']['results'];
		$this->assertCount( 1, $violatingStatementResults );
		$this->assertSame( CheckResult::STATUS_WARNING, $violatingStatementResults[0]['status'] );
		$this->assertSame( 'P1', $violatingStatementResults[0]['property'] );

		$compliantStatement = $result['wbcheckconstraints']['Q1']['claims']['P2'][0];
		$compliantStatementResults = $compliantStatement['mainsnak']['results'];
		$this->assertCount( 0, $compliantStatementResults );
	}

	/**
	 * @param array $params
	 * @return array Array of violations
	 */
	private function doRequest( array $params ) {
		$params['action'] = 'wbcheckconstraints';
		return $this->doApiRequest( $params, [], false, null )[0];
	}

	private function givenPropertyHasStatus( PropertyId $propertyId, $status ) {
		static $itemIdNumber = 1234;
		$itemId = 'Q' . $itemIdNumber++;
		self::$checkerMap[$itemId] = new FakeChecker( $status );
		self::$constraintLookupContents[] = new Constraint(
			'P1234$6a4d1930-922b-4c2e-b6e1-9a06bf04c2f8',
			$propertyId,
			$itemId,
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
