<?php

namespace WikibaseQuality\ConstraintReport\Tests\Fake;

use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Statement\Statement;
use WikibaseQuality\ConstraintReport\Constraint;

class FakeCheckerTest extends \PHPUnit_Framework_TestCase {

	public function testCheckConstraint_ResultContainsPassedStatement() {
		$checker = new FakeChecker();

		$statement = $this->dummy( Statement::class );
		$result = $checker->checkConstraint(
			$statement,
			$this->dummy( Constraint::class )
		);

		$this->assertSame( $statement, $result->getStatement() );
	}

	public function testCheckConstraint_ResultContainsNameOfPassedConstraint() {
		$checker = new FakeChecker();

		$constraintTypeId = 'constraint id';
		$result = $checker->checkConstraint(
			$this->dummy( Statement::class ),
			new Constraint( 'some guid', $this->dummy( PropertyId::class ), $constraintTypeId, [] )
		);

		$this->assertSame( $constraintTypeId, $result->getConstraintName() );
	}

	public function testCheckConstraint_CreatedWithSomeStatus_ReturnsThatStatusInResult() {
		$expectedStatus = 'some status';

		$checker = new FakeChecker( $expectedStatus );
		$result = $checker->checkConstraint(
			$this->dummy( Statement::class ),
			$this->dummy( Constraint::class )
		);

		$this->assertSame( $expectedStatus, $result->getStatus() );
	}

	private function dummy( $class ) {
		return $this->prophesize( $class )->reveal();
	}

}
