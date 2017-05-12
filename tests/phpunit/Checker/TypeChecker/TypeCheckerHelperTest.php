<?php

namespace WikibaseQuality\ConstraintReport\Test\TypeChecker;

use PHPUnit_Framework_TestCase;
use Wikibase\DataModel\Services\EntityId\PlainEntityIdFormatter;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
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
	 * @return TypeCheckerHelper
	 */
	private function getHelper() {
		return new TypeCheckerHelper(
			new JsonFileEntityLookup( __DIR__ ),
			$this->getDefaultConfig(),
			new PlainEntityIdFormatter()
		);
	}

	/**
	 * @return TypeCheckerHelper asserts that the helper calls getEntity exactly MAX_ENTITIES times
	 */
	private function getMaxEntitiesHelper() {
		$realLookup = new JsonFileEntityLookup( __DIR__ );
		$lookup = $this->getMockBuilder( EntityLookup::class )->getMock();
		$lookup->expects( $this->exactly( TypeCheckerHelper::MAX_ENTITIES ) )
			->method( 'getEntity' )
			->will( $this->returnCallback( [ $realLookup, 'getEntity' ] ) );
		return new TypeCheckerHelper( $lookup, $this->getDefaultConfig(), new PlainEntityIdFormatter() );
	}

	public function testCheckHasClassInRelationValid() {
		$statement1 = new Statement( new PropertyValueSnak( new PropertyId( 'P1' ), new EntityIdValue( new ItemId( 'Q42' ) ) ) );
		$statement2 = new Statement( new PropertyValueSnak( new PropertyId( 'P31' ), new EntityIdValue( new ItemId( 'Q1' ) ) ) );
		$statements = new StatementList( [ $statement1, $statement2 ] );
		$this->assertTrue( $this->getHelper()->hasClassInRelation( $statements, 'P31', [ 'Q1' ] ) );
	}

	public function testCheckHasClassInRelationInvalid() {
		$statement1 = new Statement( new PropertyValueSnak( new PropertyId( 'P1' ), new EntityIdValue( new ItemId( 'Q42' ) ) ) );
		$statement2 = new Statement( new PropertyValueSnak( new PropertyId( 'P31' ), new EntityIdValue( new ItemId( 'Q100' ) ) ) );
		$statements = new StatementList( [ $statement1, $statement2 ] );
		$this->assertFalse( $this->getHelper()->hasClassInRelation( $statements, 'P31', [ 'Q1' ] ) );
	}

	public function testCheckHasClassInRelationValidWithIndirection() {
		$statement1 = new Statement( new PropertyValueSnak( new PropertyId( 'P1' ), new EntityIdValue( new ItemId( 'Q42' ) ) ) );
		$statement2 = new Statement( new PropertyValueSnak( new PropertyId( 'P31' ), new EntityIdValue( new ItemId( 'Q5' ) ) ) );
		$statements = new StatementList( [ $statement1, $statement2 ] );
		$this->assertTrue( $this->getHelper()->hasClassInRelation( $statements, 'P31', [ 'Q4' ] ) );
	}

	public function testCheckIsSubclassOfValidWithIndirection() {
		$this->assertTrue( $this->getHelper()->isSubclassOf( new ItemId( 'Q6' ), [ 'Q100', 'Q101' ] ) );
	}

	public function testCheckIsSubclassOfInvalid() {
		$this->assertFalse( $this->getHelper()->isSubclassOf( new ItemId( 'Q6' ), [ 'Q200', 'Q201' ] ) );
	}

	public function testCheckIsSubclassCyclic() {
		$this->assertFalse( $this->getMaxEntitiesHelper()->isSubclassOf( new ItemId( 'Q7' ), [ 'Q100', 'Q101' ] ) );
	}

	public function testCheckIsSubclassCyclicWide() {
		$this->assertFalse( $this->getMaxEntitiesHelper()->isSubclassOf( new ItemId( 'Q9' ), [ 'Q100', 'Q101' ] ) );
	}

}
