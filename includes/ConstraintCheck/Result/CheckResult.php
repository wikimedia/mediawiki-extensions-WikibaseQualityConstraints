<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Result;

use DataValues\DataValue;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\PropertyId;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\Context;
use LogicException;

/**
 * Used for getting information about the result of a constraint check
 *
 * @package WikibaseQuality\ConstraintReport\ConstraintCheck\Result
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class CheckResult {

	// Constants for statuses
	/**
	 * The statement satisfies the constraint.
	 */
	const STATUS_COMPLIANCE = 'compliance';
	/**
	 * The statement violates the constraint.
	 */
	const STATUS_VIOLATION = 'violation';
	/**
	 * The subject entity of the statement is a known exception to the constraint.
	 */
	const STATUS_EXCEPTION = 'exception';
	/**
	 * The constraint is not implemented.
	 */
	const STATUS_TODO = 'todo';
	/**
	 * The constraint parameters are broken.
	 */
	const STATUS_BAD_PARAMETERS = 'bad-parameters';
	/**
	 * The constraint has not been checked because the statement is deprecated.
	 */
	const STATUS_DEPRECATED = 'deprecated';
	/**
	 * The statement violates the constraint, but the constraint is not mandatory.
	 *
	 * DelegatingConstraintChecker downgrades violations to warnings automatically based on the constraint parameters;
	 * constraint checkers should not assign this status directly.
	 */
	const STATUS_WARNING = 'warning';
	/**
	 * The constraint type is only checked on statements,
	 * but the current context is not a statement context,
	 * so the constraint check is skipped.
	 */
	const STATUS_NOSTATEMENT = 'no-statement';

	/**
	 * @var Constraint
	 */
	private $constraint;

	/**
	 * @var Context
	 */
	private $context;

	/**
	 * @var array[]
	 * Includes arrays of ItemIds or PropertyIds or strings.
	 */
	private $parameters;

	/**
	 * @var string One of the self::STATUS_… constants
	 */
	private $status;

	/**
	 * @var string
	 */
	private $message;

	/**
	 * @param Context $context
	 * @param Constraint $constraint
	 * @param array[] $parameters (string => string[]) parsed constraint parameters
	 * ($constraint->getParameters() contains the unparsed parameters)
	 * @param string $status One of the self::STATUS_… constants
	 * @param string $message (sanitized HTML)
	 */
	public function __construct(
		Context $context,
		Constraint $constraint,
		array $parameters = [],
		$status = self::STATUS_TODO,
		$message = ''
	) {
		$this->context = $context;
		$this->constraint = $constraint;
		$this->parameters = $parameters;
		$this->status = $status;
		$this->message = $message;
	}

	/**
	 * @return Context
	 */
	public function getContext() {
		return $this->context;
	}

	/**
	 * @return EntityId
	 */
	public function getEntityId() {
		return $this->context->getEntity()->getId();
	}

	/**
	 * @return string
	 */
	public function getSnakType() {
		return $this->context->getSnak()->getType();
	}

	/**
	 * @return DataValue
	 * @throws LogicException
	 */
	public function getDataValue() {
		$mainSnak = $this->context->getSnak();

		if ( $mainSnak instanceof PropertyValueSnak ) {
			return $mainSnak->getDataValue();
		}

		throw new LogicException( 'Cannot get DataValue, Snak is of type ' . $this->getSnakType() . '.' );
	}

	/**
	 * @return Constraint
	 */
	public function getConstraint() {
		return $this->constraint;
	}

	/**
	 * @return string
	 */
	public function getConstraintId() {
		return $this->constraint->getConstraintId();
	}

	/**
	 * @return array[]
	 */
	public function getParameters() {
		return $this->parameters;
	}

	/**
	 * @param string $key
	 * @param string $value
	 */
	public function addParameter( $key, $value ) {
		$this->parameters[$key][] = $value;
	}

	/**
	 * @return string One of the self::STATUS_… constants
	 */
	public function getStatus() {
		return $this->status;
	}

	/**
	 * @param string $status
	 */
	public function setStatus( $status ) {
		$this->status = $status;
	}

	/**
	 * @return string (sanitized HTML)
	 */
	public function getMessage() {
		return $this->message;
	}

}
