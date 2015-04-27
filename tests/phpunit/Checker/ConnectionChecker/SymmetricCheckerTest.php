<?php

namespace WikidataQuality\ConstraintReport\Test\ConnectionChecker;

use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Claim\Claim;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use DataValues\StringValue;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use WikidataQuality\ConstraintReport\ConstraintCheck\Checker\SymmetricChecker;
use WikidataQuality\ConstraintReport\ConstraintCheck\Helper\ConnectionCheckerHelper;
use WikidataQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper;
use WikidataQuality\Tests\Helper\JsonFileEntityLookup;


/**
 * @covers WikidataQuality\ConstraintReport\ConstraintCheck\Checker\SymmetricChecker
 *
 * @uses   WikidataQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 * @uses   WikidataQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class SymmetricCheckerTest extends \MediaWikiTestCase {

	private $lookup;
	private $helper;
	private $connectionCheckerHelper;
	private $checker;
	private $entity;

	protected function setUp() {
		parent::setUp();
		$this->lookup = new JsonFileEntityLookup( __DIR__ );
		$this->helper = new ConstraintReportHelper();
		$this->connectionCheckerHelper = new ConnectionCheckerHelper();
		$this->checker = new SymmetricChecker( $this->lookup, $this->helper, $this->connectionCheckerHelper );
		$this->entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
	}

	protected function tearDown() {
		unset( $this->lookup );
		unset( $this->helper );
		unset( $this->connectionCheckerHelper );
		unset( $this->checker );
		unset( $this->entity );
		parent::tearDown();
	}
	
	public function testCheckSymmetricConstraintWithCorrectSpouse() {
		$value = new EntityIdValue( new ItemId( 'Q3' ) );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) ) );

		$checkResult = $this->checker->checkConstraint( $statement, array(), $this->entity );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testCheckSymmetricConstraintWithWrongSpouse() {
		$value = new EntityIdValue( new ItemId( 'Q2' ) );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) ) );

		$checkResult = $this->checker->checkConstraint( $statement, array(), $this->entity );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testCheckSymmetricConstraintWithWrongDataValue() {
		$value = new StringValue( 'Q3' );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) ) );

		$checkResult = $this->checker->checkConstraint( $statement, array(), $this->entity );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testCheckSymmetricConstraintWithNonExistentEntity() {
		$value = new EntityIdValue( new ItemId( 'Q100' ) );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) ) );

		$checkResult = $this->checker->checkConstraint( $statement, array(), $this->entity );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}
}