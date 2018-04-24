<?php

namespace WikibaseQuality\ConstraintReport\Tests\ValueCountChecker;

use Wikibase\DataModel\Statement\Statement;
use Wikibase\Repo\Tests\NewItem;
use Wikibase\Repo\Tests\NewStatement;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\SingleBestValueChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\MainSnakContext;
use WikibaseQuality\ConstraintReport\Tests\ResultAssertions;

/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\SingleBestValueCheckerChecker
 *
 * @group WikibaseQualityConstraints
 *
 * @author Lucas Werkmeister
 * @license GPL-2.0-or-later
 */
class SingleBestValueCheckerTest extends \PHPUnit\Framework\TestCase {

	use ResultAssertions;

	/**
	 * @var Constraint
	 */
	private $constraint;

	/**
	 * @var SingleBestValueChecker
	 */
	private $checker;

	protected function setUp() {
		parent::setUp();

		$this->constraint = $this->getMockBuilder( Constraint::class )
			->disableOriginalConstructor()
			->getMock();
		$this->checker = new SingleBestValueChecker();
	}

	/**
	 * @dataProvider provideRanksAndMessageKeys
	 */
	public function testCheckConstraint( array $ranks, $messageKey ) {
		$item = NewItem::withId( 'Q1' );
		foreach ( $ranks as $rank ) {
			$statement = NewStatement::noValueFor( 'P1' )
				->withRank( $rank )
				->build();
			$item = $item->andStatement( $statement );
		}
		$context = new MainSnakContext( $item->build(), $statement );

		$checkResult = $this->checker->checkConstraint( $context, $this->constraint );

		if ( $messageKey !== null ) {
			$this->assertViolation( $checkResult, $messageKey );
		} else {
			$this->assertCompliance( $checkResult );
		}
	}

	public function provideRanksAndMessageKeys() {
		$normal = Statement::RANK_NORMAL;
		$preferred = Statement::RANK_PREFERRED;

		return [
			[ [ $normal ], null ],
			[ [ $preferred ], null ],
			[ [ $normal, $preferred ], null ],
			[ [ $normal, $normal, $preferred ], null ],
			[ [ $normal, $normal ], 'wbqc-violation-message-single-best-value-no-preferred' ],
			[ [ $normal, $preferred, $preferred ], 'wbqc-violation-message-single-best-value-multi-preferred' ],
		];
	}

	public function testCheckConstraint_Deprecated() {
		$statement = NewStatement::noValueFor( 'P1' )
			->withDeprecatedRank()
			->build();
		$entity = NewItem::withId( 'Q1' )
			->build();
		$context = new MainSnakContext( $entity, $statement );

		$checkResult = $this->checker->checkConstraint( $context, $this->constraint );

		$this->assertDeprecation( $checkResult );
	}

	public function testCheckConstraintParameters() {
		$result = $this->checker->checkConstraintParameters( $this->constraint );

		$this->assertEmpty( $result );
	}

}
