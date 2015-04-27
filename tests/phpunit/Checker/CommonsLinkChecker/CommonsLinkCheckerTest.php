<?php

namespace WikidataQuality\ConstraintReport\Test\CommonsLinkChecker;

use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Claim\Claim;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use DataValues\StringValue;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use WikidataQuality\ConstraintReport\ConstraintCheck\Checker\CommonsLinkChecker;
use WikidataQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper;


/**
 * @covers WikidataQuality\ConstraintReport\ConstraintCheck\Checker\CommonsLinkChecker
 *
 * @uses   WikidataQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 * @uses   WikidataQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper
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

	public function testcheckConstraintValid() {
		$value = new StringValue( 'President Barack Obama.jpg' );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P1' ), $value ) ) );
		$this->assertEquals( 'compliance', $this->commonsLinkChecker->checkConstraint( $statement, array( 'namespace' => 'File' ) )->getStatus(), 'check should comply' );
	}

	public function testcheckConstraintInvalid() {
		$value1 = new StringValue( 'President_Barack_Obama.jpg' );
		$value2 = new StringValue( 'President%20Barack%20Obama.jpg' );
		$value3 = new StringValue( 'File:President Barack Obama.jpg' );
		$statement1 = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P1' ), $value1 ) ) );
		$statement2 = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P1' ), $value2 ) ) );
		$statement3 = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P1' ), $value3 ) ) );
		$this->assertEquals( 'violation', $this->commonsLinkChecker->checkConstraint( $statement1, array( 'namespace' => 'File' ) )->getStatus(), 'check should not comply' );
		$this->assertEquals( 'violation', $this->commonsLinkChecker->checkConstraint( $statement2, array( 'namespace' => 'File' ) )->getStatus(), 'check should not comply' );
		$this->assertEquals( 'violation', $this->commonsLinkChecker->checkConstraint( $statement3, array( 'namespace' => 'File' ) )->getStatus(), 'check should not comply' );
	}

	public function testcheckConstraintWithoutNamespace() {
		$value = new StringValue( 'President Barack Obama.jpg' );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P1' ), $value ) ) );
		$this->assertEquals( 'violation', $this->commonsLinkChecker->checkConstraint( $statement, null )->getStatus(), 'check should not comply' );
	}

	public function testcheckConstraintNotExistent() {
		$value = new StringValue( 'Qwertz Asdfg Yxcv.jpg' );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P1' ), $value ) ) );
		$this->assertEquals( 'violation', $this->commonsLinkChecker->checkConstraint( $statement, array( 'namespace' => 'File' ) )->getStatus(), 'check should not comply' );
	}

	public function testcheckConstraintNoStringValue() {
		$value = new EntityIdValue( new ItemId( 'Q1' ) );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P1' ), $value ) ) );
		$this->assertEquals( 'violation', $this->commonsLinkChecker->checkConstraint( $statement, array( 'namespace' => 'File' ) )->getStatus(), 'check should not comply' );
	}

}