<?php

namespace WikidataQuality\ConstraintReport\Test\ValueCountChecker;

use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Claim\Claim;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Entity\EntityIdValue;
use WikidataQuality\ConstraintReport\ConstraintCheck\Checker\UniqueValueChecker;
use WikidataQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper;
use WikidataQuality\Tests\Helper\JsonFileEntityLookup;


/**
 * @covers WikidataQuality\ConstraintReport\ConstraintCheck\Checker\UniqueValueChecker
 *
 * @uses   WikidataQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class UniqueValueCheckerTest extends \MediaWikiTestCase {

	private $helper;
	private $uniquePropertyId;
	private $checker;
	private $lookup;

	protected function setUp() {
		parent::setUp();

		$this->helper = new ConstraintReportHelper();
		$this->uniquePropertyId = new PropertyId( 'P227' );
		$this->checker = new UniqueValueChecker( $this->helper );
		$this->lookup = new JsonFileEntityLookup( __DIR__ );
	}

	protected function tearDown() {
		unset( $this->helper );
		unset( $this->uniquePropertyId );
		unset( $this->lookup );
		parent::tearDown();
	}

	// todo: it is currently only testing that 'todo' comes back
	public function testCheckUniqueValueConstraint() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
		$statement = new Statement( new Claim( new PropertyValueSnak( $this->uniquePropertyId, new EntityIdValue( new ItemId( 'Q404' ) ) ) ) );
		$checkResult = $this->checker->checkConstraint( $statement, array( 'statements' => $entity->getStatements() ) );
		$this->assertEquals( 'todo', $checkResult->getStatus(), 'check should point out that it should be implemented soon' );
	}

}