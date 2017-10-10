<?php

namespace WikibaseQuality\ConstraintReport\Test\CommonsLinkChecker;

use Title;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use DataValues\StringValue;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\Repo\Tests\NewItem;
use Wikibase\Repo\Tests\NewStatement;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\CommonsLinkChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\StatementContext;
use WikibaseQuality\ConstraintReport\Tests\ConstraintParameters;
use WikibaseQuality\ConstraintReport\Tests\Fake\FakeSnakContext;
use WikibaseQuality\ConstraintReport\Tests\ResultAssertions;
use WikibaseQuality\ConstraintReport\Tests\TitleParserMock;
use WikiPage;

/**
 * @covers \WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\CommonsLinkChecker
 *
 * @group WikibaseQualityConstraints
 * @group Database
 * @group medium
 *
 * @uses   \WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 * @uses   \WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class CommonsLinkCheckerTest extends \MediaWikiTestCase {

	use ConstraintParameters, ResultAssertions, TitleParserMock;

	/**
	 * @var CommonsLinkChecker
	 */
	private $commonsLinkChecker;

	protected function setUp() {
		parent::setUp();
		$this->commonsLinkChecker = new CommonsLinkChecker(
			$this->getConstraintParameterParser(),
			$this->getConstraintParameterRenderer(),
			$this->getTitleParserMock()
		);
		$this->tablesUsed[] = 'page';
	}

	public function addDBData() {
		$this->db->delete( 'page', '*' );

		$pages = [
			[ NS_FILE, 'Test_image.jpg' ],
			[ NS_CATEGORY, 'Test_category' ],
			[ NS_MAIN, 'Test_gallery' ],
			[ 100, 'Test_creator' ],
		];

		foreach ( $pages as list( $namespace, $title ) ) {
			WikiPage::factory( Title::makeTitle( $namespace, $title ) )
				->insertOn( $this->db );
			/*
			 * insertOn() says it *must* be followed by creating a revision and such,
			 * but that’s just way too slow, and apparently not necessary for tests.
			 * (But in case this breaks: try ->doEditContent( new TextContent( '' ), 'summary' );
			 * instead of ->insertOn(), which is the method that does everything together.)
			 */
		}
	}

	public function testCommonsLinkConstraintValid() {
		$value = new StringValue( 'test image.jpg' );
		$snak = new PropertyValueSnak( new PropertyId( 'P1' ), $value );

		$result = $this->commonsLinkChecker->checkConstraint(
			new FakeSnakContext( $snak ),
			$this->getConstraintMock( $this->namespaceParameter( 'File' ) )
		);
		$this->assertCompliance( $result );
	}

	public function testCommonsLinkConstraintInvalid() {
		$value1 = new StringValue( 'test_image.jpg' );
		$value2 = new StringValue( 'test%20image.jpg' );
		$value3 = new StringValue( 'File:test image.jpg' );
		$snak1 = new PropertyValueSnak( new PropertyId( 'P1' ), $value1 );
		$snak2 = new PropertyValueSnak( new PropertyId( 'P1' ), $value2 );
		$snak3 = new PropertyValueSnak( new PropertyId( 'P1' ), $value3 );

		$result = $this->commonsLinkChecker->checkConstraint(
			new FakeSnakContext( $snak1 ),
			$this->getConstraintMock( $this->namespaceParameter( 'File' ) )
		);
		$this->assertViolation( $result, 'wbqc-violation-message-commons-link-not-well-formed' );

		$result = $this->commonsLinkChecker->checkConstraint(
			new FakeSnakContext( $snak2 ),
			$this->getConstraintMock( $this->namespaceParameter( 'File' ) )
		);
		$this->assertViolation( $result, 'wbqc-violation-message-commons-link-not-well-formed' );

		$result = $this->commonsLinkChecker->checkConstraint(
			new FakeSnakContext( $snak3 ),
			$this->getConstraintMock( $this->namespaceParameter( 'File' ) )
		);
		$this->assertViolation( $result, 'wbqc-violation-message-commons-link-not-well-formed' );
	}

	public function testCommonsLinkConstraintValidCategory() {
		$value = new StringValue( 'test category' );
		$snak = new PropertyValueSnak( new PropertyId( 'P1' ), $value );

		$result = $this->commonsLinkChecker->checkConstraint(
			new FakeSnakContext( $snak ),
			$this->getConstraintMock( $this->namespaceParameter( 'Category' ) )
		);
		$this->assertCompliance( $result );
	}

	public function testCommonsLinkConstraintValidGallery() {
		$value = new StringValue( 'test gallery' );
		$snak = new PropertyValueSnak( new PropertyId( 'P1' ), $value );

		$result = $this->commonsLinkChecker->checkConstraint(
			new FakeSnakContext( $snak ),
			$this->getConstraintMock( $this->namespaceParameter( '' ) )
		);

		$this->assertCompliance( $result );
	}

	public function testCommonsLinkConstraintValidCreator() {
		$value = new StringValue( 'test creator' );
		$snak = new PropertyValueSnak( new PropertyId( 'P1' ), $value );

		$result = $this->commonsLinkChecker->checkConstraint(
			new FakeSnakContext( $snak ),
			$this->getConstraintMock( $this->namespaceParameter( 'Creator' ) )
		);

		$this->assertCompliance( $result );
	}

	public function testCommonsLinkConstraintNotExistent() {
		$value = new StringValue( 'no image.jpg' );
		$snak = new PropertyValueSnak( new PropertyId( 'P1' ), $value );

		$result = $this->commonsLinkChecker->checkConstraint(
			new FakeSnakContext( $snak ),
			$this->getConstraintMock( $this->namespaceParameter( 'File' ) )
		);
		$this->assertViolation( $result, 'wbqc-violation-message-commons-link-no-existent' );
	}

	public function testCommonsLinkConstraintNoStringValue() {
		$value = new EntityIdValue( new ItemId( 'Q1' ) );
		$snak = new PropertyValueSnak( new PropertyId( 'P1' ), $value );

		$result = $this->commonsLinkChecker->checkConstraint(
			new FakeSnakContext( $snak ),
			$this->getConstraintMock( $this->namespaceParameter( 'File' ) )
		);
		$this->assertViolation( $result, 'wbqc-violation-message-value-needed-of-type' );
	}

	public function testCommonsLinkConstraintNoValueSnak() {
		$snak = new PropertyNoValueSnak( new PropertyId( 'P1' ) );

		$result = $this->commonsLinkChecker->checkConstraint(
			new FakeSnakContext( $snak ),
			$this->getConstraintMock( $this->namespaceParameter( 'File' ) )
		);
		$this->assertCompliance( $result );
	}

	public function testCommonsLinkConstraintDeprecatedStatement() {
		$statement = NewStatement::forProperty( 'P1' )
				   ->withValue( 'not_well formed' )
				   ->withDeprecatedRank()
				   ->build();
		$constraint = $this->getConstraintMock( [] );
		$entity = NewItem::withId( 'Q1' )
				->build();

		$checkResult = $this->commonsLinkChecker->checkConstraint( new StatementContext( $entity, $statement ), $constraint );

		// this constraint is still checked on deprecated statements
		$this->assertViolation( $checkResult, 'wbqc-violation-message-commons-link-not-well-formed' );
	}

	public function testCheckConstraintParameters() {
		$namespaceId = $this->getDefaultConfig()->get( 'WBQualityConstraintsNamespaceId' );
		$constraint = $this->getConstraintMock( [ $namespaceId => [] ] );

		$result = $this->commonsLinkChecker->checkConstraintParameters( $constraint );

		$this->assertCount( 1, $result );
	}

	/**
	 * @param string[] $parameters
	 *
	 * @return Constraint
	 */
	private function getConstraintMock( array $parameters ) {
		$mock = $this
			->getMockBuilder( Constraint::class )
			->disableOriginalConstructor()
			->getMock();
		$mock->expects( $this->any() )
			 ->method( 'getConstraintParameters' )
			 ->will( $this->returnValue( $parameters ) );
		$mock->expects( $this->any() )
			 ->method( 'getConstraintTypeItemId' )
			 ->will( $this->returnValue( 'Commons link' ) );

		return $mock;
	}

	/**
	 * @return EntityDocument
	 */
	private function getEntity() {
		return new Item( new ItemId( 'Q1' ) );
	}

}
