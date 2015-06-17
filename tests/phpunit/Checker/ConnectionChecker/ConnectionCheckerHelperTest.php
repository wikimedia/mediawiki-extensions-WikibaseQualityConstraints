<?php

namespace WikibaseQuality\ConstraintReport\Test\ConnectionChecker;

use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Statement\StatementList;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConnectionCheckerHelper;


/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConnectionCheckerHelper
 *
 * @group WikibaseQualityConstraints
 *
 * @uses   WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 * @uses   WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class ConnectionCheckerHelperTest extends \MediaWikiTestCase {

	private $statementList;
	private $connectionCheckerHelper;

	protected function setUp() {
		parent::setUp();
		$statement1 = new Statement( new PropertyValueSnak( new PropertyId( 'P1' ), new EntityIdValue( new ItemId( 'Q1' ) ) ) );
		$statement2 = new Statement( new PropertyValueSnak( new PropertyId( 'P2' ), new EntityIdValue( new ItemId( 'Q2' ) ) ) );
		$this->statementList = new StatementList( array( $statement1, $statement2 ) );
		$this->connectionCheckerHelper = new ConnectionCheckerHelper();
	}

	protected function tearDown() {
		unset( $this->statementList );
		parent::tearDown();
	}

	public function testHasPropertyValid() {
		$this->assertEquals( true, $this->connectionCheckerHelper->hasProperty( $this->statementList, 'P1' ) );
	}

	public function testHasPropertyInvalid() {
		$this->assertEquals( false, $this->connectionCheckerHelper->hasProperty( $this->statementList, 'P100' ) );
	}

	public function testHasClaimValid() {
		$this->assertEquals( true, $this->connectionCheckerHelper->hasClaim( $this->statementList, 'P1', 'Q1' ) );
	}

	public function testHasClaimWrongItem() {
		$this->assertEquals( false, $this->connectionCheckerHelper->hasClaim( $this->statementList, 'P1', 'Q100' ) );
	}

	public function testHasClaimWrongProperty() {
		$this->assertEquals( false, $this->connectionCheckerHelper->hasClaim( $this->statementList, 'P100', 'Q1' ) );
	}

	public function testHasClaimValidArray() {
		$this->assertEquals( true, $this->connectionCheckerHelper->hasClaim( $this->statementList, 'P1', array( 'Q1', 'Q2' ) ) );
	}

	public function testHasClaimNoValueSnak() {
		$statementList = new StatementList( new Statement( new PropertyNoValueSnak( 1 ) ) );
		$this->assertEquals( false, $this->connectionCheckerHelper->hasClaim( $statementList, 'P1', array( 'Q1', 'Q2' ) ) );
	}
}