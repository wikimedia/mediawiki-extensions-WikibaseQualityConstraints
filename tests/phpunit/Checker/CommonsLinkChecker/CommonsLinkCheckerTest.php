<?php

namespace WikibaseQuality\ConstraintReport\Test\CommonsLinkChecker;

use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Claim\Claim;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use DataValues\StringValue;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\CommonsLinkChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper;


/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\CommonsLinkChecker
 *
 * @group WikibaseQualityConstraints
 *
 * @uses   WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 * @uses   WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class CommonsLinkCheckerTest extends \MediaWikiTestCase {

	private $helper;
	private $commonsLinkChecker;

	protected function setUp() {
		parent::setUp();
		$this->helper = new ConstraintReportHelper();
		$this->commonsLinkChecker = new CommonsLinkChecker( $this->helper );
	}

	protected function tearDown() {
		unset( $this->helper );
		unset( $this->commonsLinkChecker );
		parent::tearDown();
	}

	public function testCommonsLinkConstraintValid() {
		$value = new StringValue( 'President Barack Obama.jpg' );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P1' ), $value ) ) );
		$this->assertEquals( 'compliance', $this->commonsLinkChecker->checkConstraint( $statement, $this->getConstraintMock( array( 'namespace' => 'File' ) ) )->getStatus(), 'check should comply' );
	}

	public function testCommonsLinkConstraintInvalid() {
		$value1 = new StringValue( 'President_Barack_Obama.jpg' );
		$value2 = new StringValue( 'President%20Barack%20Obama.jpg' );
		$value3 = new StringValue( 'File:President Barack Obama.jpg' );
		$statement1 = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P1' ), $value1 ) ) );
		$statement2 = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P1' ), $value2 ) ) );
		$statement3 = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P1' ), $value3 ) ) );
		$this->assertEquals( 'violation', $this->commonsLinkChecker->checkConstraint( $statement1, $this->getConstraintMock( array( 'namespace' => 'File' ) ) )->getStatus(), 'check should not comply' );
		$this->assertEquals( 'violation', $this->commonsLinkChecker->checkConstraint( $statement2, $this->getConstraintMock( array( 'namespace' => 'File' ) ) )->getStatus(), 'check should not comply' );
		$this->assertEquals( 'violation', $this->commonsLinkChecker->checkConstraint( $statement3, $this->getConstraintMock( array( 'namespace' => 'File' ) ) )->getStatus(), 'check should not comply' );
	}

	public function testCommonsLinkConstraintWithoutNamespace() {
		$value = new StringValue( 'President Barack Obama.jpg' );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P1' ), $value ) ) );
		$this->assertEquals( 'violation', $this->commonsLinkChecker->checkConstraint( $statement, $this->getConstraintMock( null ) )->getStatus(), 'check should not comply' );
	}

	public function testCommonsLinkConstraintNotExistent() {
		$value = new StringValue( 'Qwertz Asdfg Yxcv.jpg' );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P1' ), $value ) ) );
		$this->assertEquals( 'violation', $this->commonsLinkChecker->checkConstraint( $statement, $this->getConstraintMock( array( 'namespace' => 'File' ) ) )->getStatus(), 'check should not comply' );
	}

	public function testCommonsLinkConstraintNoStringValue() {
		$value = new EntityIdValue( new ItemId( 'Q1' ) );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P1' ), $value ) ) );
		$this->assertEquals( 'violation', $this->commonsLinkChecker->checkConstraint( $statement, $this->getConstraintMock( array( 'namespace' => 'File' ) ) )->getStatus(), 'check should not comply' );
	}

	public function testCommonsLinkConstraintNoValueSnak() {
		$statement = new Statement( new Claim( new PropertyNoValueSnak( 1 ) ) );
		$this->assertEquals( 'violation', $this->commonsLinkChecker->checkConstraint( $statement, $this->getConstraintMock( array( 'namespace' => 'File' ) ) )->getStatus(), 'check should not comply' );
	}

	private function getConstraintMock( $parameter ) {
		$mock = $this
			->getMockBuilder( 'WikibaseQuality\ConstraintReport\Constraint' )
			->disableOriginalConstructor()
			->getMock();
		$mock->expects( $this->any() )
			 ->method( 'getConstraintParameters' )
			 ->willReturn( $parameter );
		$mock->expects( $this->any() )
			 ->method( 'getConstraintTypeQid' )
			 ->willReturn( 'Commons link' );

		return $mock;
	}

}