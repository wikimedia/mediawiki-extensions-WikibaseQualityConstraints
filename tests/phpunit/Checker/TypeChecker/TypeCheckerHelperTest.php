<?php

namespace WikibaseQuality\ConstraintReport\Test\TypeChecker;

use PHPUnit_Framework_TestCase;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Statement\StatementList;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\TypeCheckerHelper;
use WikibaseQuality\ConstraintReport\Tests\DefaultConfig;
use WikibaseQuality\Tests\Helper\JsonFileEntityLookup;

/**
 * @covers \WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\TypeCheckerHelper
 *
 * @group WikibaseQualityConstraints
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class TypeCheckerHelperTest extends PHPUnit_Framework_TestCase {

	use DefaultConfig;

	/**
	 * @var TypeCheckerHelper
	 */
	private $helper;

	protected function setUp() {
		parent::setUp();
		$this->helper = new TypeCheckerHelper(
			new JsonFileEntityLookup( __DIR__ ),
			$this->getDefaultConfig()
		);
	}

	protected function tearDown() {
		unset( $this->helper );
		parent::tearDown();
	}

	public function testCheckHasClassInRelationValid() {
		$statement1 = new Statement( new PropertyValueSnak( new PropertyId( 'P1' ), new EntityIdValue( new ItemId( 'Q42' ) ) ) );
		$statement2 = new Statement( new PropertyValueSnak( new PropertyId( 'P31' ), new EntityIdValue( new ItemId( 'Q1' ) ) ) );
		$statements = new StatementList( [ $statement1, $statement2 ] );
		$this->assertEquals( true, $this->helper->hasClassInRelation( $statements, 'P31', [ 'Q1' ] ) );
	}

	public function testCheckHasClassInRelationInvalid() {
		$statement1 = new Statement( new PropertyValueSnak( new PropertyId( 'P1' ), new EntityIdValue( new ItemId( 'Q42' ) ) ) );
		$statement2 = new Statement( new PropertyValueSnak( new PropertyId( 'P31' ), new EntityIdValue( new ItemId( 'Q100' ) ) ) );
		$statements = new StatementList( [ $statement1, $statement2 ] );
		$this->assertEquals( false, $this->helper->hasClassInRelation( $statements, 'P31', [ 'Q1' ] ) );
	}

	public function testCheckHasClassInRelationValidWithIndirection() {
		$statement1 = new Statement( new PropertyValueSnak( new PropertyId( 'P1' ), new EntityIdValue( new ItemId( 'Q42' ) ) ) );
		$statement2 = new Statement( new PropertyValueSnak( new PropertyId( 'P31' ), new EntityIdValue( new ItemId( 'Q5' ) ) ) );
		$statements = new StatementList( [ $statement1, $statement2 ] );
		$this->assertEquals( true, $this->helper->hasClassInRelation( $statements, 'P31', [ 'Q4' ] ) );
	}

	public function testCheckIsSubclassOfValidWithIndirection() {
		$this->assertEquals( true, $this->helper->isSubclassOf( new ItemId( 'Q6' ), [ 'Q100', 'Q101' ] ) );
	}

	public function testCheckIsSubclassOfInvalid() {
		$this->assertEquals( false, $this->helper->isSubclassOf( new ItemId( 'Q6' ), [ 'Q200', 'Q201' ] ) );
	}

	public function testCheckIsSubclassCyclic() {
		$this->assertEquals( false, $this->helper->isSubclassOf( new ItemId( 'Q7' ), [ 'Q100', 'Q101' ] ) );
	}

}
