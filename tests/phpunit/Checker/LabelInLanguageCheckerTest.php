<?php

declare( strict_types = 1 );

namespace WikibaseQuality\ConstraintReport\Tests\Checker;

use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Snak\Snak;
use Wikibase\DataModel\Tests\NewItem;
use Wikibase\DataModel\Tests\NewStatement;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\LabelInLanguageChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\Context;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\MainSnakContext;
use WikibaseQuality\ConstraintReport\Tests\ConstraintParameters;
use WikibaseQuality\ConstraintReport\Tests\Fake\FakeSnakContext;
use WikibaseQuality\ConstraintReport\Tests\ResultAssertions;

/**
 * @covers \WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\LabelInLanguageChecker
 *
 * @group WikibaseQualityConstraints
 *
 * @license GPL-2.0-or-later
 */
class LabelInLanguageCheckerTest extends \MediaWikiIntegrationTestCase {

	use ConstraintParameters;
	use ResultAssertions;

	private LabelInLanguageChecker $labelInLanguageChecker;

	protected function setUp(): void {
		parent::setUp();
		$this->labelInLanguageChecker = new LabelInLanguageChecker(
			$this->getConstraintParameterParser()
		);
	}

	/** @dataProvider provideValidLanguageCodes */
	public function testLabelInLanguageConstraintValid(
		string $existingLanguageCode,
		array $expectedLanguageCodes
	): void {
		$statement = NewStatement::forProperty( 'P123' )
			->withValue( new ItemId( 'Q9' ) )
			->build();

		$result = $this->labelInLanguageChecker->checkConstraint(
			$this->getContext( $statement->getMainSnak(), $existingLanguageCode ),
			$this->getConstraintMock( $this->languageParameter( $expectedLanguageCodes ) )
		);

		$this->assertCompliance( $result );
	}

	public static function provideValidLanguageCodes(): iterable {
		yield 'first language' => [ 'en', [ 'en', 'fr' ] ];
		yield 'second language' => [ 'fr', [ 'en', 'fr' ] ];
		yield 'mul' => [ 'mul', [ 'en' ] ];
	}

	public function testLabelInLanguageConstraintInvalid(): void {
		$statement = NewStatement::forProperty( 'P123' )
			->withValue( new ItemId( 'Q1' ) )
			->build();

		$result = $this->labelInLanguageChecker->checkConstraint(
			$this->getContext( $statement->getMainSnak(), 'de' ),
			$this->getConstraintMock( $this->languageParameter( [ 'en', 'fr' ] ) )
		);

		$this->assertViolation( $result, 'wbqc-violation-message-label-lacking' );
	}

	public function testLabelInLanguageConstraintDeprecatedStatement(): void {
		$statement = NewStatement::noValueFor( 'P1' )
			->withDeprecatedRank()
			->build();
		$constraint = $this->getConstraintMock( [] );
		$item = NewItem::withId( 'Q1' )
			->andLabel( 'en', 'Foo' )
			->build();

		$checkResult = $this->labelInLanguageChecker->checkConstraint(
			new MainSnakContext( $item, $statement ),
			$constraint
		);

		$this->assertDeprecation( $checkResult );
	}

	public function testCheckConstraintParameters(): void {
		$constraint = $this->getConstraintMock( [] );

		$result = $this->labelInLanguageChecker->checkConstraintParameters( $constraint );

		$this->assertCount( 1, $result );
	}

	private function getConstraintMock( array $parameters ): Constraint {
		$mock = $this->createMock( Constraint::class );
		$mock->expects( $this->any() )
			->method( 'getConstraintParameters' )
			->willReturn( $parameters );
		$mock->expects( $this->any() )
			->method( 'getConstraintTypeItemId' )
			->willReturn( 'Q108139345' );

		return $mock;
	}

	private function getContext( Snak $snak, string $languageCode ): Context {
		$item = NewItem::withId( 'Q1' )
			->andLabel( $languageCode, 'Foo' )
			->build();
		return new FakeSnakContext( $snak, $item );
	}

}
