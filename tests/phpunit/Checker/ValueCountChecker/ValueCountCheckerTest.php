<?php

namespace WikidataQuality\ConstraintReport\Test\ValueCountChecker;

use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Claim\Claim;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Entity\EntityIdValue;
use WikidataQuality\ConstraintReport\ConstraintCheck\Checker\ValueCountChecker;
use WikidataQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper;
use WikidataQuality\Tests\Helper\JsonFileEntityLookup;


/**
 * @covers WikidataQuality\ConstraintReport\ConstraintCheck\Checker\ValueCountChecker
 *
 * @uses   WikidataQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 *
 * @group WikidataQualityConstraints
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class ValueCountCheckerTest extends \MediaWikiTestCase {

	private $helper;
	private $singlePropertyId;
	private $multiPropertyId;
	private $uniquePropertyId;
	private $lookup;

	protected function setUp() {
		parent::setUp();

		$this->helper = new ConstraintReportHelper();
		$this->singlePropertyId = new PropertyId( 'P36' );
		$this->multiPropertyId = new PropertyId( 'P161' );
		$this->uniquePropertyId = new PropertyId( 'P227' );
		$this->lookup = new JsonFileEntityLookup( __DIR__ );
	}

	protected function tearDown() {
		unset( $this->helper );
		unset( $this->propertyId );
		unset( $this->lookup );
		parent::tearDown();
	}

	public function testCheckSingleValueConstraintOne() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
		$valueCountChecker = new ValueCountChecker( $entity->getStatements(), $this->helper );
		$statement = new Statement( new Claim( new PropertyValueSnak( $this->singlePropertyId, new EntityIdValue( new ItemId( 'Q1384' ) ) ) ) );
		$checkResult = $valueCountChecker->checkSingleValueConstraint( $statement );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testCheckSingleValueConstraintTwo() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q2' ) );
		$valueCountChecker = new ValueCountChecker( $entity->getStatements(), $this->helper );
		$statement = new Statement( new Claim( new PropertyValueSnak( $this->singlePropertyId, new EntityIdValue( new ItemId( 'Q1384' ) ) ) ) );
		$checkResult = $valueCountChecker->checkSingleValueConstraint( $statement );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testCheckSingleValueConstraintTwoButOneDeprecated() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q3' ) );
		$valueCountChecker = new ValueCountChecker( $entity->getStatements(), $this->helper );
		$statement = new Statement( new Claim( new PropertyValueSnak( $this->singlePropertyId, new EntityIdValue( new ItemId( 'Q1384' ) ) ) ) );
		$checkResult = $valueCountChecker->checkSingleValueConstraint( $statement );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testCheckMultiValueConstraintOne() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q4' ) );
		$valueCountChecker = new ValueCountChecker( $entity->getStatements(), $this->helper );
		$statement = new Statement( new Claim( new PropertyValueSnak( $this->multiPropertyId, new EntityIdValue( new ItemId( 'Q207' ) ) ) ) );
		$checkResult = $valueCountChecker->checkMultiValueConstraint( $statement );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testCheckMultiValueConstraintTwo() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q5' ) );
		$valueCountChecker = new ValueCountChecker( $entity->getStatements(), $this->helper );
		$statement = new Statement( new Claim( new PropertyValueSnak( $this->multiPropertyId, new EntityIdValue( new ItemId( 'Q207' ) ) ) ) );
		$checkResult = $valueCountChecker->checkMultiValueConstraint( $statement );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testCheckMultiValueConstraintTwoButOneDeprecated() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q6' ) );
		$valueCountChecker = new ValueCountChecker( $entity->getStatements(), $this->helper );
		$statement = new Statement( new Claim( new PropertyValueSnak( $this->multiPropertyId, new EntityIdValue( new ItemId( 'Q409' ) ) ) ) );
		$checkResult = $valueCountChecker->checkMultiValueConstraint( $statement );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	// todo: it is currently only testing that 'todo' comes back
	public function testCheckUniqueValueConstraint() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
		$valueCountChecker = new ValueCountChecker( $entity->getStatements(), $this->helper );
		$statement = new Statement( new Claim( new PropertyValueSnak( $this->uniquePropertyId, new EntityIdValue( new ItemId( 'Q404' ) ) ) ) );
		$checkResult = $valueCountChecker->checkUniqueValueConstraint( $statement );
		$this->assertEquals( 'todo', $checkResult->getStatus(), 'check should point out that it should be implemented soon' );
	}

}