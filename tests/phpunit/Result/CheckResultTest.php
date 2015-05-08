<?php

namespace WikidataQuality\ConstraintReport\Test\CheckResult;

use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Claim\Claim;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Entity\PropertyId;
use DataValues\StringValue;
use WikidataQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;


/**
 * @covers WikidataQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class CheckResultTest extends \MediaWikiTestCase {

	private $statement;
	private $constraintName;
	private $parameters;
	private $status;
	private $message;

	protected function setUp() {
		parent::setUp();
		$this->statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P1' ), new StringValue( 'Foo' ) ) ) );
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
		$this->assertEquals( $this->statement->getClaim()->getPropertyId(), $checkResult->getPropertyId() );
		$this->assertEquals( $this->statement->getClaim()->getMainSnak()->getDataValue(), $checkResult->getDataValue() );
		$this->assertEquals( $this->constraintName, $checkResult->getConstraintName() );
		$this->assertEquals( $this->parameters, $checkResult->getParameters() );
		$this->assertEquals( $this->status, $checkResult->getStatus() );
		$this->assertEquals( $this->message, $checkResult->getMessage() );
		$this->assertEquals( 'value', $checkResult->getMainSnakType() );
	}

	public function testWithWrongSnakType() {
		$checkResult = new CheckResult( new Statement( new Claim( new PropertyNoValueSnak( 1 ) ) ), $this->constraintName, $this->parameters, $this->status, $this->message );
		$this->setExpectedException( '\Exception' );
		$checkResult->getDataValue();
	}

}