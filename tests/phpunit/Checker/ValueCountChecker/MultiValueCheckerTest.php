<?php

namespace WikibaseQuality\ConstraintReport\Test\ValueCountChecker;

use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Entity\EntityIdValue;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\MultiValueChecker;
use WikibaseQuality\ConstraintReport\Tests\ResultAssertions;
use WikibaseQuality\Tests\Helper\JsonFileEntityLookup;

/**
 * @covers \WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\MultiValueChecker
 *
 * @group WikibaseQualityConstraints
 *
 * @uses   \WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class MultiValueCheckerTest extends \MediaWikiTestCase {

	use ResultAssertions;

	/**
	 * @var PropertyId
	 */
	private $multiPropertyId;

	/**
	 * @var MultiValueChecker
	 */
	private $checker;

	/**
	 * @var JsonFileEntityLookup
	 */
	private $lookup;

	protected function setUp() {
		parent::setUp();

		$this->multiPropertyId = new PropertyId( 'P161' );
		$this->checker = new MultiValueChecker();
		$this->lookup = new JsonFileEntityLookup( __DIR__ );
	}

	public function testMultiValueConstraintOne() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q4' ) );
		$statement = new Statement( new PropertyValueSnak( $this->multiPropertyId, new EntityIdValue( new ItemId( 'Q207' ) ) ) );
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( [] ), $entity );
		$this->assertViolation( $checkResult, 'wbqc-violation-message-multi-value' );
	}

	public function testMultiValueConstraintTwo() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q5' ) );
		$statement = new Statement( new PropertyValueSnak( $this->multiPropertyId, new EntityIdValue( new ItemId( 'Q207' ) ) ) );
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( [] ), $entity );
		$this->assertCompliance( $checkResult );
	}

	public function testMultiValueConstraintTwoButOneDeprecated() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q6' ) );
		$statement = new Statement( new PropertyValueSnak( $this->multiPropertyId, new EntityIdValue( new ItemId( 'Q409' ) ) ) );
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( [] ), $entity );
		$this->assertViolation( $checkResult, 'wbqc-violation-message-multi-value' );
	}

	public function testCheckConstraintParameters() {
		$constraint = $this->getConstraintMock( [] );

		$result = $this->checker->checkConstraintParameters( $constraint );

		$this->assertCount( 0, $result );
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
			 ->method( 'getConstraintTypeItemId' )
			 ->will( $this->returnValue( 'Multi value' ) );

		return $mock;
	}

}
