<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Checker;

use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Snak\Snak;
use Wikibase\DataModel\Statement\StatementListProvider;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterException;
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
	 * @var ConstraintParameterParser
	 */
	private $constraintParameterParser;

	/**
	 * @var ConstraintParameterRenderer
	 */
	private $constraintParameterRenderer;

	/**
	 * @param ConstraintParameterParser $constraintParameterParser
	 * @param ConstraintParameterRenderer $constraintParameterRenderer should return HTML
	 */
	public function __construct(
		ConstraintParameterParser $constraintParameterParser,
		ConstraintParameterRenderer $constraintParameterRenderer
	) {
		$this->constraintParameterParser = $constraintParameterParser;
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

		$properties = $this->constraintParameterParser->parsePropertiesParameter( $constraintParameters, $constraint->getConstraintTypeItemId() );
		$parameters['property'] = $properties;

		$message = '';
		$status = CheckResult::STATUS_COMPLIANCE;

		/** @var Snak $qualifier */
		foreach ( $statement->getQualifiers() as $qualifier ) {
			$allowedQualifier = false;
			foreach ( $properties as $property ) {
				if ( $qualifier->getPropertyId()->equals( $property ) ) {
					$allowedQualifier = true;
					break;
				}
			}
			if ( !$allowedQualifier ) {
				if ( empty( $properties ) || $properties === [ '' ] ) {
					$message = wfMessage( 'wbqc-violation-message-no-qualifiers' );
					$message->rawParams(
						$this->constraintParameterRenderer->formatEntityId( $statement->getPropertyId(), ConstraintParameterRenderer::ROLE_CONSTRAINT_PROPERTY )
					);
				} else {
					$message = wfMessage( "wbqc-violation-message-qualifiers" );
					$message->rawParams(
						$this->constraintParameterRenderer->formatEntityId( $statement->getPropertyId(), ConstraintParameterRenderer::ROLE_CONSTRAINT_PROPERTY ),
						$this->constraintParameterRenderer->formatEntityId( $qualifier->getPropertyId(), ConstraintParameterRenderer::ROLE_QUALIFIER_PREDICATE )
					);
					$message->numParams( count( $properties ) );
					$message->rawParams( $this->constraintParameterRenderer->formatPropertyIdList( $properties, ConstraintParameterRenderer::ROLE_QUALIFIER_PREDICATE ) );
				}
				$message = $message->escaped();
				$status = CheckResult::STATUS_VIOLATION;
				break;
			}
		}

		return new CheckResult( $entity->getId(), $statement, $constraint, $parameters, $status, $message );
	}

	public function checkConstraintParameters( Constraint $constraint ) {
		$constraintParameters = $constraint->getConstraintParameters();
		$exceptions = [];
		try {
			$this->constraintParameterParser->parsePropertiesParameter( $constraintParameters, $constraint->getConstraintTypeItemId() );
		} catch ( ConstraintParameterException $e ) {
			$exceptions[] = $e;
		}
		return $exceptions;
	}

}
