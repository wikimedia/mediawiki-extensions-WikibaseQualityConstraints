<?php

namespace WikibaseQuality\ConstraintReport\Test\QualifierChecker;

use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Statement\StatementListProvider;
use Wikibase\Repo\Tests\NewItem;
use Wikibase\Repo\Tests\NewStatement;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\QualifierChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\Context;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\MainSnakContext;
use WikibaseQuality\ConstraintReport\Tests\ResultAssertions;

/**
 * @covers \WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\QualifierChecker
 *
 * @group WikibaseQualityConstraints
 *
 * @uses   \WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class QualifierCheckerTest extends \MediaWikiTestCase {

	use ResultAssertions;

	/**
	 * @param StatementListProvider $entity
	 *
	 * @return Statement|false
	 */
	private function getFirstStatement( StatementListProvider $entity ) {
		$statements = $entity->getStatements()->toArray();
		return reset( $statements );
	}

	/**
	 * @param string $type context type
	 * @param string|null $messageKey key of violation message, or null if compliance is expected
	 * @dataProvider contextTypes
	 */
	public function testQualifierConstraint( $type, $messageKey ) {
		$context = $this->getMock( Context::class );
		$context->method( 'getType' )->willReturn( $type );
		$checker = new QualifierChecker();
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
			[ Context::TYPE_STATEMENT, 'wbqc-violation-message-qualifier' ],
			[ Context::TYPE_QUALIFIER, null ],
			[ Context::TYPE_REFERENCE, 'wbqc-violation-message-qualifier' ],
		];
	}

	public function testQualifierConstraintDeprecatedStatement() {
		$checker = new QualifierChecker();
		$statement = NewStatement::noValueFor( 'P1' )
				   ->withDeprecatedRank()
				   ->build();
		$constraint = $this->getConstraintMock( [] );
		$entity = NewItem::withId( 'Q1' )
				->build();

		$checkResult = $checker->checkConstraint( new MainSnakContext( $entity, $statement ), $constraint );

		// this constraint is still checked on deprecated statements
		$this->assertViolation( $checkResult, 'wbqc-violation-message-qualifier' );
	}

	public function testCheckConstraintParameters() {
		$checker = new QualifierChecker();
		$constraint = $this->getConstraintMock( [] );

		$result = $checker->checkConstraintParameters( $constraint );

		$this->assertEmpty( $result );
	}

	/**
	 * @param string[] $parameters
	 *
	 * @return Constraint
	 */
	private function getConstraintMock( array $parameters ) {
		$mock = $this
			->getMockBuilder( Constraint::class )
			->disableOriginalConstructor()
			->getMock();
		$mock->expects( $this->any() )
			 ->method( 'getConstraintParameters' )
			 ->will( $this->returnValue( $parameters ) );
		$mock->expects( $this->any() )
			 ->method( 'getConstraintTypeItemId' )
			 ->will( $this->returnValue( 'Qualifier' ) );

		return $mock;
	}

}
