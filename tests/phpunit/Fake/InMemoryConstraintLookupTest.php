<?php

namespace WikibaseQuality\ConstraintReport\Tests\Fake;

use Wikibase\DataModel\Entity\PropertyId;
use WikibaseQuality\ConstraintReport\Constraint;

class InMemoryConstraintLookupTest extends \PHPUnit_Framework_TestCase {

	public function testQuery_NewLookup_ReturnsEmptyArrayForProperty() {
		$lookup = new InMemoryConstraintLookup( [] );

		$this->assertSame( [], $lookup->queryConstraintsForProperty( 2 ) );
	}

	public function testQuery_AddConstraintAndQueryWithItsProperty_ReturnsThatConstraint() {
		$constraint = new Constraint( 'some id', new PropertyId( 'P2' ), 'some type', [] );
		$lookup = new InMemoryConstraintLookup( [ $constraint ] );

		$this->assertSame( [ $constraint ], $lookup->queryConstraintsForProperty( 2 ) );
	}

	public function testQuery_AddSeveralConstraintsAndQueryForOne_ReturnsOnlyThatConstraint() {
		$expectedConstraint = new Constraint( 'some id', new PropertyId( 'P2' ), 'some type', [] );
		$otherConstraint = new Constraint( 'some id', new PropertyId( 'P3' ), 'some type', [] );
		$lookup = new InMemoryConstraintLookup( [ $expectedConstraint, $otherConstraint ] );

		$this->assertSame( [ $expectedConstraint ], $lookup->queryConstraintsForProperty( 2 ) );
	}

}
