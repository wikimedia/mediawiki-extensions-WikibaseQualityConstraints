<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Result;

use DataValues\DataValue;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Entity\EntityId;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\Metadata;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\Context;
use LogicException;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessage;

/**
 * Used for getting information about the result of a constraint check
 *
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
	 * The constraint is not checked on this kind of snak
	 * (main snak, qualifier or reference),
	 * so the constraint check is skipped.
	 */
	const STATUS_NOT_IN_SCOPE = 'not-in-scope';
	/*
	 * When adding another status, don’t forget to also do the following:
	 * * define messages for it in i18n/:
	 *   * wbqc-constraintreport-status-
	 *   * apihelp-wbcheckconstraints-paramvalue-status-
	 * * declare a color for it in modules/SpecialConstraintReportPage.less
	 * * update $order in DelegatingConstraintChecker::sortResult
	 * * update PARAM_STATUS type in CheckConstraints::getAllowedParams
	 */

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
	 * @var string|ViolationMessage|null
	 */
	private $message;

	/**
	 * @var Metadata
	 */
	private $metadata;

	/**
	 * @param Context $context
	 * @param Constraint $constraint
	 * @param array[] $parameters (string => string[]) parsed constraint parameters
	 * ($constraint->getParameters() contains the unparsed parameters)
	 * @param string $status One of the self::STATUS_… constants
	 * @param string|ViolationMessage|null $message sanitized HTML string or ViolationMessage object
	 */
	public function __construct(
		Context $context,
		Constraint $constraint,
		array $parameters = [],
		$status = self::STATUS_TODO,
		$message = null
	) {
		$this->context = $context;
		$this->constraint = $constraint;
		$this->parameters = $parameters;
		$this->status = $status;
		$this->message = $message;
		$this->metadata = Metadata::blank();
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
	 * @return string|ViolationMessage|null sanitized HTML string or ViolationMessage object
	 */
	public function getMessage() {
		return $this->message;
	}

	/**
	 * @param Metadata $cm
	 * @return self
	 */
	public function withMetadata( Metadata $cm ) {
		$this->metadata = $cm;
		return $this;
	}

	/**
	 * @return Metadata
	 */
	public function getMetadata() {
		return $this->metadata;
	}

}
