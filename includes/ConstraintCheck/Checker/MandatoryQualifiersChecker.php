<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Checker;

use InvalidArgumentException;
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
class MandatoryQualifiersChecker implements ConstraintChecker {

	/**
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
	 * Checks 'Mandatory qualifiers' constraint.
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

		$properties = [];
		if ( array_key_exists( 'property', $constraintParameters ) ) {
			$properties = explode( ',', $constraintParameters['property'] );
		}
		$parameters['property'] = $this->helper->parseParameterArray( $properties );
		$qualifiersList = $statement->getQualifiers();
		$qualifiers = [];

		/** @var Snak $qualifier */
		foreach ( $qualifiersList as $qualifier ) {
			$qualifiers[ $qualifier->getPropertyId()->getSerialization() ] = true;
		}

		$message = '';
		$status = CheckResult::STATUS_COMPLIANCE;

		$missingQualifiers = [];
		foreach ( $properties as $property ) {
			$property = strtoupper( $property ); // FIXME strtoupper should not be necessary, remove once constraints are imported from statements
			if ( !array_key_exists( $property, $qualifiers ) ) {
				$missingQualifiers[] = $property;
			}
		}

		if ( $missingQualifiers ) {
			$message = wfMessage( "wbqc-violation-message-mandatory-qualifiers" );
			$message->rawParams(
				$this->constraintParameterRenderer->formatEntityId( $statement->getPropertyId() )
			);
			$message->numParams( count( $missingQualifiers ) );
			$message->rawParams( $this->constraintParameterRenderer->formatPropertyIdList( $missingQualifiers ) );
			$message = $message->escaped();
			$status = CheckResult::STATUS_VIOLATION;
		}

		return new CheckResult( $entity->getId(), $statement, $constraint->getConstraintTypeQid(), $constraint->getConstraintId(), $parameters, $status, $message );
	}

}
