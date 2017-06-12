<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Checker;

use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\DataModel\Statement\StatementListProvider;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\SparqlHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\SparqlHelperException;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use Wikibase\DataModel\Statement\Statement;

/**
 * @package WikibaseQuality\ConstraintReport\ConstraintCheck\Checker
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class TypeSparqlChecker implements ConstraintChecker {

	/**
	 * @var ConstraintParameterParser
	 */
	private $helper;

	/**
	 * @var EntityLookup
	 */
	private $entityLookup;

	/**
	 * @var SparqlHelper|null
	 */
	private $sparqlHelper;

	/**
	 * @param EntityLookup $lookup
	 * @param ConstraintParameterParser $helper
	 * @param SparqlHelper|null $sparqlHelper
	 */
	public function __construct( EntityLookup $lookup, ConstraintParameterParser $helper, SparqlHelper $sparqlHelper = null ) {
		$this->entityLookup = $lookup;
		$this->helper = $helper;
		$this->sparqlHelper = $sparqlHelper;
	}

	/**
	 * Checks 'Type' constraint.
	 *
	 * @param Statement $statement
	 * @param Constraint $constraint
	 * @param EntityDocument|StatementListProvider $entity
	 *
	 * @return CheckResult
	 */
	public function checkConstraint( Statement $statement, Constraint $constraint, EntityDocument $entity = null ) {
		$parameters = [];
		$constraintParameters = $constraint->getConstraintParameters();

		$classes = false;
		if ( array_key_exists( 'class', $constraintParameters ) ) {
			$classes = explode( ',', $constraintParameters['class'] );
			$parameters['class'] = $this->helper->parseParameterArray( $classes );
		}

		$relation = false;
		if ( array_key_exists( 'relation', $constraintParameters ) ) {
			$relation = $constraintParameters['relation'];
			$parameters['relation'] = $this->helper->parseSingleParameter( $relation, true );
		}

		/*
		 * error handling:
		 *   parameter $constraintParameters['class'] must not be null
		 */
		if ( !$classes ) {
			$message = wfMessage( "wbqc-violation-message-parameter-needed" )->params( $constraint->getConstraintTypeName(), 'class' )->escaped();
			return new CheckResult( $entity->getId(), $statement, $constraint->getConstraintTypeQid(), $constraint->getConstraintId(), $parameters, CheckResult::STATUS_VIOLATION, $message );
		}

		/*
		 * error handling:
		 *   parameter $constraintParameters['relation'] must be either 'instance' or 'subclass'
		 */
		if ( $relation === 'instance' ) {
			$withInstance = true;
		} elseif ( $relation === 'subclass' ) {
			$withInstance = false;
		} else {
			$message = wfMessage( "wbqc-violation-message-type-relation-instance-or-subclass" )->escaped();
			return new CheckResult( $entity->getId(), $statement, $constraint->getConstraintTypeQid(), $constraint->getConstraintId(), $parameters, CheckResult::STATUS_VIOLATION, $message );
		}

		if ( $this->sparqlHelper !== null ) {
			try {
				if ( $this->sparqlHelper->hasType( $entity->getId()->getSerialization(), $classes, $withInstance ) ) {
					$message = '';
					$status = CheckResult::STATUS_COMPLIANCE;
				} else {
					$message = wfMessage( "wbqc-violation-message-sparql-type" )->escaped();
					$status = CheckResult::STATUS_VIOLATION;
				}
			} catch ( SparqlHelperException $e ) {
				$status = CheckResult::STATUS_VIOLATION;
				$message = wfMessage( 'wbqc-violation-message-sparql-error' )->escaped();
			}
		} else {
			$status = CheckResult::STATUS_TODO;
			$message = wfMessage( 'wbqc-violation-message-not-yet-implemented' )
					 ->params( $constraint->getConstraintTypeName() )
					 ->escaped();
		}

		return new CheckResult( $entity->getId(), $statement, $constraint->getConstraintTypeQid(), $constraint->getConstraintId(), $parameters, $status, $message );
	}

}
