<?php

namespace WikibaseQuality\ConstraintReport\Test\ValueCountChecker;

use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Entity\EntityIdValue;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\UniqueValueChecker;
use WikibaseQuality\ConstraintReport\Tests\ResultAssertions;

/**
 * @covers \WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\UniqueValueChecker
 *
 * @group WikibaseQualityConstraints
 *
 * @uses   \WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class UniqueValueCheckerTest extends \MediaWikiTestCase {

	use ResultAssertions;

	/**
	 * @var PropertyId
	 */
	private $uniquePropertyId;

	/**
	 * @var UniqueValueChecker
	 */
	private $checker;

	protected function setUp() {
		parent::setUp();

		$this->uniquePropertyId = new PropertyId( 'P227' );
		$this->checker = new UniqueValueChecker();
	}

	protected function tearDown() {
		unset( $this->uniquePropertyId );
		parent::tearDown();
	}

	public function testCheckUniqueValueConstraint() {
		$itemId = new ItemId( 'Q404' );
		$entity = new Item( $itemId );
		$statement = new Statement( new PropertyValueSnak( $this->uniquePropertyId, new EntityIdValue( $itemId ) ) );
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( [] ), $entity );

		$this->assertTodo( $checkResult );
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
			 ->method( 'getConstraintParameter' )
			 ->will( $this->returnValue( $parameters ) );
		$mock->expects( $this->any() )
			 ->method( 'getConstraintTypeQid' )
			 ->will( $this->returnValue( 'Unique value' ) );

		return $mock;
	}

}
