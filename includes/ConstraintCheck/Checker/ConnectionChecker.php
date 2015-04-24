<?php

namespace WikidataQuality\ConstraintReport\ConstraintCheck\Checker;

use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\StatementList;
use Wikibase\Lib\Store\EntityLookup;
use WikidataQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper;
use Wikibase\DataModel\Statement\Statement;
use WikidataQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;


/**
 * Class ConnectionChecker.
 * Checks 'Conflicts with', 'Item', 'Target required claim', 'Symmetric' and 'Inverse' constraints.
 *
 * @package WikidataQuality\ConstraintReport\ConstraintCheck\Checker
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class ConnectionChecker {

	/**
	 * List of all statements of given entity.
	 *
	 * @var StatementList
	 */
	private $statements;

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
	private $helper;

	/**
	 * @param StatementList $statements
	 * @param EntityLookup $lookup
	 * @param ConstraintReportHelper $helper
	 */
	public function __construct( StatementList $statements, EntityLookup $lookup, ConstraintReportHelper $helper ) {
		$this->statements = $statements;
		$this->entityLookup = $lookup;
		$this->helper = $helper;
	}

	/**
	 * Checks 'Conflicts with' constraint.
	 *
	 * @param Statement $statement
	 * @param string $property
	 * @param array $itemArray
	 *
	 * @return CheckResult
	 */
	public function checkConflictsWithConstraint( Statement $statement, $property, $itemArray ) {
		$parameters = array ();

		$parameters[ 'property' ] = $this->helper->parseSingleParameter( $property, 'PropertyId' );
		$parameters[ 'item' ] = $this->helper->parseParameterArray( $itemArray, 'ItemId' );

		/*
		 * error handling:
		 *   parameter $property must not be null
		 */
		if ( $property === null ) {
			$message = 'Properties with \'Conflicts with\' constraint need a parameter \'property\'.';
			return new CheckResult( $statement, 'Conflicts with', $parameters, CheckResult::STATUS_VIOLATION, $message );
		}

		/*
		 * 'Conflicts with' can be defined with
		 *   a) a property only
		 *   b) a property and a number of items (each combination of property and item forming an individual claim)
		 */
		if ( $itemArray[ 0 ] === '' ) {
			if ( $this->hasProperty( $this->statements, $property ) ) {
				$message = 'This property must not be used when there is another statement using the property defined in the parameters.';
				$status = CheckResult::STATUS_VIOLATION;
			} else {
				$message = '';
				$status = CheckResult::STATUS_COMPLIANCE;
			}
		} else {
			if ( $this->hasClaim( $this->statements, $property, $itemArray ) ) {
				$message = 'This property must not be used when there is another statement using the property with one of the values defined in the parameters.';
				$status = CheckResult::STATUS_VIOLATION;
			} else {
				$message = '';
				$status = CheckResult::STATUS_COMPLIANCE;
			}
		}

		return new CheckResult( $statement, 'Conflicts with', $parameters, $status, $message );
	}

	/**
	 * Checks 'Item' constraint.
	 *
	 * @param Statement $statement
	 * @param string $property
	 * @param array $itemArray
	 *
	 * @return CheckResult
	 */
	public function checkItemConstraint( Statement $statement, $property, $itemArray ) {
		$parameters = array ();

		$parameters[ 'property' ] = $this->helper->parseSingleParameter( $property, 'PropertyId' );
		$parameters[ 'item' ] = $this->helper->parseParameterArray( $itemArray, 'ItemId' );

		/*
		 * error handling:
		 *   parameter $property must not be null
		 */
		if ( $property === null ) {
			$message = 'Properties with \'Item\' constraint need a parameter \'property\'.';
			return new CheckResult( $statement, 'Item', $parameters, CheckResult::STATUS_VIOLATION, $message );
		}

		/*
		 * 'Item' can be defined with
		 *   a) a property only
		 *   b) a property and a number of items (each combination of property and item forming an individual claim)
		 */
		if ( $itemArray[ 0 ] === '' ) {
			if ( $this->hasProperty( $this->statements, $property ) ) {
				$message = '';
				$status = CheckResult::STATUS_COMPLIANCE;
			} else {
				$message = 'This property must only be used when there is another statement using the property defined in the parameters.';
				$status = CheckResult::STATUS_VIOLATION;
			}
		} else {
			if ( $this->hasClaim( $this->statements, $property, $itemArray ) ) {
				$message = '';
				$status = CheckResult::STATUS_COMPLIANCE;
			} else {
				$message = 'This property must only be used when there is another statement using the property with one of the values defined in the parameters.';
				$status = CheckResult::STATUS_VIOLATION;
			}
		}

		return new CheckResult( $statement, 'Item', $parameters, $status, $message );
	}

	/**
	 * Checks 'Target required claim' constraint.
	 *
	 * @param Statement $statement
	 * @param string $property
	 * @param array $itemArray
	 *
	 * @return CheckResult
	 */
	public function checkTargetRequiredClaimConstraint( Statement $statement, $property, $itemArray ) {
		$parameters = array ();

		$parameters[ 'property' ] = $this->helper->parseSingleParameter( $property, 'PropertyId' );
		$parameters[ 'item' ] = $this->helper->parseParameterArray( $itemArray, 'ItemId' );

		$mainSnak = $statement->getClaim()->getMainSnak();

		/*
		 * error handling:
		 *   $mainSnak must be PropertyValueSnak, neither PropertySomeValueSnak nor PropertyNoValueSnak is allowed
		 */
		if ( !$mainSnak instanceof PropertyValueSnak ) {
			$message = 'Properties with \'Target required claim\' constraint need to have a value.';
			return new CheckResult( $statement, 'Target required claim', $parameters, CheckResult::STATUS_VIOLATION, $message );
		}

		$dataValue = $mainSnak->getDataValue();

		/*
		 * error handling:
		 *   type of $dataValue for properties with 'Target required claim' constraint has to be 'wikibase-entityid'
		 *   parameter $property must not be null
		 */
		if ( $dataValue->getType() !== 'wikibase-entityid' ) {
			$message = 'Properties with \'Target required claim\' constraint need to have values of type \'wikibase-entityid\'.';
			return new CheckResult( $statement, 'Target required claim', $parameters, CheckResult::STATUS_VIOLATION, $message );
		}
		if ( $property === null ) {
			$message = 'Properties with \'Target required claim\' constraint need a parameter \'property\'.';
			return new CheckResult( $statement, 'Target required claim', $parameters, CheckResult::STATUS_VIOLATION, $message );
		}

		$targetEntity = $this->entityLookup->getEntity( $dataValue->getEntityId() );
		if ( $targetEntity === null ) {
			$message = 'Target entity does not exist.';
			return new CheckResult( $statement, 'Target required claim', $parameters, CheckResult::STATUS_VIOLATION, $message );
		}
		$targetEntityStatementList = $targetEntity->getStatements();

		/*
		 * 'Target required claim' can be defined with
		 *   a) a property only
		 *   b) a property and a number of items (each combination forming an individual claim)
		 */
		if ( $itemArray[ 0 ] === '' ) {
			if ( $this->hasProperty( $targetEntityStatementList, $property ) ) {
				$message = '';
				$status = CheckResult::STATUS_COMPLIANCE;
			} else {
				$message = 'This property must only be used when there is a statement on its value entity using the property defined in the parameters.';
				$status = CheckResult::STATUS_VIOLATION;
			}
		} else {
			if ( $this->hasClaim( $targetEntityStatementList, $property, $itemArray ) ) {
				$message = '';
				$status = CheckResult::STATUS_COMPLIANCE;
			} else {
				$message = 'This property must only be used when there is a statement on its value entity using the property with one of the values defined in the parameters.';
				$status = CheckResult::STATUS_VIOLATION;
			}
		}

		return new CheckResult( $statement, 'Target required claim', $parameters, $status, $message );
	}

	/**
	 * Checks 'Symmetric' constraint.
	 *
	 * @param Statement $statement
	 * @param string $entityIdSerialization
	 *
	 * @return CheckResult
	 */
	public function checkSymmetricConstraint( Statement $statement, $entityIdSerialization ) {
		$parameters = array ();

		$mainSnak = $statement->getClaim()->getMainSnak();
		$propertyId = $statement->getClaim()->getPropertyId();

		/*
		 * error handling:
		 *   $mainSnak must be PropertyValueSnak, neither PropertySomeValueSnak nor PropertyNoValueSnak is allowed
		 */
		if ( !$mainSnak instanceof PropertyValueSnak ) {
			$message = 'Properties with \'Symmetric\' constraint need to have a value.';
			return new CheckResult( $statement, 'Symmetric', $parameters, CheckResult::STATUS_VIOLATION, $message );
		}

		$dataValue = $mainSnak->getDataValue();

		/*
		 * error handling:
		 *   type of $dataValue for properties with 'Symmetric' constraint has to be 'wikibase-entityid'
		 */
		if ( $dataValue->getType() !== 'wikibase-entityid' ) {
			$message = 'Properties with \'Symmetric\' constraint need to have values of type \'wikibase-entityid\'.';
			return new CheckResult( $statement, 'Symmetric', $parameters, CheckResult::STATUS_VIOLATION, $message );
		}

		$targetItem = $this->entityLookup->getEntity( $dataValue->getEntityId() );
		if ( $targetItem === null ) {
			$message = 'Target item does not exist.';
			return new CheckResult( $statement, 'Symmetric', $parameters, CheckResult::STATUS_VIOLATION, $message );
		}
		$targetItemStatementList = $targetItem->getStatements();

		if ( $this->hasClaim( $targetItemStatementList, $propertyId->getSerialization(), $entityIdSerialization ) ) {
			$message = '';
			$status = CheckResult::STATUS_COMPLIANCE;
		} else {
			$message = 'This property must only be used when there is a statement on its value entity with the same property and this item as its value.';
			$status = CheckResult::STATUS_VIOLATION;
		}

		return new CheckResult( $statement, 'Symmetric', $parameters, $status, $message );
	}

	/**
	 * Checks 'Inverse' constraint.
	 *
	 * @param Statement $statement
	 * @param string $entityIdSerialization
	 * @param string $property
	 *
	 * @return CheckResult
	 */
	public function checkInverseConstraint( Statement $statement, $entityIdSerialization, $property ) {
		$parameters = array ();

		$parameters[ 'property' ] = $this->helper->parseSingleParameter( $property, 'PropertyId' );

		$mainSnak = $statement->getClaim()->getMainSnak();

		/*
		 * error handling:
		 *   $mainSnak must be PropertyValueSnak, neither PropertySomeValueSnak nor PropertyNoValueSnak is allowed
		 */
		if ( !$mainSnak instanceof PropertyValueSnak ) {
			$message = 'Properties with \'Commons link\' constraint need to have a value.';
			return new CheckResult( $statement, 'Commons link', $parameters, CheckResult::STATUS_VIOLATION, $message );
		}

		$dataValue = $mainSnak->getDataValue();

		/*
		 * error handling:
		 *   type of $dataValue for properties with 'Inverse' constraint has to be 'wikibase-entityid'
		 *   parameter $property must not be null
		 */
		if ( $dataValue->getType() !== 'wikibase-entityid' ) {
			$message = 'Properties with \'Inverse\' constraint need to have values of type \'wikibase-entityid\'.';
			return new CheckResult( $statement, 'Inverse', $parameters, CheckResult::STATUS_VIOLATION, $message );
		}
		if ( $property === null ) {
			$message = 'Properties with \'Inverse\' constraint need a parameter \'property\'.';
			return new CheckResult( $statement, 'Inverse', $parameters, CheckResult::STATUS_VIOLATION, $message );
		}

		$targetItem = $this->entityLookup->getEntity( $dataValue->getEntityId() );
		if ( $targetItem === null ) {
			$message = 'Target item does not exist.';
			return new CheckResult( $statement, 'Inverse', $parameters, CheckResult::STATUS_VIOLATION, $message );
		}
		$targetItemStatementList = $targetItem->getStatements();

		if ( $this->hasClaim( $targetItemStatementList, $property, $entityIdSerialization ) ) {
			$message = '';
			$status = CheckResult::STATUS_COMPLIANCE;
		} else {
			$message = 'This property must only be used when there is a statement on its value entity using the property defined in the parameters and this item as its value.';
			$status = CheckResult::STATUS_VIOLATION;
		}

		return new CheckResult( $statement, 'Inverse', $parameters, $status, $message );
	}

	/**
	 * Checks if there is a statement with a claim using the given property.
	 *
	 * @param StatementList $statementList
	 * @param string $propertyIdSerialization
	 *
	 * @return boolean
	 */
	private function hasProperty( $statementList, $propertyIdSerialization ) {
		foreach ( $statementList as $statement ) {
			if ( $statement->getPropertyId()->getSerialization() === $propertyIdSerialization ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Checks if there is a statement with a claim using the given property and having one of the given items as its value.
	 *
	 * @param StatementList $statementList
	 * @param string $propertyIdSerialization
	 * @param string|array $itemIdSerializationOrArray
	 *
	 * @return boolean
	 */
	private function hasClaim( $statementList, $propertyIdSerialization, $itemIdSerializationOrArray ) {
		foreach ( $statementList as $statement ) {
			if ( $statement->getPropertyId()->getSerialization() === $propertyIdSerialization ) {
				if ( is_string( $itemIdSerializationOrArray ) ) { // string
					$itemIdSerializationArray = array ( $itemIdSerializationOrArray );
				} else { // array
					$itemIdSerializationArray = $itemIdSerializationOrArray;
				}
				if ( $this->arrayHasClaim( $statement, $itemIdSerializationArray ) ) {
					return true;
				}
			}
		}
		return false;
	}

	private function arrayHasClaim( $statement, $itemIdSerializationArray ) {
		$mainSnak = $statement->getMainSnak();
		if ( $mainSnak->getType() !== 'value' || $mainSnak->getDataValue()->getType() !== 'wikibase-entityid' ) {
			return false;
		}

		foreach ( $itemIdSerializationArray as $itemIdSerialization ) {
			if ( $mainSnak->getDataValue()->getEntityId()->getSerialization() === $itemIdSerialization ) {
				return true;
			}
		}
		return false;
	}

}