<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Checker;

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
class QualifiersChecker implements ConstraintChecker {

	/**
	 * @var ConstraintParameterParser
	 */
	private $helper;

	/**
	 * @param ConstraintParameterParser $helper
	 */
	public function __construct( ConstraintParameterParser $helper ) {
		$this->helper = $helper;
	}

	/**
	 * Checks 'Qualifiers' constraint.
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

		if ( array_key_exists( 'constraint_status', $constraintParameters ) ) {
			$parameters['constraint_status'] = $this->helper->parseSingleParameter( $constraintParameters['constraint_status'], true );
		}

		$parameters['property'] = $this->helper->parseParameterArray( explode( ',', $constraintParameters['property'] ) );

		/*
		 * error handling:
		 *  $constraintParameters['property'] can be array( '' ), meaning that there are explicitly no qualifiers allowed
		 */

		$message = '';
		$status = CheckResult::STATUS_COMPLIANCE;

		foreach ( $statement->getQualifiers() as $qualifier ) {
			$pid = $qualifier->getPropertyId()->getSerialization();
			if ( !in_array( $pid, explode( ',', $constraintParameters['property'] ) ) ) {
				$message = wfMessage( "wbqc-violation-message-qualifiers" )->escaped();
				$status = CheckResult::STATUS_VIOLATION;
				break;
			}
		}

		return new CheckResult( $statement, $constraint->getConstraintTypeQid(), $parameters, $status, $message );
	}

}