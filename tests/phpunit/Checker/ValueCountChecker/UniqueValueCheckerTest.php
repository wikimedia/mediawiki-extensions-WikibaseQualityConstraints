<?php

namespace WikibaseQuality\ConstraintReport\Test\ValueCountChecker;

use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Entity\EntityIdValue;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\UniqueValueChecker;

/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\UniqueValueChecker
 *
 * @group WikibaseQualityConstraints
 *
 * @uses   WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class UniqueValueCheckerTest extends \MediaWikiTestCase {

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

	// todo: it is currently only testing that 'todo' comes back
	public function testCheckUniqueValueConstraint() {
		$statement = new Statement( new PropertyValueSnak( $this->uniquePropertyId, new EntityIdValue( new ItemId( 'Q404' ) ) ) );
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( array() ) );
		$this->assertEquals( 'todo', $checkResult->getStatus(), 'check should point out that it should be implemented soon' );
	}

	/**
	 * @param string[] $parameters
	 *
	 * @return Constraint
	 */
	private function getConstraintMock( array $parameters ) {
		$mock = $this
			->getMockBuilder( 'WikibaseQuality\ConstraintReport\Constraint' )
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
