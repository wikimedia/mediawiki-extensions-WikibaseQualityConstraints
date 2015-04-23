<?php

namespace WikidataQuality\ConstraintReport\ConstraintCheck\Checker;

use Wikibase\DataModel\Statement\StatementList;
use WikidataQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper;
use Wikibase\DataModel\Statement\Statement;
use WikidataQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;


/**
 * Class ValueCountChecker.
 * Checks 'Single', 'Multi' and 'Unique value' constraint.
 *
 * @package WikidataQuality\ConstraintReport\ConstraintCheck\Checker
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class ValueCountChecker {

	private $propertyCount;

	/**
	 * List of all statements of given entity.
	 *
	 * @var StatementList
	 */
	private $statements;

	/**
	 * Class for helper functions for constraint checkers.
	 *
	 * @var ConstraintReportHelper
	 */
	private $helper;

	/**
	 * @param StatementList $statements
	 * @param ConstraintReportHelper $helper
	 */
	public function __construct( StatementList $statements, ConstraintReportHelper $helper ) {
		$this->statements = $statements;
		$this->helper = $helper;
	}

	/**
	 * Checks 'Single value' constraint.
	 *
	 * @param Statement $statement
	 *
	 * @return CheckResult
	 */
	public function checkSingleValueConstraint( Statement $statement ) {
		$propertyId = $statement->getClaim()->getPropertyId();

		$parameters = array ();

		$propertyCountArray = $this->getPropertyCount( $this->statements );

		if ( $propertyCountArray[ $propertyId->getNumericId() ] > 1 ) {
			$message = 'This property must only have a single value, that is there must only be one claim using this property.';
			$status = CheckResult::STATUS_VIOLATION;
		} else {
			$message = '';
			$status = CheckResult::STATUS_COMPLIANCE;
		}

		return new CheckResult( $statement, 'Single value', $parameters, $status, $message );
	}

	/**
	 * Checks Multi value constraint
	 *
	 * @param Statement $statement
	 *
	 * @return CheckResult
	 */
	public function checkMultiValueConstraint( Statement $statement ) {
		$propertyId = $statement->getClaim()->getPropertyId();

		$parameters = array ();

		$propertyCountArray = $this->getPropertyCount( $this->statements );

		if ( $propertyCountArray[ $propertyId->getNumericId() ] <= 1 ) {
			$message = 'This property must have a multiple values, that is there must be more than one claim using this property.';
			$status = CheckResult::STATUS_VIOLATION;
		} else {
			$message = '';
			$status = CheckResult::STATUS_COMPLIANCE;
		}

		return new CheckResult( $statement, 'Multi value', $parameters, $status, $message );
	}

	// todo: implement when index exists that makes it possible in real-time
	/**
	 * @param Statement $statement
	 *
	 * @return CheckResult
	 */
	public function checkUniqueValueConstraint( Statement $statement ) {
		$parameters = array ();

		$message = 'For technical reasons, the check for this constraint has not yet been implemented.';
		return new CheckResult( $statement, 'Unique value', $parameters, 'todo', $message );
	}

	private function getPropertyCount( $statements ) {
		if ( !isset( $this->propertyCount ) ) {
			$this->propertyCount = array ();
			foreach ( $statements as $statement ) {
				if ( $statement->getRank() === Statement::RANK_DEPRECATED ) {
					continue;
				}
				if ( array_key_exists( $statement->getPropertyId()->getNumericId(), $this->propertyCount ) ) {
					$this->propertyCount[ $statement->getPropertyId()->getNumericId() ]++;
				} else {
					$this->propertyCount[ $statement->getPropertyId()->getNumericId() ] = 1;
				}
			}
		}
		return $this->propertyCount;
	}

}