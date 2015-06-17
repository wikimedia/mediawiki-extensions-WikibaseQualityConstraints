<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Result;

use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Entity\PropertyId;


/**
 * Class CheckResult
 * Used for getting information about the result of a constraint check
 *
 * @package WikibaseQuality\ConstraintReport\ConstraintCheck\Result
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class CheckResult {

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

	public function __construct( Statement $statement, $constraintName, $parameters = array(), $status = self::STATUS_TODO, $message = '' ) {
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
		return $this->statement->getPropertyId();
	}

	/**
	 * @return string
	 */
	public function getMainSnakType() {
		return $this->statement->getMainSnak()->getType();
	}

	/**
	 * @return mixed
	 * @throws \Exception
	 */
	public function getDataValue() {
		if ( !$this->statement->getMainSnak() instanceof PropertyValueSnak ) {
			throw new \Exception( 'Cannot get DataValue, MainSnak is of type ' . $this->getMainSnakType() . '.' );
		}

		return $this->statement->getMainSnak()->getDataValue();
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