<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Checker;

use Wikibase\DataModel\Snak\PropertyValueSnak;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Entity\Entity;


/**
 * @package WikibaseQuality\ConstraintReport\ConstraintCheck\Checker
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class FormatChecker implements ConstraintChecker {

	/**
	 * @var ConstraintParameterParser
	 */
	private $helper;

	/**
	 * @param ConstraintParameterParser $helper
	 */
	public function __construct( $helper ) {
		$this->helper = $helper;
	}

	/**
	 * Checks 'Format' constraint.
	 *
	 * @param Statement $statement
	 * @param Constraint $constraint
	 * @param Entity $entity
	 *
	 * @return CheckResult
	 */
	public function checkConstraint( Statement $statement, Constraint $constraint, Entity $entity = null ) {
		$parameters = array ();
		$constraintParameters = $constraint->getConstraintParameters();

		if( array_key_exists( 'pattern', $constraintParameters ) ) {
			$pattern = $constraintParameters['pattern'];
			$parameters['pattern'] = $this->helper->parseSingleParameter( $pattern, true );
		} else {
			$message = wfMessage( "wbqc-violation-message-parameter-needed" )->params( $constraint->getConstraintTypeName(), 'pattern' )->escaped();
			return new CheckResult( $statement, $constraint->getConstraintTypeQid(), $parameters, CheckResult::STATUS_VIOLATION, $message );
		}

		if ( array_key_exists( 'constraint_status', $constraintParameters ) ) {
			$parameters['constraint_status'] = $this->helper->parseSingleParameter( $constraintParameters['constraint_status'], true );
		}

		$mainSnak = $statement->getMainSnak();

		/*
		 * error handling:
		 *   $mainSnak must be PropertyValueSnak, neither PropertySomeValueSnak nor PropertyNoValueSnak is allowed
		 */
		if ( !$mainSnak instanceof PropertyValueSnak ) {
			$message = wfMessage( "wbqc-violation-message-value-needed" )->params( $constraint->getConstraintTypeName() )->escaped();
			return new CheckResult( $statement, $constraint->getConstraintTypeQid(), $parameters, CheckResult::STATUS_VIOLATION, $message );
		}

		$dataValue = $mainSnak->getDataValue();

		/*
		 * error handling:
		 *   type of $dataValue for properties with 'Format' constraint has to be 'string'
		 *   parameter $pattern must not be null
		 */
		if ( $dataValue->getType() !== 'string' ) {
			$message = wfMessage( "wbqc-violation-message-value-needed-of-type" )->params( $constraint->getConstraintTypeName(), 'string' )->escaped();
			return new CheckResult( $statement, $constraint->getConstraintTypeQid(), $parameters, CheckResult::STATUS_VIOLATION, $message );
		}

		$message = wfMessage( 'wbqc-violation-message-security-reason' )->params( $constraint->getConstraintTypeName(), 'string' )->escaped();
		return new CheckResult( $statement, $constraint->getConstraintTypeQid(), $parameters, CheckResult::STATUS_TODO, $message );
	}

}