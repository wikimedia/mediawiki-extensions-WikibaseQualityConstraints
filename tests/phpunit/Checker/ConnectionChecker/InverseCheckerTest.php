<?php

namespace WikidataQuality\ConstraintReport\Test\ConnectionChecker;

use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Claim\Claim;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use DataValues\StringValue;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use WikidataQuality\ConstraintReport\ConstraintCheck\Checker\InverseChecker;
use WikidataQuality\ConstraintReport\ConstraintCheck\Helper\ConnectionCheckerHelper;
use WikidataQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper;
use WikidataQuality\Tests\Helper\JsonFileEntityLookup;


/**
 * @covers WikidataQuality\ConstraintReport\ConstraintCheck\Checker\InverseChecker
 *
 * @uses   WikidataQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 * @uses   WikidataQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class InverseCheckerTest extends \MediaWikiTestCase {

	private $lookup;
	private $helper;
	private $connectionCheckerHelper;
	private $checker;

	protected function setUp() {
		parent::setUp();
		$this->lookup = new JsonFileEntityLookup( __DIR__ );
		$this->helper = new ConstraintReportHelper();
		$this->connectionCheckerHelper = new ConnectionCheckerHelper();
		$this->checker = new InverseChecker( $this->lookup, $this->helper, $this->connectionCheckerHelper );
	}

	protected function tearDown() {
		unset( $this->lookup );
		unset( $this->helper );
		unset( $this->connectionCheckerHelper );
		unset( $this->checker );
		parent::tearDown();
	}

	public function testInverseConstraintValid() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
		
		$value = new EntityIdValue( new ItemId( 'Q7' ) );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) ) );

		$constraintParameters = array(
			'entity' => 'Q1',
			'statements' => $entity->getStatements(),
			'property' => array( 'P1' )
		);
		$checkResult = $this->checker->checkConstraint( $statement, $constraintParameters, $entity );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testInverseConstraintWrongItem() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );

		$value = new EntityIdValue( new ItemId( 'Q8' ) );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) ) );

		$constraintParameters = array(
			'entity' => 'Q1',
			'statements' => $entity->getStatements(),
			'property' => array( 'P1' )
		);
		$checkResult = $this->checker->checkConstraint( $statement, $constraintParameters, $entity );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testInverseConstraintWithoutProperty() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );

		$value = new EntityIdValue( new ItemId( 'Q7' ) );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) ) );

		$constraintParameters = array(
			'entity' => 'Q1',
			'statements' => $entity->getStatements(),
			'property' => array( '' )
		);
		$checkResult = $this->checker->checkConstraint( $statement, $constraintParameters, $entity );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testInverseConstraintWrongDataTypeForItem() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );

		$value = new StringValue( 'Q7' );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) ) );

		$constraintParameters = array(
			'entity' => 'Q1',
			'statements' => $entity->getStatements(),
			'property' => array( 'P1' )
		);
		$checkResult = $this->checker->checkConstraint( $statement, $constraintParameters, $entity );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testInverseConstraintItemDoesNotExist() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );

		$value = new EntityIdValue( new ItemId( 'Q100' ) );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) ) );

		$constraintParameters = array(
			'entity' => 'Q1',
			'statements' => $entity->getStatements(),
			'property' => array( 'P1' )
		);
		$checkResult = $this->checker->checkConstraint( $statement, $constraintParameters, $entity );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

}