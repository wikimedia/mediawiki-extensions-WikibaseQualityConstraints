<?php

namespace WikibaseQuality\ConstraintReport\Tests\Checker\Lexeme;

use DataValues\StringValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Snak\Snak;
use Wikibase\Lexeme\Tests\Unit\DataModel\NewForm;
use Wikibase\Lexeme\Tests\Unit\DataModel\NewLexeme;
use Wikibase\Lexeme\Tests\Unit\DataModel\NewSense;
use Wikibase\Repo\Tests\NewStatement;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\Lexeme\LanguageChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\MainSnakContext;
use WikibaseQuality\ConstraintReport\Tests\ConstraintParameters;
use WikibaseQuality\ConstraintReport\Tests\Fake\FakeSnakContext;
use WikibaseQuality\ConstraintReport\Tests\ResultAssertions;

/**
 * @covers \WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\Lexeme\LanguageChecker
 *
 * @group WikibaseQualityConstraints
 *
 * @license GPL-2.0-or-later
 */
class LanguageCheckerTest extends \MediaWikiTestCase {

	use ConstraintParameters;
	use ResultAssertions;

	/**
	 * @var LanguageChecker
	 */
	private $languageChecker;

	protected function setUp() : void {
		parent::setUp();
		$this->languageChecker = new LanguageChecker(
			$this->getConstraintParameterParser()
		);
	}

	public function testLanguageConstraintValid() {
		$statement = NewStatement::forProperty( 'P123' )
			->withValue( new ItemId( 'Q9' ) )
			->build();

		$result = $this->languageChecker->checkConstraint(
			$this->getContext( $statement->getMainSnak(), new ItemId( 'Q2' ) ),
			$this->getConstraintMock( $this->itemsParameter( [ 'Q1', 'Q2', 'Q3' ] ) )
		);

		$this->assertCompliance( $result );
	}

	public function testLanguageConstraintInvalid() {
		$statement = NewStatement::forProperty( 'P123' )
			->withValue( new ItemId( 'Q1' ) )
			->build();

		$result = $this->languageChecker->checkConstraint(
			$this->getContext( $statement->getMainSnak(), new ItemId( 'Q5' ) ),
			$this->getConstraintMock( $this->itemsParameter( [ 'Q1', 'Q2', 'Q3' ] ) )
		);

		$this->assertViolation( $result, 'wbqc-violation-message-language' );
	}

	public function testLanguageConstraintWrongEntityType() {
		$statement = NewStatement::forProperty( 'P123' )
			->withValue( new StringValue( 'Q1' ) )
			->build();

		$result = $this->languageChecker->checkConstraint(
			new FakeSnakContext( $statement->getMainSnak() ),
			$this->getConstraintMock( $this->itemsParameter( [ 'Q1', 'Q2', 'Q3' ] ) )
		);

		$this->assertNotInScope( $result );
	}

	public function testLanguageConstraintWithForm() {
		// TODO: Add support for form
		$statement = NewStatement::forProperty( 'P123' )
			->withValue( new StringValue( 'Q1' ) )
			->build();

		$result = $this->languageChecker->checkConstraint(
			new FakeSnakContext( $statement->getMainSnak(), NewForm::any()->build() ),
			$this->getConstraintMock( $this->itemsParameter( [ 'Q1', 'Q2', 'Q3' ] ) )
		);

		$this->assertNotInScope( $result );
	}

	public function testLanguageConstraintWithSense() {
		// TODO: Add support for sense
		$statement = NewStatement::forProperty( 'P123' )
			->withValue( new StringValue( 'Q1' ) )
			->build();

		$result = $this->languageChecker->checkConstraint(
			new FakeSnakContext(
				$statement->getMainSnak(),
				NewSense::havingId( 'S1' )->build()
			),
			$this->getConstraintMock( $this->itemsParameter( [ 'Q1', 'Q2', 'Q3' ] ) )
		);

		$this->assertNotInScope( $result );
	}

	public function testLanguageConstraintDeprecatedStatement() {
		$statement = NewStatement::noValueFor( 'P1' )
			->withDeprecatedRank()
			->build();
		$constraint = $this->getConstraintMock( [] );
		$lexeme = NewLexeme::havingId( 'L1' )
			->withLanguage( 'Q1' )
			->build();

		$checkResult = $this->languageChecker->checkConstraint(
			new MainSnakContext( $lexeme, $statement ),
			$constraint
		);

		$this->assertDeprecation( $checkResult );
	}

	public function testCheckConstraintParameters() {
		$constraint = $this->getConstraintMock( [] );

		$result = $this->languageChecker->checkConstraintParameters( $constraint );

		$this->assertCount( 1, $result );
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
			->will( $this->returnValue( 'Q52558054' ) );

		return $mock;
	}

	private function getContext( Snak $snak, ItemId $language ) {
		$lexeme = NewLexeme::havingId( 'L1' )
			->withLanguage( $language )
			->build();
		return new FakeSnakContext( $snak, $lexeme );
	}

}
