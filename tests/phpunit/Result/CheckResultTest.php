<?php

namespace WikibaseQuality\ConstraintReport\Test\CheckResult;

use LogicException;
use PHPUnit_Framework_TestCase;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use DataValues\StringValue;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;

/**
 * @covers \WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 *
 * @group WikibaseQualityConstraints
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class CheckResultTest extends PHPUnit_Framework_TestCase {

	/**
	 * @var EntityId
	 */
	private $entityId;

	/**
	 * @var Statement
	 */
	private $statement;

	/**
	 * @var string
	 */
	private $constraintName;

	/**
	 * @var string
	 */
	private $constraintId;

	/**
	 * @var array
	 */
	private $parameters;

	/**
	 * @var Constraint
	 */
	private $constraint;

	/**
	 * @var string
	 */
	private $status;

	/**
	 * @var string
	 */
	private $message;

	protected function setUp() {
		parent::setUp();
		$this->entityId = new ItemId( 'Q1' );
		$this->statement = new Statement( new PropertyValueSnak( new PropertyId( 'P1' ), new StringValue( 'Foo' ) ) );
		$this->constraintName = 'Range';
		$this->constraintId = '1';
		$this->parameters = [];
		$this->constraint = new Constraint( $this->constraintId, new PropertyId( 'P1' ), $this->constraintName, $this->parameters );
		$this->status = 'compliance';
		$this->message = 'All right';
	}

	public function testConstructAndGetters() {
		$checkResult = new CheckResult(
			$this->entityId, $this->statement, $this->constraint,
			$this->parameters, $this->status, $this->message
		);
		$this->assertEquals( $this->entityId, $checkResult->getEntityId() );
		$this->assertEquals( $this->statement, $checkResult->getStatement() );
		$this->assertEquals( $this->statement->getPropertyId(), $checkResult->getPropertyId() );
		$this->assertEquals( $this->statement->getMainSnak()->getDataValue(), $checkResult->getDataValue() );
		$this->assertEquals( $this->constraint, $checkResult->getConstraint() );
		$this->assertEquals( $this->constraintName, $checkResult->getConstraintName() );
		$this->assertEquals( $this->constraintId, $checkResult->getConstraintId() );
		$this->assertEquals( $this->parameters, $checkResult->getParameters() );
		$this->assertEquals( $this->status, $checkResult->getStatus() );
		$this->assertEquals( $this->message, $checkResult->getMessage() );
		$this->assertEquals( 'value', $checkResult->getMainSnakType() );
	}

	public function testWithWrongSnakType() {
		$checkResult = new CheckResult(
			$this->entityId, new Statement( new PropertyNoValueSnak( 1 ) ), $this->constraint,
			$this->parameters, $this->status, $this->message
		);
		$this->setExpectedException( LogicException::class );
		$checkResult->getDataValue();
	}

}
