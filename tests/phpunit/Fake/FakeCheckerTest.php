<?php

namespace WikibaseQuality\ConstraintReport\Tests\Fake;

use Wikibase\DataModel\Entity\PropertyId;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\Context;

/**
 * @covers WikibaseQuality\ConstraintReport\Tests\Fake\FakeChecker
 *
 * @group WikibaseQualityConstraints
 *
 * @license GNU GPL v2+
 */
class FakeCheckerTest extends \PHPUnit\Framework\TestCase {

	public function testCheckConstraint_ResultContainsPassedContext() {
		$checker = new FakeChecker();
		$context = $this->getMock( Context::class );
		$constraint = $this->dummy( Constraint::class );

		$result = $checker->checkConstraint(
			$context,
			$constraint
		);

		$this->assertSame( $context, $result->getContext() );
	}

	public function testCheckConstraint_ResultContainsPassedConstraint() {
		$checker = new FakeChecker();
		$context = $this->getMock( Context::class );
		$constraint = new Constraint( 'some guid', $this->dummy( PropertyId::class ), 'some constraint type item id', [] );

		$result = $checker->checkConstraint(
			$context,
			$constraint
		);

		$this->assertSame( $constraint, $result->getConstraint() );
	}

	public function testCheckConstraint_CreatedWithSomeStatus_ReturnsThatStatusInResult() {
		$expectedStatus = 'some status';

		$checker = new FakeChecker( $expectedStatus );
		$result = $checker->checkConstraint(
			$this->getMock( Context::class ),
			$this->dummy( Constraint::class )
		);

		$this->assertSame( $expectedStatus, $result->getStatus() );
	}

	private function dummy( $class ) {
		return $this->prophesize( $class )->reveal();
	}

}
