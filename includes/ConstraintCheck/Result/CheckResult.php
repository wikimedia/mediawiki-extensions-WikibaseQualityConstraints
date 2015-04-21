<?php

namespace WikidataQuality\ConstraintReport\ConstraintCheck\Result;

use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Entity\PropertyId;
use DataValues\DataValue;


/**
 * Class CheckResult
 * Used for getting information about the result of a constraint check
 *
 * @package WikidataQuality\ConstraintReport\ConstraintCheck\Result
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class CheckResult implements \WikidataQuality\Result\CheckResult {

	// Constants for statuses
	const STATUS_COMPLIANCE = 'compliance';
	const STATUS_VIOLATION = 'violation';
	const STATUS_EXCEPTION = 'exception';
	const STATUS_TODO = 'todo';

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
	 * Includes arrays of ItemIds or PropertyIds or strings.
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

	public function __construct( Statement $statement, $constraintName, $parameters = array (), $status = 'todo', $message = '' ) {
		$this->statement = $statement;
		$this->constraintName = $constraintName;
		$this->parameters = $parameters;
		$this->status = $status;
		$this->message = $message;
	}

	/**
	 * @return Statement
	 */
	public function getStatement() {
		return $this->statement;
	}

	/**
	 * @return PropertyId
	 */
	public function getPropertyId() {
		return $this->statement->getClaim()->getPropertyId();
	}

	/**
	 * @return DataValue
	 */
	public function getDataValue() {
		return $this->statement->getClaim()->getMainSnak()->getDataValue();
	}

	/**
	 * @return string
	 */
	public function getConstraintName() {
		return $this->constraintName;
	}

	/**
	 * @return array
	 */
	public function getParameters() {
		return $this->parameters;
	}

	/**
	 * @return string
	 */
	public function getStatus() {
		return $this->status;
	}

	/**
	 * @return string
	 */
	public function getMessage() {
		return $this->message;
	}

}