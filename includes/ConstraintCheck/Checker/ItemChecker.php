<?php

namespace WikidataQuality\ConstraintReport\ConstraintCheck\Checker;

use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\StatementList;
use Wikibase\Lib\Store\EntityLookup;
use WikidataQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikidataQuality\ConstraintReport\ConstraintCheck\Helper\ConnectionCheckerHelper;
use WikidataQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper;
use Wikibase\DataModel\Statement\Statement;
use WikidataQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use Wikibase\DataModel\Entity\Entity;


/**
 * Checks 'Item' constraints.
 *
 * @package WikidataQuality\ConstraintReport\ConstraintCheck\Checker
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class ItemChecker implements ConstraintChecker {

	/**
	 * Wikibase entity lookup.
	 *
	 * @var EntityLookup
	 */
	private $entityLookup;

	/**
	 * Class for helper functions for constraint checkers.
	 *
	 * @var ConstraintReportHelper
	 */
	private $constraintReportHelper;

	/**
	 * @var ConnectionCheckerHelper
	 */
	private $connectionCheckerHelper;

	/**
	 * @param EntityLookup $lookup
	 * @param ConstraintReportHelper $helper
	 * @param ConnectionCheckerHelper $connectionCheckerHelper
	 */
	public function __construct( EntityLookup $lookup, ConstraintReportHelper $helper, ConnectionCheckerHelper $connectionCheckerHelper ) {
		$this->entityLookup = $lookup;
		$this->constraintReportHelper = $helper;
		$this->connectionCheckerHelper = $connectionCheckerHelper;
	}

	/**
	 * Checks 'Item' constraint.
	 *
	 * @param Statement $statement
	 * @param array $constraintParameters
	 * @param Entity $entity
	 *
	 * @return CheckResult
	 */
	public function checkConstraint( Statement $statement, $constraintParameters, Entity $entity = null ) {
		$parameters = array ();

		$parameters['property'] = $this->constraintReportHelper->parseParameterArray( $constraintParameters['property'] );
		$parameters['item'] = $this->constraintReportHelper->parseParameterArray( $constraintParameters['item'] );

		$property = $constraintParameters['property'][0];

		/*
		 * error handling:
		 *   parameter $property must not be null
		 */
		if ( $property === '' ) {
			$message = 'Properties with \'Item\' constraint need a parameter \'property\'.';
			return new CheckResult( $statement, 'Item', $parameters, CheckResult::STATUS_VIOLATION, $message );
		}

		/*
		 * 'Item' can be defined with
		 *   a) a property only
		 *   b) a property and a number of items (each combination of property and item forming an individual claim)
		 */
		if ( $constraintParameters['item'][0] === '' ) {
			if ( $this->connectionCheckerHelper->hasProperty( $entity->getStatements(), $property ) ) {
				$message = '';
				$status = CheckResult::STATUS_COMPLIANCE;
			} else {
				$message = 'This property must only be used when there is another statement using the property defined in the parameters.';
				$status = CheckResult::STATUS_VIOLATION;
			}
		} else {
			if ( $this->connectionCheckerHelper->hasClaim( $entity->getStatements(), $property, $constraintParameters['item'] ) ) {
				$message = '';
				$status = CheckResult::STATUS_COMPLIANCE;
			} else {
				$message = 'This property must only be used when there is another statement using the property with one of the values defined in the parameters.';
				$status = CheckResult::STATUS_VIOLATION;
			}
		}

		return new CheckResult( $statement, 'Item', $parameters, $status, $message );
	}

}