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
class MandatoryQualifiersChecker implements ConstraintChecker {

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

		$propertyId = $this->constraintParameterParser->parsePropertyParameter( $constraintParameters, $constraint->getConstraintTypeName() );
		$parameters['property'] = [ $propertyId ];

		$message = wfMessage( "wbqc-violation-message-mandatory-qualifier" )
				 ->rawParams(
					 $this->constraintParameterRenderer->formatEntityId( $statement->getPropertyId() ),
					 $this->constraintParameterRenderer->formatEntityId( $propertyId )
				 )
				 ->escaped();
		$status = CheckResult::STATUS_VIOLATION;

		/** @var Snak $qualifier */
		foreach ( $statement->getQualifiers() as $qualifier ) {
			if ( $propertyId->equals( $qualifier->getPropertyId() ) ) {
				$message = '';
				$status = CheckResult::STATUS_COMPLIANCE;
				break;
			}
		}

		return new CheckResult( $entity->getId(), $statement, $constraint->getConstraintTypeQid(), $constraint->getConstraintId(), $parameters, $status, $message );
	}

}
