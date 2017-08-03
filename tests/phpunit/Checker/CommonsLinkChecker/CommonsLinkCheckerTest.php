<?php

namespace WikibaseQuality\ConstraintReport\Test\CommonsLinkChecker;

use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use DataValues\StringValue;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\Repo\Tests\NewItem;
use Wikibase\Repo\Tests\NewStatement;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\CommonsLinkChecker;
use WikibaseQuality\ConstraintReport\Tests\ConstraintParameters;
use WikibaseQuality\ConstraintReport\Tests\ResultAssertions;
use WikibaseQuality\ConstraintReport\Tests\TitleParserMock;

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
		$this->db->insert(
			'page',
			[
				'page_id' => '1',
				'page_namespace' => NS_FILE,
				'page_title' => 'Test_image.jpg'
			]
		);
		$this->db->insert(
			'page',
			[
				'page_id' => '2',
				'page_namespace' => NS_CATEGORY,
				'page_title' => 'Test_category'
			]
		);
		$this->db->insert(
			'page',
			[
				'page_id' => '3',
				'page_namespace' => NS_MAIN,
				'page_title' => 'Test_gallery'
			]
		);
	}

	public function testCommonsLinkConstraintValid() {
		$value = new StringValue( 'test image.jpg' );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P1' ), $value ) );

		$result = $this->commonsLinkChecker->checkConstraint(
			$statement,
			$this->getConstraintMock( $this->namespaceParameter( 'File' ) ),
			$this->getEntity()
		);
		$this->assertCompliance( $result );
	}

	public function testCommonsLinkConstraintInvalid() {
		$value1 = new StringValue( 'test_image.jpg' );
		$value2 = new StringValue( 'test%20image.jpg' );
		$value3 = new StringValue( 'File:test image.jpg' );
		$statement1 = new Statement( new PropertyValueSnak( new PropertyId( 'P1' ), $value1 ) );
		$statement2 = new Statement( new PropertyValueSnak( new PropertyId( 'P1' ), $value2 ) );
		$statement3 = new Statement( new PropertyValueSnak( new PropertyId( 'P1' ), $value3 ) );

		$result = $this->commonsLinkChecker->checkConstraint(
			$statement1,
			$this->getConstraintMock( $this->namespaceParameter( 'File' ) ),
			$this->getEntity()
		);
		$this->assertViolation( $result, 'wbqc-violation-message-commons-link-not-well-formed' );

		$result = $this->commonsLinkChecker->checkConstraint(
			$statement2,
			$this->getConstraintMock( $this->namespaceParameter( 'File' ) ),
			$this->getEntity()
		);
		$this->assertViolation( $result, 'wbqc-violation-message-commons-link-not-well-formed' );

		$result = $this->commonsLinkChecker->checkConstraint(
			$statement3,
			$this->getConstraintMock( $this->namespaceParameter( 'File' ) ),
			$this->getEntity()
		);
		$this->assertViolation( $result, 'wbqc-violation-message-commons-link-not-well-formed' );
	}

	public function testCommonsLinkConstraintValidCategory() {
		$value = new StringValue( 'test category' );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P1' ), $value ) );

		$result = $this->commonsLinkChecker->checkConstraint(
			$statement,
			$this->getConstraintMock( $this->namespaceParameter( 'Category' ) ),
			$this->getEntity()
		);
		$this->assertCompliance( $result );
	}

	public function testCommonsLinkConstraintValidGallery() {
		$value = new StringValue( 'test gallery' );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P1' ), $value ) );

		$result = $this->commonsLinkChecker->checkConstraint(
			$statement,
			$this->getConstraintMock( $this->namespaceParameter( '' ) ),
			$this->getEntity()
		);

		$this->assertCompliance( $result );
	}

	public function testCommonsLinkConstraintNotExistent() {
		$value = new StringValue( 'no image.jpg' );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P1' ), $value ) );

		$result = $this->commonsLinkChecker->checkConstraint(
			$statement,
			$this->getConstraintMock( $this->namespaceParameter( 'File' ) ),
			$this->getEntity()
		);
		$this->assertViolation( $result, 'wbqc-violation-message-commons-link-no-existent' );
	}

	public function testCommonsLinkConstraintNoStringValue() {
		$value = new EntityIdValue( new ItemId( 'Q1' ) );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P1' ), $value ) );

		$result = $this->commonsLinkChecker->checkConstraint(
			$statement,
			$this->getConstraintMock( $this->namespaceParameter( 'File' ) ),
			$this->getEntity()
		);
		$this->assertViolation( $result, 'wbqc-violation-message-value-needed-of-type' );
	}

	public function testCommonsLinkConstraintNoValueSnak() {
		$statement = NewStatement::noValueFor( 'P1' )->build();

		$result = $this->commonsLinkChecker->checkConstraint(
			$statement,
			$this->getConstraintMock( $this->namespaceParameter( 'File' ) ),
			$this->getEntity()
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

		$checkResult = $this->commonsLinkChecker->checkConstraint( $statement, $constraint, $entity );

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
