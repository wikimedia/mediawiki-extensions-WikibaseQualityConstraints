<?php
namespace WikibaseQuality\ConstraintReport\Test\TypeChecker;

use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Claim\Claim;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\TypeChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\TypeCheckerHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikibaseQuality\Tests\Helper\JsonFileEntityLookup;


/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\TypeCheckerHelper
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class TypeCheckerHelperTest extends \MediaWikiTestCase {

	private $helper;

	protected function setUp() {
		parent::setUp();
		$this->helper = new TypeCheckerHelper( new JsonFileEntityLookup( __DIR__ ) );
	}

	protected function tearDown() {
		unset( $this->helper );
		parent::tearDown();
	}


	public function testCheckHasClassInRelationValid() {
		$statement1 = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P1' ), new EntityIdValue( new ItemId( 'Q42' ) ) ) ) );
		$statement2 = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P31' ), new EntityIdValue( new ItemId( 'Q1' ) ) ) ) );
		$statements = array( $statement1, $statement2 );
		$this->assertEquals( true, $this->helper->hasClassInRelation( $statements, 31, array( 'Q1' ) ) );
	}

	public function testCheckHasClassInRelationInvalid() {
		$statement1 = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P1' ), new EntityIdValue( new ItemId( 'Q42' ) ) ) ) );
		$statement2 = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P31' ), new EntityIdValue( new ItemId( 'Q100' ) ) ) ) );
		$statements = array( $statement1, $statement2 );
		$this->assertEquals( false, $this->helper->hasClassInRelation( $statements, 31, array( 'Q1' ) ) );
	}

	public function testCheckHasClassInRelationValidWithIndirection() {
		$statement1 = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P1' ), new EntityIdValue( new ItemId( 'Q42' ) ) ) ) );
		$statement2 = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P31' ), new EntityIdValue( new ItemId( 'Q5' ) ) ) ) );
		$statements = array( $statement1, $statement2 );
		$this->assertEquals( true, $this->helper->hasClassInRelation( $statements, 31, array( 'Q4' ) ) );
	}

	public function testCheckIsSubclassOfValidWithIndirection() {
		$this->assertEquals( true, $this->helper->isSubclassOf( new ItemId( 'Q6' ), array( 'Q100', 'Q101' ), 1) );
	}

	public function testCheckIsSubclassOfInvalid() {
		$this->assertEquals( false, $this->helper->isSubclassOf( new ItemId( 'Q6' ), array( 'Q200', 'Q201' ), 1) );
	}

	public function testCheckIsSubclassCyclic() {
		$this->assertEquals( false, $this->helper->isSubclassOf( new ItemId( 'Q7' ), array( 'Q100', 'Q101' ), 1) );
	}
}