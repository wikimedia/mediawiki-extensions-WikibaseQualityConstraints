<?php

namespace WikibaseQuality\ConstraintReport\Test\ValueCountChecker;

use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Entity\EntityIdValue;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\UniqueValueChecker;
use WikibaseQuality\ConstraintReport\Tests\ConstraintParameters;
use WikibaseQuality\ConstraintReport\Tests\ResultAssertions;
use WikibaseQuality\ConstraintReport\Tests\SparqlHelperMock;
use WikibaseQuality\Tests\Helper\JsonFileEntityLookup;

/**
 * @covers \WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\UniqueValueChecker
 *
 * @group WikibaseQualityConstraints
 *
 * @uses   \WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 *
 * @author Olga Bode
 * @license GNU GPL v2+
 */
class UniqueValueCheckerTest extends \PHPUnit_Framework_TestCase  {

	use ConstraintParameters, SparqlHelperMock;

	/**
	 * @var JsonFileEntityLookup
	 */
	private $lookup;

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
		$this->lookup = new JsonFileEntityLookup( __DIR__ );
		$this->uniquePropertyId = new PropertyId( 'P31' );

	}

	public function testCheckUniqueValueConstraintInvalid() {
		$statement = new Statement( new PropertyValueSnak( $this->uniquePropertyId, new EntityIdValue( new ItemId( 'Q6' ) ) ) );
		$statement->setGuid( 'Q6$e35707be-4a84-61fe-9b52-623784a316a7' );

		$mock = $this->getSparqlHelperMockFindEntities( $statement, [ new ItemId( 'Q42' ) ] );

		$this->checker = new UniqueValueChecker( $this->getConstraintParameterRenderer(), $mock );

		$entity = $this->lookup->getEntity( new ItemId( 'Q6' ) );

		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( [] ), $entity );
		$this->assertViolation( $checkResult, 'wbqc-violation-message-unique-value' );
	}

	public function testCheckUniqueValueConstraintValid() {
		$statement = new Statement( new PropertyValueSnak( $this->uniquePropertyId, new EntityIdValue( new ItemId( 'Q1' ) ) ) );
		$statement->setGuid( "Q1$56e6a474-4431-fb24-cc15-1d580e467559" );

		$mock = $this->getSparqlHelperMockFindEntities( $statement, [] );

		$this->checker = new UniqueValueChecker( $this->getConstraintParameterRenderer(), $mock );

		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );

		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( [] ), $entity );
		$this->assertCompliance( $checkResult );
	}

	public function testCheckUniqueValueWithoutSparql() {
		$statement = new Statement( new PropertyValueSnak( $this->uniquePropertyId, new EntityIdValue( new ItemId( 'Q1' ) ) ) );
		$statement->setGuid( "Q1$56e6a474-4431-fb24-cc15-1d580e467559" );

		$this->checker = new UniqueValueChecker( $this->getConstraintParameterRenderer(), null );

		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );

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
			 ->method( 'getConstraintTypeItemId' )
			 ->will( $this->returnValue( 'Unique value' ) );

		return $mock;
	}

}
