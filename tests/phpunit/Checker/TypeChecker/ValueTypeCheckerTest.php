<?php

namespace WikibaseQuality\ConstraintReport\Test\TypeChecker;

use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use DataValues\StringValue;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\ValueTypeChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\TypeCheckerHelper;
use WikibaseQuality\ConstraintReport\Tests\DefaultConfig;
use WikibaseQuality\Tests\Helper\JsonFileEntityLookup;

/**
 * @covers \WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\ValueTypeChecker
 *
 * @group WikibaseQualityConstraints
 *
 * @uses   \WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 * @uses   \WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class ValueTypeCheckerTest extends \MediaWikiTestCase {

	use DefaultConfig;

	/**
	 * @var JsonFileEntityLookup
	 */
	private $lookup;

	/**
	 * @var ValueTypeChecker
	 */
	private $checker;

	/**
	 * @var PropertyId
	 */
	private $valueTypePropertyId;

	protected function setUp() {
		parent::setUp();
		$this->lookup = new JsonFileEntityLookup( __DIR__ );
		$this->checker = new ValueTypeChecker(
			$this->lookup,
			new ConstraintParameterParser(),
			new TypeCheckerHelper( $this->lookup, $this->getDefaultConfig() ),
			$this->getDefaultConfig()
		);
		$this->valueTypePropertyId = new PropertyId( 'P1234' );
	}

	protected function tearDown() {
		unset( $this->lookup );
		unset( $this->valueTypePropertyId );
		unset( $this->valueTypeTypeChecker );
		parent::tearDown();
	}

	// relation 'instance'
	public function testValueTypeConstraintInstanceValid() {
		$statement = new Statement( new PropertyValueSnak( $this->valueTypePropertyId, new EntityIdValue( new ItemId( 'Q1' ) ) ) );
		$constraintParameters = array(
			'relation' => 'instance',
			'class' => 'Q100,Q101'
		);
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $this->getEntity() );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testValueTypeConstraintInstanceValidWithIndirection() {
		$statement = new Statement( new PropertyValueSnak( $this->valueTypePropertyId, new EntityIdValue( new ItemId( 'Q2' ) ) ) );
		$constraintParameters = array(
			'relation' => 'instance',
			'class' => 'Q100,Q101'
		);
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $this->getEntity() );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testValueTypeConstraintInstanceValidWithMoreIndirection() {
		$statement = new Statement( new PropertyValueSnak( $this->valueTypePropertyId, new EntityIdValue( new ItemId( 'Q3' ) ) ) );
		$constraintParameters = array(
			'relation' => 'instance',
			'class' => 'Q100,Q101'
		);
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $this->getEntity() );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	// relation 'subclass'
	public function testValueTypeConstraintSubclassValid() {
		$statement = new Statement( new PropertyValueSnak( $this->valueTypePropertyId, new EntityIdValue( new ItemId( 'Q4' ) ) ) );
		$constraintParameters = array(
			'relation' => 'subclass',
			'class' => 'Q100,Q101'
		);
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $this->getEntity() );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testValueTypeConstraintSubclassValidWithIndirection() {
		$statement = new Statement( new PropertyValueSnak( $this->valueTypePropertyId, new EntityIdValue( new ItemId( 'Q5' ) ) ) );
		$constraintParameters = array(
			'relation' => 'subclass',
			'class' => 'Q100,Q101'
		);
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $this->getEntity() );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testValueTypeConstraintSubclassValidWithMoreIndirection() {
		$statement = new Statement( new PropertyValueSnak( $this->valueTypePropertyId, new EntityIdValue( new ItemId( 'Q6' ) ) ) );
		$constraintParameters = array(
			'relation' => 'subclass',
			'class' => 'Q100,Q101'
		);
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $this->getEntity() );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	// relation 'instance', violations
	public function testValueTypeConstraintInstanceInvalid() {
		$statement = new Statement( new PropertyValueSnak( $this->valueTypePropertyId, new EntityIdValue( new ItemId( 'Q1' ) ) ) );
		$constraintParameters = array(
			'relation' => 'instance',
			'class' => 'Q200,Q201'
		);
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $this->getEntity() );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testValueTypeConstraintInstanceInvalidWithIndirection() {
		$statement = new Statement( new PropertyValueSnak( $this->valueTypePropertyId, new EntityIdValue( new ItemId( 'Q2' ) ) ) );
		$constraintParameters = array(
			'relation' => 'instance',
			'class' => 'Q200,Q201'
		);
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $this->getEntity() );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testValueTypeConstraintInstanceInvalidWithMoreIndirection() {
		$statement = new Statement( new PropertyValueSnak( $this->valueTypePropertyId, new EntityIdValue( new ItemId( 'Q3' ) ) ) );
		$constraintParameters = array(
			'relation' => 'instance',
			'class' => 'Q200,Q201'
		);
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $this->getEntity() );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	// relation 'subclass', violations
	public function testValueTypeConstraintSubclassInvalid() {
		$statement = new Statement( new PropertyValueSnak( $this->valueTypePropertyId, new EntityIdValue( new ItemId( 'Q4' ) ) ) );
		$constraintParameters = array(
			'relation' => 'subclass',
			'class' => 'Q200,Q201'
		);
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $this->getEntity() );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testValueTypeConstraintSubclassInvalidWithIndirection() {
		$statement = new Statement( new PropertyValueSnak( $this->valueTypePropertyId, new EntityIdValue( new ItemId( 'Q5' ) ) ) );
		$constraintParameters = array(
			'relation' => 'subclass',
			'class' => 'Q200,Q201'
		);
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $this->getEntity() );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testValueTypeConstraintSubclassInvalidWithMoreIndirection() {
		$statement = new Statement( new PropertyValueSnak( $this->valueTypePropertyId, new EntityIdValue( new ItemId( 'Q6' ) ) ) );
		$constraintParameters = array(
			'relation' => 'subclass',
			'class' => 'Q200,Q201'
		);
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $this->getEntity() );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	// edge cases
	public function testValueTypeConstraintMissingRelation() {
		$statement = new Statement( new PropertyValueSnak( $this->valueTypePropertyId, new EntityIdValue( new ItemId( 'Q1' ) ) ) );
		$constraintParameters = array(
			'class' => 'Q100,Q101'
		);
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $this->getEntity() );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testValueTypeConstraintMissingClass() {
		$statement = new Statement( new PropertyValueSnak( $this->valueTypePropertyId, new EntityIdValue( new ItemId( 'Q1' ) ) ) );
		$constraintParameters = array(
			'relation' => 'instance'
		);
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $this->getEntity() );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testValueTypeConstraintWrongType() {
		$statement = new Statement( new PropertyValueSnak( $this->valueTypePropertyId, new StringValue( 'foo bar baz' ) ) );
		$constraintParameters = array(
			'relation' => 'instance',
			'class' => 'Q100,Q101'
		);
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $this->getEntity() );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testValueTypeConstraintNonExistingValue() {
		$statement = new Statement( new PropertyValueSnak( $this->valueTypePropertyId, new EntityIdValue( new ItemId( 'Q100' ) ) ) );
		$constraintParameters = array(
			'relation' => 'instance',
			'class' => 'Q100,Q101'
		);
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $this->getEntity() );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testValueTypeConstraintNoValueSnak() {
		$statement = new Statement( new PropertyNoValueSnak( 1 ) );
		$constraintParameters = array(
			'relation' => 'instance',
			'class' => 'Q100,Q101'
		);
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $this->getEntity() );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
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
			 ->method( 'getConstraintTypeQid' )
			 ->will( $this->returnValue( 'Value type' ) );

		return $mock;
	}

	/**
	 * @return EntityDocument
	 */
	private function getEntity() {
		return new Item( new ItemId( 'Q1' ) );
	}

}
