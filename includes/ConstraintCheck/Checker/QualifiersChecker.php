<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Checker;

use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Snak\Snak;
use Wikibase\DataModel\Statement\StatementListProvider;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikibaseQuality\ConstraintReport\ConstraintParameterRenderer;
use Wikibase\DataModel\Statement\Statement;

/**
 * @package WikibaseQuality\ConstraintReport\ConstraintCheck\Checker
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class QualifiersChecker implements ConstraintChecker {

	/**
	 * Class for helper functions for constraint checkers.
	 *
	 * @var ConstraintParameterParser
	 */
	private $helper;

	/**
	 * @var ConstraintParameterRenderer
	 */
	private $constraintParameterRenderer;

	/**
	 * @param ConstraintParameterParser $helper
	 * @param ConstraintParameterRenderer $constraintParameterRenderer should return HTML
	 */
	public function __construct(
		ConstraintParameterParser $helper,
		ConstraintParameterRenderer $constraintParameterRenderer
	) {
		$this->helper = $helper;
		$this->constraintParameterRenderer = $constraintParameterRenderer;
	}

	/**
	 * Checks 'Qualifiers' constraint.
	 *
	 * @param Statement $statement
	 * @param Constraint $constraint
	 * @param EntityDocument|StatementListProvider $entity
	 *
	 * @return CheckResult
	 */
	public function checkConstraint( Statement $statement, Constraint $constraint, EntityDocument $entity ) {
		$parameters = [];
		$constraintParameters = $constraint->getConstraintParameters();

		$parameters['property'] = $this->helper->parseParameterArray( explode( ',', $constraintParameters['property'] ) );

		/*
		 * error handling:
		 *  $constraintParameters['property'] can be array( '' ), meaning that there are explicitly no qualifiers allowed
		 */

		$properties = [];
		if ( array_key_exists( 'property', $constraintParameters ) ) {
			$properties = explode( ',', $constraintParameters['property'] );
			$properties = array_map( 'strtoupper', $properties ); // FIXME strtoupper should not be necessary, remove once constraints are imported from statements
		}

		$message = '';
		$status = CheckResult::STATUS_COMPLIANCE;

		/** @var Snak $qualifier */
		foreach ( $statement->getQualifiers() as $qualifier ) {
			$pid = $qualifier->getPropertyId()->getSerialization();
			if ( !in_array( $pid, $properties ) ) {
				if ( empty( $properties ) || $properties === [ '' ] ) {
					$message = wfMessage( 'wbqc-violation-message-no-qualifiers' );
					$message->rawParams(
						$this->constraintParameterRenderer->formatEntityId( $statement->getPropertyId() )
					);
				} else {
					$message = wfMessage( "wbqc-violation-message-qualifiers" );
					$message->rawParams(
						$this->constraintParameterRenderer->formatEntityId( $statement->getPropertyId() ),
						$this->constraintParameterRenderer->formatPropertyId( $pid )
					);
					$message->numParams( count( $properties ) );
					$message->rawParams( $this->constraintParameterRenderer->formatPropertyIdList( $properties ) );
				}
				$message = $message->escaped();
				$status = CheckResult::STATUS_VIOLATION;
				break;
			}
		}

		return new CheckResult( $entity->getId(), $statement, $constraint->getConstraintTypeQid(), $constraint->getConstraintId(), $parameters, $status, $message );
	}

}
