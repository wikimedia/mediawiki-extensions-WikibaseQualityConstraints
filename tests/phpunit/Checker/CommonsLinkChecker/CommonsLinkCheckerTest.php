<?php

namespace WikibaseQuality\ConstraintReport\Test\CommonsLinkChecker;

use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use DataValues\StringValue;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\CommonsLinkChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;

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

	/**
	 * @var ConstraintParameterParser
	 */
	private $helper;

	/**
	 * @var CommonsLinkChecker
	 */
	private $commonsLinkChecker;

	protected function setUp() {
		parent::setUp();
		$this->helper = new ConstraintParameterParser();
		$this->commonsLinkChecker = new CommonsLinkChecker( $this->helper );
		$this->tablesUsed[] = 'image';
	}

	protected function tearDown() {
		unset( $this->helper );
		unset( $this->commonsLinkChecker );
		parent::tearDown();
	}

	public function addDBData() {
		$this->db->delete('image', '*');

		$this->db->insert('image', [
				'img_name' => 'test_image.jpg',
				'img_size' => '42',
				'img_width' => '7',
				'img_height' => '6',
				'img_metadata' => 'test_blob',
				'img_bits' => '42',
				'img_major_mime' => 'image',
				'img_minor_mime' => 'unknown',
				'img_description' => 'image entry for testing',
				'img_user' => '1234',
				'img_user_text' => 'yomamma',
				'img_timestamp' => '201501010000',
				'img_sha1' => '8843d7f92416211de9ebb963ff4ce28125932878'
			]
		);
	}

	public function testCommonsLinkConstraintValid() {
		$value = new StringValue( 'test image.jpg' );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P1' ), $value ) );

		$result = $this->commonsLinkChecker->checkConstraint(
			$statement,
			$this->getConstraintMock( [ 'namespace' => 'File' ] ),
			$this->getEntity()
		);
		$this->assertEquals( 'compliance', $result->getStatus(), 'check should comply' );
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
			$this->getConstraintMock( [ 'namespace' => 'File' ] ),
			$this->getEntity()
		);
		$this->assertEquals( 'violation', $result->getStatus(), 'check should not comply' );

		$result = $this->commonsLinkChecker->checkConstraint(
			$statement2,
			$this->getConstraintMock( [ 'namespace' => 'File' ] ),
			$this->getEntity()
		);
		$this->assertEquals( 'violation', $result->getStatus(), 'check should not comply' );

		$result = $this->commonsLinkChecker->checkConstraint(
			$statement3,
			$this->getConstraintMock( [ 'namespace' => 'File' ] ),
			$this->getEntity()
		);
		$this->assertEquals( 'violation', $result->getStatus(), 'check should not comply' );
	}

	public function testNotImplementedNamespaces() {
		$value = new StringValue( 'test image.jpg' );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P1' ), $value ) );

		$result = $this->commonsLinkChecker->checkConstraint(
			$statement,
			$this->getConstraintMock( [] ),
			$this->getEntity()
		);
		$this->assertEquals( 'todo', $result->getStatus(), 'check is not implemented' );

		$result = $this->commonsLinkChecker->checkConstraint(
			$statement,
			$this->getConstraintMock( [ 'namespace' => 'Gallery' ] ),
			$this->getEntity()
		);
		$this->assertEquals( 'todo', $result->getStatus(), 'check is not implemented' );

		$result = $this->commonsLinkChecker->checkConstraint(
			$statement,
			$this->getConstraintMock( [ 'namespace' => 'Institution' ] ),
			$this->getEntity()
		);
		$this->assertEquals( 'todo', $result->getStatus(), 'check is not implemented' );

		$result = $this->commonsLinkChecker->checkConstraint(
			$statement,
			$this->getConstraintMock( [ 'namespace' => 'Museum' ] ),
			$this->getEntity()
		);
		$this->assertEquals( 'todo', $result->getStatus(), 'check is not implemented' );

		$result = $this->commonsLinkChecker->checkConstraint(
			$statement,
			$this->getConstraintMock( [ 'namespace' => 'Creator' ] ),
			$this->getEntity()
		);
		$this->assertEquals( 'todo', $result->getStatus(), 'check is not implemented' );
	}

	public function testCommonsLinkConstraintNotExistent() {
		$value = new StringValue( 'no image.jpg' );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P1' ), $value ) );

		$result = $this->commonsLinkChecker->checkConstraint(
			$statement,
			$this->getConstraintMock( [ 'namespace' => 'File' ] ),
			$this->getEntity()
		);
		$this->assertEquals( 'violation', $result->getStatus(), 'check should not comply' );
	}

	public function testCommonsLinkConstraintNoStringValue() {
		$value = new EntityIdValue( new ItemId( 'Q1' ) );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P1' ), $value ) );

		$result = $this->commonsLinkChecker->checkConstraint(
			$statement,
			$this->getConstraintMock( [ 'namespace' => 'File' ] ),
			$this->getEntity()
		);
		$this->assertEquals( 'violation', $result->getStatus(), 'check should not comply' );
	}

	public function testCommonsLinkConstraintNoValueSnak() {
		$statement = new Statement( new PropertyNoValueSnak( 1 ) );

		$result = $this->commonsLinkChecker->checkConstraint(
			$statement,
			$this->getConstraintMock( [ 'namespace' => 'File' ] ),
			$this->getEntity()
		);
		$this->assertEquals( 'violation', $result->getStatus(), 'check should not comply' );
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
			 ->method( 'getConstraintTypeQid' )
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
