<?php

namespace WikibaseQuality\ConstraintReport\Test\ConnectionChecker;

use PHPUnit_Framework_TestCase;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Snak\PropertySomeValueSnak;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Statement\StatementList;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConnectionCheckerHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ItemIdSnakValue;

/**
 * @covers \WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConnectionCheckerHelper
 *
 * @group WikibaseQualityConstraints
 *
 * @uses   \WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 * @uses   \WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintStatementParameterParser
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class ConnectionCheckerHelperTest extends PHPUnit_Framework_TestCase {

	/**
	 * @var StatementList
	 */
	private $statementList;

	/**
	 * @var ConnectionCheckerHelper
	 */
	private $connectionCheckerHelper;

	protected function setUp() {
		parent::setUp();
		$this->statementList = new StatementList( [
			new Statement( new PropertyValueSnak( new PropertyId( 'P1' ), new EntityIdValue( new ItemId( 'Q1' ) ) ) ),
			new Statement( new PropertyValueSnak( new PropertyId( 'P2' ), new EntityIdValue( new ItemId( 'Q2' ) ) ) ),
			new Statement( new PropertySomeValueSnak( new PropertyId( 'P1' ) ) ),
			new Statement( new PropertyNoValueSnak( new PropertyId( 'P2' ) ) )
		] );
		$this->connectionCheckerHelper = new ConnectionCheckerHelper();
	}

	public function testHasPropertyValid() {
		$this->assertTrue( $this->connectionCheckerHelper->hasProperty( $this->statementList, 'P1' ) );
	}

	public function testHasPropertyInvalid() {
		$this->assertFalse( $this->connectionCheckerHelper->hasProperty( $this->statementList, 'P100' ) );
	}

	public function testHasClaimValid() {
		$this->assertNotNull( $this->connectionCheckerHelper->findStatement( $this->statementList, 'P1', 'Q1' ) );
	}

	public function testHasClaimWrongItem() {
		$this->assertNull( $this->connectionCheckerHelper->findStatement( $this->statementList, 'P1', 'Q100' ) );
	}

	public function testHasClaimWrongProperty() {
		$this->assertNull( $this->connectionCheckerHelper->findStatement( $this->statementList, 'P100', 'Q1' ) );
	}

	public function testHasClaimValidArray() {
		$this->assertNotNull( $this->connectionCheckerHelper->findStatement( $this->statementList, 'P1', [ 'Q1', 'Q2' ] ) );
	}

	public function testHasClaimNoValueSnak() {
		$statementList = new StatementList( new Statement( new PropertyNoValueSnak( 1 ) ) );
		$this->assertNull( $this->connectionCheckerHelper->findStatement( $statementList, 'P1', [ 'Q1', 'Q2' ] ) );
	}

	public function testHasClaimValidUnknownValue() {
		$this->assertNotNull( $this->connectionCheckerHelper->findStatement( $this->statementList, 'P1', 'somevalue' ) );
	}

	public function testHasClaimValidNoValue() {
		$this->assertNotNull( $this->connectionCheckerHelper->findStatement( $this->statementList, 'P2', 'novalue' ) );
	}

}
