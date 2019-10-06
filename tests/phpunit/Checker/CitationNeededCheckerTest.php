<?php

namespace WikibaseQuality\ConstraintReport\Tests\Checker;

use DataValues\StringValue;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Reference;
use Wikibase\DataModel\ReferenceList;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Snak\Snak;
use Wikibase\DataModel\Statement\Statement;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\CitationNeededChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\MainSnakContext;
use WikibaseQuality\ConstraintReport\Tests\ResultAssertions;

/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\CitationNeededChecker
 *
 * @group WikibaseQualityConstraints
 *
 * @author Amir Sarabadani
 * @license GPL-2.0-or-later
 */
class CitationNeededCheckerTest extends \PHPUnit\Framework\TestCase {

	use ResultAssertions;

	/**
	 * @param Snak $snak
	 * @param string|null $messageKey key of violation message, or null if compliance is expected
	 * @dataProvider provideStatements
	 */
	public function testCitationNeededConstraint( Statement $statement, $messageKey ) {
		$checker = new CitationNeededChecker();
		$context = new MainSnakContext( new Item( new ItemId( 'Q7251' ) ), $statement );

		$checkResult = $checker->checkConstraint( $context, $this->getConstraintMock( [] ) );

		if ( $messageKey === null ) {
			$this->assertCompliance( $checkResult );
		} else {
			$this->assertViolation( $checkResult, $messageKey );
		}
	}

	public function provideStatements() {
		$mainSnak = new PropertyValueSnak( new PropertyId( 'P1' ), new StringValue( 'emacs forever' ) );
		$referenceSnak = new PropertyValueSnak( new PropertyId( 'P2' ), new StringValue( 'Everyone says so' ) );

		$statementWithoutReference = new Statement( $mainSnak );
		$statementWithReference = new Statement(
			$mainSnak,
			null,
			new ReferenceList( [ new Reference( [ $referenceSnak ] ) ] )
		);

		return [
			[ $statementWithoutReference, 'wbqc-violation-message-citationNeeded' ],
			[ $statementWithReference, null ],
		];
	}

	public function testCheckConstraintParameters() {
		$checker = new CitationNeededChecker();
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
			->will( $this->returnValue( 'Q54554025' ) );

		return $mock;
	}

}
