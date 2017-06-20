<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Checker;

use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Snak\Snak;
use Wikibase\DataModel\Statement\StatementListProvider;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintStatementParameterParser;
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
	 * @var ConstraintStatementParameterParser
	 */
	private $constraintParameterParser;

	/**
	 * @var ConstraintParameterRenderer
	 */
	private $constraintParameterRenderer;

	/**
	 * @param ConstraintStatementParameterParser $constraintParameterParser
	 * @param ConstraintParameterRenderer $constraintParameterRenderer should return HTML
	 */
	public function __construct(
		ConstraintStatementParameterParser $constraintParameterParser,
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

		$properties = $this->constraintParameterParser->parsePropertiesParameter( $constraintParameters, $constraint->getConstraintTypeName() );
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
						$this->constraintParameterRenderer->formatEntityId( $statement->getPropertyId() )
					);
				} else {
					$message = wfMessage( "wbqc-violation-message-qualifiers" );
					$message->rawParams(
						$this->constraintParameterRenderer->formatEntityId( $statement->getPropertyId() ),
						$this->constraintParameterRenderer->formatEntityId( $qualifier->getPropertyId() )
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
