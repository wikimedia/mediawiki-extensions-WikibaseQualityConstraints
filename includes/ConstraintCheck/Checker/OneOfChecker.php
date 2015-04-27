<?php

namespace WikidataQuality\ConstraintReport\ConstraintCheck\Checker;

use Wikibase\DataModel\Snak\PropertyValueSnak;
use WikidataQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikidataQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper;
use Wikibase\DataModel\Statement\Statement;
use WikidataQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use Wikibase\DataModel\Entity\Entity;


/**
 * @package WikidataQuality\ConstraintReport\ConstraintCheck\Checker
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class OneOfChecker implements ConstraintChecker {

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
	 * Checks 'One of' constraint.
	 *
	 * @param Statement $statement
	 * @param array $constraintParameters
	 * @param Entity $entity
	 *
	 * @return CheckResult
	 */
	public function checkConstraint( Statement $statement, $constraintParameters, Entity $entity = null ) {
		$parameters = array ();

		$parameters[ 'item' ] = $this->helper->parseParameterArray( $constraintParameters['item'] );

		$mainSnak = $statement->getClaim()->getMainSnak();

		/*
		 * error handling:
		 *   $mainSnak must be PropertyValueSnak, neither PropertySomeValueSnak nor PropertyNoValueSnak is allowed
		 */
		if ( !$mainSnak instanceof PropertyValueSnak ) {
			$message = 'Properties with \'One of\' constraint need to have a value.';
			return new CheckResult( $statement, 'IOne of', $parameters, CheckResult::STATUS_VIOLATION, $message );
		}

		$dataValue = $mainSnak->getDataValue();

		/*
		 * error handling:
		 *   type of $dataValue for properties with 'One of' constraint has to be 'wikibase-entityid'
		 *   parameter $itemArray must not be null
		 */
		if ( $dataValue->getType() !== 'wikibase-entityid' ) {
			$message = 'Properties with \'One of\' constraint need to have values of type \'wikibase-entityid\'.';
			return new CheckResult( $statement, 'Format', $parameters, CheckResult::STATUS_VIOLATION, $message );
		}
		if ( $constraintParameters['item'][ 0 ] === '' ) {
			$message = 'Properties with \'One of\' constraint need a parameter \'item\'.';
			return new CheckResult( $statement, 'One of', $parameters, CheckResult::STATUS_VIOLATION, $message );
		}

		if ( in_array( $dataValue->getEntityId()->getSerialization(), $constraintParameters['item'] ) ) {
			$message = '';
			$status = CheckResult::STATUS_COMPLIANCE;
		} else {
			$message = 'The property\'s value must be one of the items defined in the parameters.';
			$status = CheckResult::STATUS_VIOLATION;
		}

		return new CheckResult( $statement, 'One of', $parameters, $status, $message );
	}

}