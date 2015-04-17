<?php

namespace WikidataQuality\ConstraintReport\Test\ConnectionChecker;

use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Claim\Claim;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use DataValues\StringValue;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use WikidataQuality\ConstraintReport\ConstraintCheck\Checker\ConnectionChecker;
use WikidataQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper;
use WikidataQuality\Tests\Helper\JsonFileEntityLookup;


/**
 * @covers WikidataQuality\ConstraintReport\ConstraintCheck\Checker\ConnectionChecker
 *
 * @uses WikidataQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 * @uses WikidataQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class ConnectionCheckerTest extends \MediaWikiTestCase {

	private $lookup;
	private $helper;

	protected function setUp() {
		parent::setUp();
		$this->lookup = new JsonFileEntityLookup( __DIR__ );
		$this->helper = new ConstraintReportHelper();
	}

	protected function tearDown() {
		unset( $this->lookup );
		unset( $this->helper );
		parent::tearDown();
	}

	/**
	 * Following tests are testing the 'Symmetric' constraint.
	 */

	public function testCheckSymmetricConstraintWithCorrectSpouse() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
		$connectionChecker = new ConnectionChecker( $entity->getStatements(), $this->lookup, $this->helper );

		$value = new EntityIdValue( new ItemId( 'Q3' ) );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) ) );

		$checkResult = $connectionChecker->checkSymmetricConstraint( $statement, 'Q1' );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testCheckSymmetricConstraintWithWrongSpouse() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
		$connectionChecker = new ConnectionChecker( $entity->getStatements(), $this->lookup, $this->helper );

		$value = new EntityIdValue( new ItemId( 'Q2' ) );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) ) );

		$checkResult = $connectionChecker->checkSymmetricConstraint( $statement, 'Q1' );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testCheckSymmetricConstraintWithWrongDataValue() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
		$connectionChecker = new ConnectionChecker( $entity->getStatements(), $this->lookup, $this->helper );

		$value = new StringValue( 'Q3' );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) ) );

		$checkResult = $connectionChecker->checkSymmetricConstraint( $statement, 'Q1' );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testCheckSymmetricConstraintWithNonExistentEntity() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
		$connectionChecker = new ConnectionChecker( $entity->getStatements(), $this->lookup, $this->helper );

		$value = new EntityIdValue( new ItemId( 'Q100' ) );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) ) );

		$checkResult = $connectionChecker->checkSymmetricConstraint( $statement, 'Q1' );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	/*
	 * Following tests are testing the 'Conflicts with' constraint.
	 */

	public function testConflictsWithConstraintValid() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q4' ) );
		$connectionChecker = new ConnectionChecker( $entity->getStatements(), $this->lookup, $this->helper );

		$value = new EntityIdValue( new ItemId( 'Q100' ) );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) ) );

		$checkResult = $connectionChecker->checkConflictsWithConstraint( $statement, 'P2', array( '' ) );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testConflictsWithConstraintProperty() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q5' ) );
		$connectionChecker = new ConnectionChecker( $entity->getStatements(), $this->lookup, $this->helper );

		$value = new EntityIdValue( new ItemId( 'Q100' ) );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) ) );

		$checkResult = $connectionChecker->checkConflictsWithConstraint( $statement, 'P2', array( '' ) );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testConflictsWithConstraintPropertyButNotItem() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q5' ) );
		$connectionChecker = new ConnectionChecker( $entity->getStatements(), $this->lookup, $this->helper );

		$value = new EntityIdValue( new ItemId( 'Q100' ) );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) ) );

		$checkResult = $connectionChecker->checkConflictsWithConstraint( $statement, 'P2', array( 'Q1' ) );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testConflictsWithConstraintPropertyAndItem() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q5' ) );
		$connectionChecker = new ConnectionChecker( $entity->getStatements(), $this->lookup, $this->helper );

		$value = new EntityIdValue( new ItemId( 'Q100' ) );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) ) );

		$checkResult = $connectionChecker->checkConflictsWithConstraint( $statement, 'P2', array( 'Q42' ) );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testConflictsWithConstraintWithoutProperty() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q4' ) );
		$connectionChecker = new ConnectionChecker( $entity->getStatements(), $this->lookup, $this->helper );

		$value = new EntityIdValue( new ItemId( 'Q100' ) );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) ) );

		$checkResult = $connectionChecker->checkConflictsWithConstraint( $statement, null, array( '' ) );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testConflictsWithConstraintPropertyAndNoValue() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q6' ) );
		$connectionChecker = new ConnectionChecker( $entity->getStatements(), $this->lookup, $this->helper );

		$value = new EntityIdValue( new ItemId( 'Q100' ) );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) ) );

		$checkResult = $connectionChecker->checkConflictsWithConstraint( $statement, 'P2', array( 'Q42' ) );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	/*
	 * Following tests are testing the item constraint.
	 */

	public function testItemConstraintValid() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q4' ) );
		$connectionChecker = new ConnectionChecker( $entity->getStatements(), $this->lookup, $this->helper );

		$value = new EntityIdValue( new ItemId( 'Q100' ) );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) ) );

		$checkResult = $connectionChecker->checkItemConstraint( $statement, 'P2', array( '' ) );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testItemConstraintProperty() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q5' ) );
		$connectionChecker = new ConnectionChecker( $entity->getStatements(), $this->lookup, $this->helper );

		$value = new EntityIdValue( new ItemId( 'Q100' ) );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) ) );

		$checkResult = $connectionChecker->checkItemConstraint( $statement, 'P2', array( '' ) );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testItemConstraintPropertyButNotItem() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q5' ) );
		$connectionChecker = new ConnectionChecker( $entity->getStatements(), $this->lookup, $this->helper );

		$value = new EntityIdValue( new ItemId( 'Q100' ) );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) ) );

		$checkResult = $connectionChecker->checkItemConstraint( $statement, 'P2', array( 'Q1' ) );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testItemConstraintPropertyAndItem() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q5' ) );
		$connectionChecker = new ConnectionChecker( $entity->getStatements(), $this->lookup, $this->helper );

		$value = new EntityIdValue( new ItemId( 'Q100' ) );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) ) );

		$checkResult = $connectionChecker->checkItemConstraint( $statement, 'P2', array( 'Q42' ) );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testItemConstraintWithoutProperty() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q4' ) );
		$connectionChecker = new ConnectionChecker( $entity->getStatements(), $this->lookup, $this->helper );

		$value = new EntityIdValue( new ItemId( 'Q100' ) );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) ) );

		$checkResult = $connectionChecker->checkItemConstraint( $statement, null, array( '' ) );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	/*
	 * Following tests are testing the 'Target required claim' constraint.
	 */

	public function testTargetRequiredClaimConstraintValid() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
		$connectionChecker = new ConnectionChecker( $entity->getStatements(), $this->lookup, $this->helper );

		$value = new EntityIdValue( new ItemId( 'Q5' ) );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) ) );

		$checkResult = $connectionChecker->checkTargetRequiredClaimConstraint( $statement, 'P2', array( 'Q42' ) );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testTargetRequiredClaimConstraintWrongItem() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
		$connectionChecker = new ConnectionChecker( $entity->getStatements(), $this->lookup, $this->helper );

		$value = new EntityIdValue( new ItemId( 'Q5' ) );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) ) );

		$checkResult = $connectionChecker->checkTargetRequiredClaimConstraint( $statement, 'P2', array( 'Q2' ) );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testTargetRequiredClaimConstraintOnlyProperty() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
		$connectionChecker = new ConnectionChecker( $entity->getStatements(), $this->lookup, $this->helper );

		$value = new EntityIdValue( new ItemId( 'Q5' ) );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) ) );

		$checkResult = $connectionChecker->checkTargetRequiredClaimConstraint( $statement, 'P2', array( '' ) );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testTargetRequiredClaimConstraintOnlyPropertyButDoesNotExist() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
		$connectionChecker = new ConnectionChecker( $entity->getStatements(), $this->lookup, $this->helper );

		$value = new EntityIdValue( new ItemId( 'Q5' ) );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) ) );

		$checkResult = $connectionChecker->checkTargetRequiredClaimConstraint( $statement, 'P3', array( '' ) );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testTargetRequiredClaimConstraintWithoutProperty() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
		$connectionChecker = new ConnectionChecker( $entity->getStatements(), $this->lookup, $this->helper );

		$value = new EntityIdValue( new ItemId( 'Q5' ) );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) ) );

		$checkResult = $connectionChecker->checkTargetRequiredClaimConstraint( $statement, null, array( '' ) );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testTargetRequiredClaimConstraintWrongDataTypeForItem() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
		$connectionChecker = new ConnectionChecker( $entity->getStatements(), $this->lookup, $this->helper );

		$value = new StringValue( 'Q5' );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) ) );

		$checkResult = $connectionChecker->checkTargetRequiredClaimConstraint( $statement, 'P2', array( '' ) );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testTargetRequiredClaimConstraintItemDoesNotExist() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
		$connectionChecker = new ConnectionChecker( $entity->getStatements(), $this->lookup, $this->helper );

		$value = new EntityIdValue( new ItemId( 'Q100' ) );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) ) );

		$checkResult = $connectionChecker->checkTargetRequiredClaimConstraint( $statement, 'P2', array( '' ) );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	/*
	 * Following tests are testing the 'Inverse' constraint.
	 */

	public function testInverseConstraintValid() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
		$connectionChecker = new ConnectionChecker( $entity->getStatements(), $this->lookup, $this->helper );

		$value = new EntityIdValue( new ItemId( 'Q7' ) );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) ) );

		$checkResult = $connectionChecker->checkInverseConstraint( $statement, 'Q1', 'P1' );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testInverseConstraintWrongItem() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
		$connectionChecker = new ConnectionChecker( $entity->getStatements(), $this->lookup, $this->helper );

		$value = new EntityIdValue( new ItemId( 'Q8' ) );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) ) );

		$checkResult = $connectionChecker->checkInverseConstraint( $statement, 'Q1', 'P1' );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testInverseConstraintWithoutProperty() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
		$connectionChecker = new ConnectionChecker( $entity->getStatements(), $this->lookup, $this->helper );

		$value = new EntityIdValue( new ItemId( 'Q7' ) );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) ) );

		$checkResult = $connectionChecker->checkInverseConstraint( $statement, 'Q1', null );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testInverseConstraintWrongDataTypeForItem() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
		$connectionChecker = new ConnectionChecker( $entity->getStatements(), $this->lookup, $this->helper );

		$value = new StringValue( 'Q7' );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) ) );

		$checkResult = $connectionChecker->checkInverseConstraint( $statement, 'Q1', 'P1' );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testInverseConstraintItemDoesNotExist() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
		$connectionChecker = new ConnectionChecker( $entity->getStatements(), $this->lookup, $this->helper );

		$value = new EntityIdValue( new ItemId( 'Q100' ) );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) ) );

		$checkResult = $connectionChecker->checkInverseConstraint( $statement, 'Q1', 'P1' );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

}