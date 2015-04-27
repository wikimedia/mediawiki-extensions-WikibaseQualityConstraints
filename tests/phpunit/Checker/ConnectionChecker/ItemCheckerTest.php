<?php

namespace WikidataQuality\ConstraintReport\Test\ConnectionChecker;

use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Claim\Claim;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use DataValues\StringValue;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use WikidataQuality\ConstraintReport\ConstraintCheck\Checker\ItemChecker;
use WikidataQuality\ConstraintReport\ConstraintCheck\Helper\ConnectionCheckerHelper;
use WikidataQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper;
use WikidataQuality\Tests\Helper\JsonFileEntityLookup;


/**
 * @covers WikidataQuality\ConstraintReport\ConstraintCheck\Checker\ItemChecker
 *
 * @uses   WikidataQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 * @uses   WikidataQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class ItemCheckerTest extends \MediaWikiTestCase {

	private $lookup;
	private $helper;
	private $connectionCheckerHelper;
	private $checker;

	protected function setUp() {
		parent::setUp();
		$this->lookup = new JsonFileEntityLookup( __DIR__ );
		$this->helper = new ConstraintReportHelper();
		$this->connectionCheckerHelper = new ConnectionCheckerHelper();
		$this->checker = new ItemChecker( $this->lookup, $this->helper, $this->connectionCheckerHelper );
	}

	protected function tearDown() {
		unset( $this->lookup );
		unset( $this->helper );
		unset( $this->connectionCheckerHelper );
		unset( $this->checker );
		parent::tearDown();
	}

	public function testItemConstraintInvalid() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q4' ) );
		$constraintParameters = array(
			'statements' => $entity->getStatements(),
			'property' => array( 'P2' ),
			'item' => array( '' )
		);
		$value = new EntityIdValue( new ItemId( 'Q100' ) );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) ) );

		$checkResult = $this->checker->checkConstraint( $statement, $constraintParameters, $entity );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testItemConstraintProperty() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q5' ) );
		$constraintParameters = array(
			'statements' => $entity->getStatements(),
			'property' => array( 'P2' ),
			'item' => array( '' )
		);

		$value = new EntityIdValue( new ItemId( 'Q100' ) );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) ) );

		$checkResult = $this->checker->checkConstraint( $statement, $constraintParameters, $entity );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testItemConstraintPropertyButNotItem() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q5' ) );
		$constraintParameters = array(
			'statements' => $entity->getStatements(),
			'property' => array( 'P2' ),
			'item' => array( 'Q1' )
		);
		
		$value = new EntityIdValue( new ItemId( 'Q100' ) );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) ) );

		$checkResult = $this->checker->checkConstraint( $statement, $constraintParameters, $entity );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testItemConstraintPropertyAndItem() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q5' ) );
		$constraintParameters = array(
			'statements' => $entity->getStatements(),
			'property' => array( 'P2' ),
			'item' => array( 'Q42' )
		);
		$value = new EntityIdValue( new ItemId( 'Q100' ) );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) ) );

		$checkResult = $this->checker->checkConstraint( $statement, $constraintParameters, $entity );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testItemConstraintWithoutProperty() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q4' ) );
		$constraintParameters = array(
			'statements' => $entity->getStatements(),
			'property' => array( '' ),
			'item' => array( '' )
		);
		
		$value = new EntityIdValue( new ItemId( 'Q100' ) );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) ) );

		$checkResult = $this->checker->checkConstraint( $statement, $constraintParameters, $entity );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}
}