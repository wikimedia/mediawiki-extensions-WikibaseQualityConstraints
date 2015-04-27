<?php

namespace WikidataQuality\ConstraintReport\ConstraintCheck\Checker;

use WikidataQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikidataQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper;
use Wikibase\DataModel\Statement\Statement;
use WikidataQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use Wikibase\DataModel\Entity\Entity;


/**
 * Checks 'Qualifiers' constraint.
 *
 * @package WikidataQuality\ConstraintReport\ConstraintCheck\Checker
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class QualifiersChecker implements ConstraintChecker {

	/**
	 * Class for helper functions for constraint checkers.
	 *
	 * @var ConstraintReportHelper
	 */
	private $helper;

	/**
	 * @param ConstraintReportHelper $helper
	 */
	public function __construct( ConstraintReportHelper $helper ) {
		$this->helper = $helper;
	}

	/**
	 * Checks 'Qualifiers' constraint.
	 *
	 * @param Statement $statement
	 * @param array $constraintParameters
	 * @param Entity $entity
	 *
	 * @return CheckResult
	 */
	public function checkConstraint( Statement $statement, $constraintParameters, Entity $entity = null ) {
		$parameters = array ();

		$parameters[ 'property' ] = $this->helper->parseParameterArray( $constraintParameters['property'] );

		/*
		 * error handling:
		 *  $constraintParameters['property'] can be array( '' ), meaning that there are explicitly no qualifiers allowed
		 */

		$message = '';
		$status = CheckResult::STATUS_COMPLIANCE;

		foreach ( $statement->getQualifiers() as $qualifier ) {
			$pid = $qualifier->getPropertyId()->getSerialization();
			if ( !in_array( $pid, $constraintParameters['property'] ) ) {
				$message = 'The property must only be used with (no other than) the qualifiers defined in the parameters.';
				$status = CheckResult::STATUS_VIOLATION;
				break;
			}
		}

		return new CheckResult( $statement, 'Qualifiers', $parameters, $status, $message );
	}
}