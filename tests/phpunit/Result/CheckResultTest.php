<?php

namespace WikibaseQuality\ConstraintReport\Test\CheckResult;

use LogicException;
use PHPUnit_Framework_TestCase;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Entity\PropertyId;
use DataValues\StringValue;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;

/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 *
 * @group WikibaseQualityConstraints
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class CheckResultTest extends PHPUnit_Framework_TestCase {

	/**
	 * @var Statement
	 */
	private $statement;

	/**
	 * @var string
	 */
	private $constraintName;

	/**
	 * @var array
	 */
	private $parameters;

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
		$this->statement = new Statement( new PropertyValueSnak( new PropertyId( 'P1' ), new StringValue( 'Foo' ) ) );
		$this->constraintName = 'Range';
		$this->parameters = array ();
		$this->status = 'compliance';
		$this->message = 'All right';
	}

	protected function tearDown() {
		parent::tearDown();
		unset( $this->statement );
		unset( $this->constraintName );
		unset( $this->parameters );
		unset( $this->status );
		unset( $this->message );
	}

	public function testConstructAndGetters() {
		$checkResult = new CheckResult( $this->statement, $this->constraintName, $this->parameters, $this->status, $this->message );
		$this->assertEquals( $this->statement, $checkResult->getStatement() );
		$this->assertEquals( $this->statement->getPropertyId(), $checkResult->getPropertyId() );
		$this->assertEquals( $this->statement->getMainSnak()->getDataValue(), $checkResult->getDataValue() );
		$this->assertEquals( $this->constraintName, $checkResult->getConstraintName() );
		$this->assertEquals( $this->parameters, $checkResult->getParameters() );
		$this->assertEquals( $this->status, $checkResult->getStatus() );
		$this->assertEquals( $this->message, $checkResult->getMessage() );
		$this->assertEquals( 'value', $checkResult->getMainSnakType() );
	}

	public function testWithWrongSnakType() {
		$checkResult = new CheckResult( new Statement( new PropertyNoValueSnak( 1 ) ), $this->constraintName, $this->parameters, $this->status, $this->message );
		$this->setExpectedException( LogicException::class );
		$checkResult->getDataValue();
	}

}
