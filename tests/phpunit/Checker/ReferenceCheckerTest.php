<?php

namespace WikibaseQuality\ConstraintReport\Test;

use Wikibase\Repo\Tests\NewItem;
use Wikibase\Repo\Tests\NewStatement;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\ReferenceChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\Context;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\StatementContext;
use WikibaseQuality\ConstraintReport\Tests\ResultAssertions;

/**
 * @covers \WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\ReferenceChecker
 *
 * @group WikibaseQualityConstraints
 *
 * @uses \WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 *
 * @author Lucas Werkmeister
 * @license GNU GPL v2+
 */
class ReferenceCheckerTest extends \PHPUnit_Framework_TestCase {

	use ResultAssertions;

	/**
	 * @param string $type context type
	 * @param string|null $messageKey key of violation message, or null if compliance is expected
	 * @dataProvider contextTypes
	 */
	public function testReferenceConstraint( $type, $messageKey ) {
		$context = $this->getMock( Context::class );
		$context->method( 'getType' )->willReturn( $type );
		$checker = new ReferenceChecker();
		$constraint = $this->getConstraintMock( [] );

		$checkResult = $checker->checkConstraint( $context, $constraint );

		if ( $messageKey === null ) {
			$this->assertCompliance( $checkResult );
		} else {
			$this->assertViolation( $checkResult, $messageKey );
		}
	}

	public function contextTypes() {
		return [
			[ Context::TYPE_STATEMENT, 'wbqc-violation-message-reference' ],
			[ Context::TYPE_QUALIFIER, 'wbqc-violation-message-reference' ],
			[ Context::TYPE_REFERENCE, null ],
		];
	}

	public function testReferenceConstraintDeprecatedStatement() {
		$checker = new ReferenceChecker();
		$statement = NewStatement::noValueFor( 'P1' )
			->withDeprecatedRank()
			->build();
		$constraint = $this->getConstraintMock( [] );
		$entity = NewItem::withId( 'Q1' )
			->build();

		$checkResult = $checker->checkConstraint( new StatementContext( $entity, $statement ), $constraint );

		// this constraint is still checked on deprecated statements
		$this->assertViolation( $checkResult, 'wbqc-violation-message-reference' );
	}

	public function testCheckConstraintParameters() {
		$checker = new ReferenceChecker();
		$constraint = $this->getConstraintMock( [] );

		$result = $checker->checkConstraintParameters( $constraint );

		$this->assertEmpty( $result );
	}

	/**
	 * @return Constraint
	 */
	private function getConstraintMock() {
		$mock = $this
			->getMockBuilder( Constraint::class )
			->disableOriginalConstructor()
			->getMock();
		$mock->expects( $this->any() )
			->method( 'getConstraintParameters' )
			->will( $this->returnValue( [] ) );
		$mock->expects( $this->any() )
			->method( 'getConstraintTypeItemId' )
			->will( $this->returnValue( 'Reference' ) );

		return $mock;
	}

}
