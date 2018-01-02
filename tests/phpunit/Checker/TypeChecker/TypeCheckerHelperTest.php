<?php

namespace WikibaseQuality\ConstraintReport\Test\TypeChecker;

use PHPUnit_Framework_TestCase;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\DataModel\Services\Lookup\InMemoryEntityLookup;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Statement\StatementList;
use Wikibase\Repo\Tests\NewItem;
use Wikibase\Repo\Tests\NewStatement;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachedBool;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\Metadata;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\SparqlHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\TypeCheckerHelper;
use WikibaseQuality\ConstraintReport\Tests\ConstraintParameters;
use WikibaseQuality\Tests\Helper\JsonFileEntityLookup;

/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\TypeCheckerHelper
 *
 * @group WikibaseQualityConstraints
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class TypeCheckerHelperTest extends PHPUnit_Framework_TestCase {

	use ConstraintParameters;

	/**
	 * @param EntityLookup|null $entityLookup
	 * @param SparqlHelper|null $sparqlHelper
	 *
	 * @return TypeCheckerHelper
	 */
	private function getHelper(
		EntityLookup $entityLookup = null,
		SparqlHelper $sparqlHelper = null
	) {
		return new TypeCheckerHelper(
			$entityLookup ?: new JsonFileEntityLookup( __DIR__ ),
			$this->getDefaultConfig(),
			$this->getConstraintParameterRenderer(),
			$sparqlHelper
		);
	}

	/**
	 * @param EntityLookup $lookup The backing lookup of the mock (defaults to JsonFileEntityLookup).
	 *
	 * @return EntityLookup Expects that getEntity is called
	 * exactly WBQualityConstraintsTypeCheckMaxEntities times.
	 */
	private function getMaxEntitiesLookup( EntityLookup $lookup = null ) {
		if ( $lookup === null ) {
			$lookup = new JsonFileEntityLookup( __DIR__ );
		}
		$maxEntities = $this->getDefaultConfig()->get( 'WBQualityConstraintsTypeCheckMaxEntities' );

		$spy = $this->getMock( EntityLookup::class );
		$spy->expects( $this->exactly( $maxEntities ) )
			->method( 'getEntity' )
			->will( $this->returnCallback( [ $lookup, 'getEntity' ] ) );

		return $spy;
	}

	/**
	 * @param boolean $return
	 * @param array|null $arguments
	 *
	 * @return SparqlHelper expects that {@link SparqlHelper::hasType}
	 * is called exactly once and returns $return.
	 */
	private function getSparqlHelper( $return, array $arguments = null ) {
		if ( $arguments === null ) {
			$arguments = [ $this->anything(), $this->anything(), $this->anything() ];
		}
		$mock = $this->getMockBuilder( SparqlHelper::class )
			  ->disableOriginalConstructor()
			  ->getMock();
		$mock->expects( $this->once() )
			->method( 'hasType' )
			->withConsecutive( $arguments )
			->willReturn( new CachedBool( $return, Metadata::blank() ) );
		return $mock;
	}

	public function testCheckHasClassInRelationValid() {
		$statement1 = new Statement( new PropertyValueSnak( new PropertyId( 'P1' ), new EntityIdValue( new ItemId( 'Q42' ) ) ) );
		$statement2 = new Statement( new PropertyValueSnak( new PropertyId( 'P31' ), new EntityIdValue( new ItemId( 'Q1' ) ) ) );
		$statements = new StatementList( [ $statement1, $statement2 ] );
		$this->assertTrue( $this->getHelper()->hasClassInRelation( $statements, 'P31', [ 'Q1' ] )->getBool() );
	}

	public function testCheckHasClassInRelationInvalid() {
		$statement1 = new Statement( new PropertyValueSnak( new PropertyId( 'P1' ), new EntityIdValue( new ItemId( 'Q42' ) ) ) );
		$statement2 = new Statement( new PropertyValueSnak( new PropertyId( 'P31' ), new EntityIdValue( new ItemId( 'Q100' ) ) ) );
		$statements = new StatementList( [ $statement1, $statement2 ] );
		$this->assertFalse( $this->getHelper()->hasClassInRelation( $statements, 'P31', [ 'Q1' ] )->getBool() );
	}

	public function testCheckHasClassInRelationValidWithIndirection() {
		$statement1 = new Statement( new PropertyValueSnak( new PropertyId( 'P1' ), new EntityIdValue( new ItemId( 'Q42' ) ) ) );
		$statement2 = new Statement( new PropertyValueSnak( new PropertyId( 'P31' ), new EntityIdValue( new ItemId( 'Q5' ) ) ) );
		$statements = new StatementList( [ $statement1, $statement2 ] );
		$this->assertTrue( $this->getHelper()->hasClassInRelation( $statements, 'P31', [ 'Q4' ] )->getBool() );
	}

	public function testCheckIsSubclassOfValidWithIndirection() {
		$this->assertTrue( $this->getHelper()->isSubclassOfWithSparqlFallback( new ItemId( 'Q6' ), [ 'Q100', 'Q101' ] )->getBool() );
	}

	public function testCheckIsSubclassOfInvalid() {
		$this->assertFalse( $this->getHelper()->isSubclassOfWithSparqlFallback( new ItemId( 'Q6' ), [ 'Q200', 'Q201' ] )->getBool() );
	}

	public function testCheckIsSubclassCyclic() {
		$helper = $this->getHelper( $this->getMaxEntitiesLookup() );
		$this->assertFalse( $helper->isSubclassOfWithSparqlFallback( new ItemId( 'Q7' ), [ 'Q100', 'Q101' ] )->getBool() );
	}

	public function testCheckIsSubclassCyclicWide() {
		$helper = $this->getHelper( $this->getMaxEntitiesLookup() );
		$this->assertFalse( $helper->isSubclassOfWithSparqlFallback( new ItemId( 'Q9' ), [ 'Q100', 'Q101' ] )->getBool() );
	}

	public function testCheckIsSubclassCyclicWideWithSparqlTrue() {
		$helper = $this->getHelper( $this->getMaxEntitiesLookup(), $this->getSparqlHelper( true ) );
		$this->assertTrue( $helper->isSubclassOfWithSparqlFallback( new ItemId( 'Q9' ), [ 'Q100', 'Q101' ] )->getBool() );
	}

	public function testCheckIsSubclassCyclicWideWithSparqlFalse() {
		$helper = $this->getHelper( $this->getMaxEntitiesLookup(), $this->getSparqlHelper( false ) );
		$this->assertFalse( $helper->isSubclassOfWithSparqlFallback( new ItemId( 'Q9' ), [ 'Q100', 'Q101' ] )->getBool() );
	}

	public function testCheckIsSubclassTree() {
		$lookup = new InMemoryEntityLookup();
		$subclassPid = $this->getDefaultConfig()->get( 'WBQualityConstraintsSubclassOfId' );

		$q1 = NewItem::withId( 'Q1' )
			->andStatement(
				NewStatement::forProperty( $subclassPid )
					->withValue( new ItemId( 'Q2' ) )
			)
			->build();
		$lookup->addEntity( $q1 );
		$q2 = NewItem::withId( 'Q2' )
			->andStatement(
				NewStatement::forProperty( $subclassPid )
					->withValue( new ItemId( 'Q3' ) )
			)
			->andStatement(
				NewStatement::forProperty( $subclassPid )
					->withValue( new ItemId( 'Q5' ) )
			)
			->build();
		$lookup->addEntity( $q2 );
		$q3 = NewItem::withId( 'Q3' )
			->andStatement(
				NewStatement::forProperty( $subclassPid )
					->withValue( new ItemId( 'Q4' ) )
			)
			->build();
		$lookup->addEntity( $q3 );
		$q4 = NewItem::withId( 'Q4' )
			->andStatement(
				NewStatement::forProperty( $subclassPid )
					->withValue( new ItemId( 'Q3' ) )
			)
			->build();
		$lookup->addEntity( $q4 );

		$sparqlHelper = $this->getSparqlHelper(
			true,
			[
				$this->identicalTo( 'Q1' ),
				$this->identicalTo( [ 'Q5' ] ),
				$this->identicalTo( false )
			]
		);
		$helper = $this->getHelper( $this->getMaxEntitiesLookup( $lookup ), $sparqlHelper );

		$this->assertTrue( $helper->isSubclassOfWithSparqlFallback( new ItemId( 'Q1' ), [ 'Q5' ] )->getBool() );
	}

}
