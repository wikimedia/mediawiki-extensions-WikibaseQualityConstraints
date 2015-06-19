<?php

namespace WikibaseQuality\ConstraintReport\Tests\Specials\SpecialConstraintReport;

use Wikibase\Test\SpecialPageTestBase;
use DataValues\StringValue;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\Lib\ClaimGuidGenerator;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\DataModel\Entity\EntityId;
use WikibaseQuality\ConstraintReport\ConstraintReportFactory;
use WikibaseQuality\ConstraintReport\Specials\SpecialConstraintReport;
use WikibaseQuality\WikibaseQualityFactory;


/**
 * @covers WikibaseQuality\ConstraintReport\Specials\SpecialConstraintReport
 *
 * @group WikibaseQualityConstraints
 * @group Database
 * @group medium
 *
 * @uses   WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\SingleValueChecker
 * @uses   WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\MultiValueChecker
 * @uses   WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser
 * @uses   WikibaseQuality\ConstraintReport\ConstraintCheck\DelegatingConstraintChecker
 * @uses   WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 * @uses   WikibaseQuality\Html\HtmlTableBuilder
 * @uses   WikibaseQuality\Html\HtmlTableCellBuilder
 * @uses   WikibaseQuality\Html\HtmlTableHeaderBuilder
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class SpecialConstraintReportTest extends SpecialPageTestBase {

	/**
	 * Id of a item that (hopefully) does not exist.
	 */
	const NOT_EXISTENT_ITEM_ID = 'Q5678765432345678';

	/**
	 * @var EntityId[]
	 */
	private static $idMap;

	/**
	 * @var array
	 */
	private static $claimGuids = array ();

	/**
	 * @var bool
	 */
	private static $hasSetup;

	protected function setUp() {
		parent::setUp();
		$this->tablesUsed[ ] = CONSTRAINT_TABLE;
	}

	protected function tearDown() {
		parent::tearDown();
	}

	protected function newSpecialPage() {
		$wikibaseQuality = WikibaseQualityFactory::getDefaultInstance();
		$constraintReportFactory = ConstraintReportFactory::getDefaultInstance();
		$wikibaseRepo = WikibaseRepo::getDefaultInstance();

		return new SpecialConstraintReport(
			$wikibaseRepo->getEntityLookup(),
			$wikibaseRepo->getTermLookup(),
			$wikibaseRepo->getEntityTitleLookup(),
			$wikibaseRepo->getEntityIdParser(),
			$wikibaseRepo->getValueFormatterFactory(),
			$constraintReportFactory->getConstraintChecker(),
			$constraintReportFactory->getCheckResultToViolationTranslator(),
			$wikibaseQuality->getViolationStore()
		);
	}

	/**
	 * Adds temporary test data to database
	 *
	 * @throws \DBUnexpectedError
	 */
	public function addDBData() {
		if ( !self::$hasSetup ) {
			$store = WikibaseRepo::getDefaultInstance()->getEntityStore();

			$propertyP1 = Property::newFromType( 'string' );
			$store->saveEntity( $propertyP1, 'TestEntityP1', $GLOBALS[ 'wgUser' ], EDIT_NEW );
			self::$idMap[ 'P1' ] = $propertyP1->getId();

			$itemQ1 = new Item();
			$store->saveEntity( $itemQ1, 'TestEntityQ1', $GLOBALS[ 'wgUser' ], EDIT_NEW );
			self::$idMap[ 'Q1' ] = $itemQ1->getId();

			$statementGuidGenerator = new ClaimGuidGenerator();

			$dataValue = new StringValue( 'foo' );
			$snak = new PropertyValueSnak( self::$idMap[ 'P1' ], $dataValue );
			$statement = new Statement( $snak );
			$statementGuid = $statementGuidGenerator->newGuid( self::$idMap[ 'Q1' ] );
			self::$claimGuids[ 'P1' ] = $statementGuid;
			$statement->setGuid( $statementGuid );
			$itemQ1->getStatements()->addStatement( $statement );

			$store->saveEntity( $itemQ1, 'TestEntityQ1', $GLOBALS[ 'wgUser' ], EDIT_UPDATE );

			self::$hasSetup = true;
		}

		// Truncate table
		$this->db->delete(
			CONSTRAINT_TABLE,
			'*'
		);

		$this->db->insert(
			CONSTRAINT_TABLE,
			array (
				array (
					'constraint_guid' => '1',
					'pid' => self::$idMap[ 'P1' ]->getNumericId(),
					'constraint_type_qid' => 'Multi value',
					'constraint_parameters' => '{}'
				),
				array (
					'constraint_guid' => '3',
					'pid' => self::$idMap[ 'P1' ]->getNumericId(),
					'constraint_type_qid' => 'Single value',
					'constraint_parameters' => '{}'
				)
			)
		);
	}

	/**
	 * @dataProvider executeProvider
	 */
	public function testExecute( $subPage, $request, $userLanguage, $matchers ) {
		$request = new \FauxRequest( $request );

		// the added item is Q1; this solves the problem that the provider is executed before the test
		$id = self::$idMap[ 'Q1' ];
		$subPage = str_replace( '$id', $id->getSerialization(), $subPage );

		// assert matchers
		list( $output, ) = $this->executeSpecialPage( $subPage, $request, $userLanguage );
		foreach ( $matchers as $key => $matcher ) {
			$this->assertTag( $matcher, $output, "Failed to assert output: $key" );
		}
	}

	public function executeProvider() {
		$userLanguage = 'qqx';
		$cases = array ();
		$matchers = array ();

		// Empty input
		$matchers[ 'explanationOne' ] = array (
			'tag' => 'div',
			'content' => '(wbqc-constraintreport-explanation-part-one)'
		);

		$matchers[ 'explanationTwo' ] = array (
			'tag' => 'div',
			'content' => '(wbqc-constraintreport-explanation-part-two)'
		);

		$matchers[ 'entityId' ] = array (
			'tag' => 'input',
			'attributes' => array (
				'placeholder' => '(wbqc-constraintreport-form-entityid-placeholder)',
				'name' => 'entityid',
				'class' => 'wbqc-constraintreport-form-entity-id'
			)
		);

		$matchers[ 'submit' ] = array (
			'tag' => 'input',
			'attributes' => array (
				'type' => 'submit',
				'value' => '(wbqc-constraintreport-form-submit-label)',
			)
		);

		$cases[ 'empty' ] = array ( '', array (), $userLanguage, $matchers );

		// Invalid input
		$matchers[ 'error' ] = array (
			'tag' => 'p',
			'attributes' => array (
				'class' => 'wbqc-constraintreport-notice wbqc-constraintreport-notice-error'
			),
			'content' => '(wbqc-constraintreport-invalid-entity-id)'
		);

		$cases[ 'invalid input 1' ] = array ( 'Qwertz', array (), $userLanguage, $matchers );
		$cases[ 'invalid input 2' ] = array ( '300', array (), $userLanguage, $matchers );

		// Valid input but entity does not exist
		unset( $matchers[ 'error' ] );
		$matchers[ 'error' ] = array (
			'tag' => 'p',
			'attributes' => array (
				'class' => 'wbqc-constraintreport-notice wbqc-constraintreport-notice-error'
			),
			'content' => '(wbqc-constraintreport-not-existent-entity)'
		);

		$cases[ 'valid input - not existing item' ] = array (
			self::NOT_EXISTENT_ITEM_ID,
			array (),
			$userLanguage,
			$matchers
		);

		// Valid input and entity exists
		unset( $matchers[ 'error' ] );
		$matchers[ 'result for' ] = array (
			'tag' => 'h3',
			'content' => '(wbqc-constraintreport-result-headline)'
		);

		$matchers[ 'result table' ] = array (
			'tag' => 'table',
			'attributes' => array (
				'class' => 'wikitable sortable jquery-tablesort'
			)
		);

		$matchers[ 'column status' ] = array (
			'tag' => 'th',
			'attributes' => array (
				'role' => 'columnheader button'
			),
			'content' => '(wbqc-constraintreport-result-table-header-status)'
		);

		$matchers[ 'column claim' ] = array (
			'tag' => 'th',
			'attributes' => array (
				'role' => 'columnheader button'
			),
			'content' => '(wbqc-constraintreport-result-table-header-claim)'
		);

		$matchers[ 'column constraint' ] = array (
			'tag' => 'th',
			'attributes' => array (
				'role' => 'columnheader button'
			),
			'content' => '(wbqc-constraintreport-result-table-header-constraint)'
		);

		$matchers[ 'value status - violation' ] = array (
			'tag' => 'span',
			'attributes' => array (
				'class' => 'wbqc-status wbqc-status-violation'
			),
			'content' => '(wbqc-constraintreport-status-violation)'
		);

		$matchers[ 'value status - compliance' ] = array (
			'tag' => 'span',
			'attributes' => array (
				'class' => 'wbqc-status wbqc-status-compliance'
			),
			'content' => '(wbqc-constraintreport-status-compliance)'
		);

		$cases[ 'valid input - existing item' ] = array ( '$id', array (), $userLanguage, $matchers );

		return $cases;
	}

}