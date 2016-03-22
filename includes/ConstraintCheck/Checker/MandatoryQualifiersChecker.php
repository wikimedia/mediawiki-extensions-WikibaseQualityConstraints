<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Checker;

use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Snak\Snak;
use Wikibase\DataModel\Statement\StatementListProvider;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use Wikibase\DataModel\Statement\Statement;

/**
 * @package WikibaseQuality\ConstraintReport\ConstraintCheck\Checker
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class MandatoryQualifiersChecker implements ConstraintChecker {

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
	 * Checks 'Mandatory qualifiers' constraint.
	 *
	 * @param Statement $statement
	 * @param Constraint $constraint
	 * @param EntityDocument|StatementListProvider $entity
	 *
	 * @return CheckResult
	 */
	public function checkConstraint( Statement $statement, Constraint $constraint, EntityDocument $entity = null ) {
		$parameters = array ();
		$constraintParameters = $constraint->getConstraintParameters();

		$properties = array();
		if ( array_key_exists( 'property', $constraintParameters ) ) {
			$properties = explode( ',', $constraintParameters['property'] );
		}
		$parameters['property'] = $this->helper->parseParameterArray( $properties );
		$qualifiersList = $statement->getQualifiers();
		$qualifiers = array ();

		/** @var Snak $qualifier */
		foreach ( $qualifiersList as $qualifier ) {
			$qualifiers[ $qualifier->getPropertyId()->getSerialization() ] = true;
		}

		$message = '';
		$status = CheckResult::STATUS_COMPLIANCE;

		foreach ( $properties as $property ) {
			if ( !array_key_exists( $property, $qualifiers ) ) {
				$message = wfMessage( "wbqc-violation-message-mandatory-qualifiers" )->escaped();
				$status = CheckResult::STATUS_VIOLATION;
				break;
			}
		}

		return new CheckResult( $statement, $constraint->getConstraintTypeQid(), $parameters, $status, $message );
	}

}
