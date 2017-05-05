<?php

namespace WikibaseQuality\ConstraintReport\Tests\Api;

use ApiTestCase;
use DataValues\UnknownValue;
use RequestContext;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\ItemIdParser;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\Lookup\InMemoryEntityLookup;
use Wikibase\DataModel\Services\Statement\StatementGuidParser;
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
 * @covers \WikibaseQuality\ConstraintReport\Api\CheckConstraints
 *
 * @group API
 * @group Database
 * @group Wikibase
 * @group WikibaseAPI
 *
 * @group medium
 *
 * @license GPL-2.0+
 */
class CheckConstraintsTest extends ApiTestCase {

	const NONEXISTENT_ITEM = 'Q99';
	const NONEXISTENT_CLAIM = 'Q99$does-not-exist';

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
			$fallbackLabelDescLookupFactory = new LanguageFallbackLabelDescriptionLookupFactory( $languageFallbackChainFactory, $termLookup, $termBuffer );
			$language = new Language();
			$labelLookup = $fallbackLabelDescLookupFactory->newLabelDescriptionLookup( $language );

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

			$entityIdParser = new ItemIdParser();
			$constraintChecker = new DelegatingConstraintChecker(
				self::$entityLookup,
				self::$checkerMap,
				new InMemoryConstraintLookup( self::$constraintLookupContents )
			);

			return new CheckConstraints(
				$main,
				$name,
				'',
				$entityIdParser,
				new StatementGuidValidator( $entityIdParser ),
				new StatementGuidParser( $entityIdParser ),
				$constraintChecker,
				new ConstraintParameterRenderer( $factory->getEntityIdFormatter( $labelLookup ), $valueFormatter ),
				$repo->getApiHelperFactory( RequestContext::getMain() )
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
			'statement-id'
		);
		$this->givenPropertyHasViolation( new PropertyId( 'P1' ) );

		$result = $this->doRequest( [ CheckConstraints::PARAM_ID => 'Q1' ] );

		$this->assertCount( 1, $result['wbcheckconstraints'] );
		$resultsForItem = $result['wbcheckconstraints']['Q1']['P1']['Q1$statement-id'];
		$this->assertCount( 1, $resultsForItem );
		$this->assertEquals( CheckResult::STATUS_VIOLATION, $resultsForItem[0]['status'] );
		$this->assertEquals( 'P1', $resultsForItem[0]['property'] );
	}

	public function testItemWithClaimExistsAndHasViolation_WillGetOnlyThisViolationInTheResult() {
		$this->givenItemWithPropertyExists(
			new ItemId( 'Q1' ),
			new PropertyId( 'P1' ),
			'statement-id'
		);
		$this->givenPropertyHasViolation( new PropertyId( 'P1' ) );

		$result = $this->doRequest( [ CheckConstraints::PARAM_CLAIM_ID => 'Q1$statement-id' ] );

		$this->assertCount( 1, $result['wbcheckconstraints'] );
		$resultsForItem = $result['wbcheckconstraints']['Q1']['P1']['Q1$statement-id'];
		$this->assertCount( 1, $resultsForItem );
		$this->assertEquals( CheckResult::STATUS_VIOLATION, $resultsForItem[0]['status'] );
		$this->assertEquals( 'P1', $resultsForItem[0]['property'] );
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
		self::$checkerMap['violationConstraint'] = new FakeChecker( CheckResult::STATUS_VIOLATION );
		self::$constraintLookupContents[] = new Constraint(
			'some guid',
			$propertyId,
			'violationConstraint',
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
