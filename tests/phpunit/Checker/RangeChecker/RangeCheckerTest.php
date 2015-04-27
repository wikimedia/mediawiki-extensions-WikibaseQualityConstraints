<?php

namespace WikidataQuality\ConstraintReport\Test\RangeChecker;

use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Claim\Claim;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use DataValues\DecimalValue;
use DataValues\QuantityValue;
use DataValues\StringValue;
use DataValues\TimeValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use WikidataQuality\ConstraintReport\ConstraintCheck\Checker\RangeChecker;
use WikidataQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper;
use WikidataQuality\Tests\Helper\JsonFileEntityLookup;


/**
 * @covers WikidataQuality\ConstraintReport\ConstraintCheck\Checker\RangeChecker
 *
 * @uses   WikidataQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 * @uses   WikidataQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper
 *
 * @group WikidataQualityConstraints
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class RangeCheckerTest extends \MediaWikiTestCase {

	private $helper;
	private $lookup;
	private $timeValue;

	protected function setUp() {
		parent::setUp();
		$this->helper = new ConstraintReportHelper();
		$this->lookup = new JsonFileEntityLookup( __DIR__ );
		$this->timeValue = new TimeValue( '+00000001970-01-01T00:00:00Z', 0, 0, 0, 11, 'http://www.wikidata.org/entity/Q1985727' );
	}

	protected function tearDown() {
		unset( $this->helper );
		unset( $this->lookup );
		unset( $this->timeValue );
		parent::tearDown();
	}

	/*
	 * Following tests are testing the 'Range' constraint.
	 */

	public function testCheckRangeConstraintWithinRange() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
		$rangeChecker = new RangeChecker( $entity->getStatements(), $this->helper );

		$value = new DecimalValue( 3.1415926536 );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P1457' ), new QuantityValue( $value, '1', $value, $value ) ) ) );

		$checkResult = $rangeChecker->checkRangeConstraint( $statement, 0, 10, null, null );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testCheckRangeConstraintTooSmall() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q2' ) );
		$this->rangeChecker = new RangeChecker( $entity->getStatements(), $this->helper );

		$value = new DecimalValue( 42 );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P1457' ), new QuantityValue( $value, '1', $value, $value ) ) ) );

		$checkResult = $this->rangeChecker->checkRangeConstraint( $statement, 100, 1000, null, null );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testCheckRangeConstraintTooBig() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q3' ) );
		$this->rangeChecker = new RangeChecker( $entity->getStatements(), $this->helper );

		$value = new DecimalValue( 3.141592 );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P1457' ), new QuantityValue( $value, '1', $value, $value ) ) ) );

		$checkResult = $this->rangeChecker->checkRangeConstraint( $statement, 0, 1, null, null );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testCheckRangeConstraintTimeWithinRange() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
		$rangeChecker = new RangeChecker( $entity->getStatements(), $this->helper );

		$min = new TimeValue( '+00000001960-01-01T00:00:00Z', 0, 0, 0, 11, 'http://www.wikidata.org/entity/Q1985727' );
		$max = new TimeValue( '+00000001980-01-01T00:00:00Z', 0, 0, 0, 11, 'http://www.wikidata.org/entity/Q1985727' );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P1457' ), $this->timeValue ) ) );

		$checkResult = $rangeChecker->checkRangeConstraint( $statement, null, null, $min, $max );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testCheckRangeConstraintTimeTooSmall() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
		$rangeChecker = new RangeChecker( $entity->getStatements(), $this->helper );

		$min = new TimeValue( '+00000001975-01-01T00:00:00Z', 0, 0, 0, 11, 'http://www.wikidata.org/entity/Q1985727' );
		$max = new TimeValue( '+00000001980-01-01T00:00:00Z', 0, 0, 0, 11, 'http://www.wikidata.org/entity/Q1985727' );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P1457' ), $this->timeValue ) ) );

		$checkResult = $rangeChecker->checkRangeConstraint( $statement, null, null, $min, $max );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testCheckRangeConstraintTimeTooBig() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
		$rangeChecker = new RangeChecker( $entity->getStatements(), $this->helper );

		$min = new TimeValue( '+00000001960-01-01T00:00:00Z', 0, 0, 0, 11, 'http://www.wikidata.org/entity/Q1985727' );
		$max = new TimeValue( '+00000001965-01-01T00:00:00Z', 0, 0, 0, 11, 'http://www.wikidata.org/entity/Q1985727' );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P1457' ), $this->timeValue ) ) );

		$checkResult = $rangeChecker->checkRangeConstraint( $statement, null, null, $min, $max );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testCheckRangeConstraintQuantityWrongParameter() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
		$rangeChecker = new RangeChecker( $entity->getStatements(), $this->helper );

		$min = new TimeValue( '+00000001970-01-01T00:00:00Z', 0, 0, 0, 11, 'http://www.wikidata.org/entity/Q1985727' );
		$value = $max = new DecimalValue( 42 );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P1457' ), new QuantityValue( $value, '1', $value, $value ) ) ) );

		$checkResult = $rangeChecker->checkRangeConstraint( $statement, $min, null, null, $max );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testCheckRangeConstraintTimeWrongParameter() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
		$rangeChecker = new RangeChecker( $entity->getStatements(), $this->helper );

		$min = new TimeValue( '+00000001970-01-01T00:00:00Z', 0, 0, 0, 11, 'http://www.wikidata.org/entity/Q1985727' );
		$max = new DecimalValue( 42 );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P1457' ), $this->timeValue ) ) );

		$checkResult = $rangeChecker->checkRangeConstraint( $statement, $min, null, null, $max );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testCheckRangeConstraintWrongType() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
		$rangeChecker = new RangeChecker( $entity->getStatements(), $this->helper );

		$min = new TimeValue( '+00000001960-01-01T00:00:00Z', 0, 0, 0, 11, 'http://www.wikidata.org/entity/Q1985727' );
		$max = new TimeValue( '+00000001965-01-01T00:00:00Z', 0, 0, 0, 11, 'http://www.wikidata.org/entity/Q1985727' );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P1457' ), new StringValue( '1.1.1970' ) ) ) );

		$checkResult = $rangeChecker->checkRangeConstraint( $statement, null, null, $min, $max );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	/*
	 * Following tests are testing the 'Diff within range' constraint.
	 */

	public function testCheckDiffWithinRangeConstraintWithinRange() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q4' ) );
		$this->rangeChecker = new RangeChecker( $entity->getStatements(), $this->helper );

		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P570' ), $this->timeValue ) ) );

		$checkResult = $this->rangeChecker->checkDiffWithinRangeConstraint( $statement, 'P569', 0, 150 );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testCheckDiffWithinRangeConstraintTooSmall() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q5' ) );
		$this->rangeChecker = new RangeChecker( $entity->getStatements(), $this->helper );

		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P570' ), $this->timeValue ) ) );

		$checkResult = $this->rangeChecker->checkDiffWithinRangeConstraint( $statement, 'P569', 50, 150 );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testCheckDiffWithinRangeConstraintTooBig() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q6' ) );
		$this->rangeChecker = new RangeChecker( $entity->getStatements(), $this->helper );

		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P570' ), $this->timeValue ) ) );

		$checkResult = $this->rangeChecker->checkDiffWithinRangeConstraint( $statement, 'P569', 0, 150 );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testCheckDiffWithinRangeConstraintWithoutProperty() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
		$rangeChecker = new RangeChecker( $entity->getStatements(), $this->helper );

		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P1457' ), $this->timeValue ) ) );

		$checkResult = $rangeChecker->checkDiffWithinRangeConstraint( $statement, null, null, null );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testCheckDiffWithinRangeConstraintWrongType() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
		$rangeChecker = new RangeChecker( $entity->getStatements(), $this->helper );

		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P1457' ), new StringValue( '1.1.1970' ) ) ) );

		$checkResult = $rangeChecker->checkDiffWithinRangeConstraint( $statement, 'P1', null, null );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testCheckDiffWithinRangeConstraintWrongTypeOfProperty() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q4' ) );
		$this->rangeChecker = new RangeChecker( $entity->getStatements(), $this->helper );

		$value = new DecimalValue( 42 );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P570' ), new QuantityValue( $value, '1', $value, $value ) ) ) );

		$checkResult = $this->rangeChecker->checkDiffWithinRangeConstraint( $statement, 'P569', 0, 150 );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testCheckDiffWithinRangeConstraintWithoutBaseProperty() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q4' ) );
		$this->rangeChecker = new RangeChecker( $entity->getStatements(), $this->helper );

		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P570' ), $this->timeValue ) ) );

		$checkResult = $this->rangeChecker->checkDiffWithinRangeConstraint( $statement, 'P1000', 0, 150 );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

}