<?php

namespace WikibaseQuality\ConstraintReport\Test\RangeChecker;

use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use DataValues\StringValue;
use DataValues\TimeValue;
use DataValues\UnboundedQuantityValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\DiffWithinRangeChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\RangeCheckerHelper;
use WikibaseQuality\ConstraintReport\Tests\ConstraintParameters;
use WikibaseQuality\ConstraintReport\Tests\ResultAssertions;
use WikibaseQuality\Tests\Helper\JsonFileEntityLookup;

/**
 * @covers \WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\DiffWithinRangeChecker
 *
 * @group WikibaseQualityConstraints
 *
 * @uses   \WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 * @uses   \WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class DiffWithinRangeCheckerTest extends \MediaWikiTestCase {

	use ConstraintParameters, ResultAssertions;

	/**
	 * @var JsonFileEntityLookup
	 */
	private $lookup;

	/**
	 * @var TimeValue
	 */
	private $timeValue;

	/**
	 * @var DiffWithinRangeChecker
	 */
	private $checker;

	protected function setUp() {
		parent::setUp();
		$this->lookup = new JsonFileEntityLookup( __DIR__ );
		$this->timeValue = new TimeValue( '+00000001970-01-01T00:00:00Z', 0, 0, 0, 11, 'http://www.wikidata.org/entity/Q1985727' );
		$this->checker = new DiffWithinRangeChecker(
			$this->getConstraintParameterParser(),
			new RangeCheckerHelper( $this->getDefaultConfig() ),
			$this->getConstraintParameterRenderer()
		);
	}

	public function testDiffWithinRangeConstraintWithinRange() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q4' ) );
		$constraintParameters = [
			'property' => 'P569',
			'minimum_quantity' => 0,
			'maximum_quantity' => 150
		];
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P570' ), $this->timeValue ) );

		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertCompliance( $checkResult );
	}

	public function testDiffWithinRangeConstraintTooSmall() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q5' ) );
		$constraintParameters = [
			'property' => 'P569',
			'minimum_quantity' => 50,
			'maximum_quantity' => 150
		];
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P570' ), $this->timeValue ) );

		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertViolation( $checkResult, 'wbqc-violation-message-diff-within-range' );
	}

	public function testDiffWithinRangeConstraintTooBig() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q6' ) );
		$constraintParameters = [
			'property' => 'P569',
			'minimum_quantity' => 0,
			'maximum_quantity' => 150
		];
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P570' ), $this->timeValue ) );

		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertViolation( $checkResult, 'wbqc-violation-message-diff-within-range' );
	}

	public function testDiffWithinRangeConstraintWrongTypeOfProperty() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q7' ) );
		$constraintParameters = [
			'property' => 'P569',
			'minimum_quantity' => 1,
			'maximum_quantity' => 100
		];
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P570' ), $this->timeValue ) );

		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertViolation( $checkResult, 'wbqc-violation-message-diff-within-range-must-have-equal-types' );
	}

	public function testDiffWithinRangeConstraintNoValueSnak() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q4' ) );
		$statement = new Statement( new PropertyNoValueSnak( 1 ) );
		$constraintParameters = [
			'property' => 'P1000',
			'minimum_quantity' => 0,
			'maximum_quantity' => 150
		];
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertViolation( $checkResult, 'wbqc-violation-message-value-needed' );
	}

	public function testDiffWithinRangeConstraintLeftOpenWithinRange() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q4' ) );
		$constraintParameters = array_merge(
			$this->propertyParameter( 'P569' ),
			$this->rangeParameter( 'quantity', null, UnboundedQuantityValue::newFromNumber( 100 ) )
		);
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P570' ), $this->timeValue ) );

		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertCompliance( $checkResult );
	}

	public function testDiffWithinRangeConstraintLeftOpenTooBig() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q4' ) );
		$constraintParameters = array_merge(
			$this->propertyParameter( 'P569' ),
			$this->rangeParameter( 'quantity', null, UnboundedQuantityValue::newFromNumber( 0 ) )
		);
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P570' ), $this->timeValue ) );

		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertViolation( $checkResult, 'wbqc-violation-message-diff-within-range-leftopen' );
	}

	public function testDiffWithinRangeConstraintRightOpenWithinRange() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q4' ) );
		$constraintParameters = array_merge(
			$this->propertyParameter( 'P569' ),
			$this->rangeParameter( 'quantity', UnboundedQuantityValue::newFromNumber( 0 ), null )
		);
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P570' ), $this->timeValue ) );

		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertCompliance( $checkResult );
	}

	public function testDiffWithinRangeConstraintRightOpenTooSmall() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q4' ) );
		$constraintParameters = array_merge(
			$this->propertyParameter( 'P569' ),
			$this->rangeParameter( 'quantity', UnboundedQuantityValue::newFromNumber( 100 ), null )
		);
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P570' ), $this->timeValue ) );

		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertViolation( $checkResult, 'wbqc-violation-message-diff-within-range-rightopen' );
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
			 ->will( $this->returnValue( 'Diff within range' ) );
		$mock->expects( $this->any() )
			 ->method( 'getConstraintTypeName' )
			 ->will( $this->returnValue( 'Diff within range' ) );

		return $mock;
	}

}
