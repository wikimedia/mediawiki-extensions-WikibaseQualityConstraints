<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Checker;

use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Snak\PropertyValueSnak;
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
class FormatChecker implements ConstraintChecker {

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
	 * @param ConstraintParameterRenderer $constraintParameterRenderer
	 */
	public function __construct(
		ConstraintParameterParser $constraintParameterParser,
		ConstraintParameterRenderer $constraintParameterRenderer
	) {
		$this->constraintParameterParser = $constraintParameterParser;
		$this->constraintParameterRenderer = $constraintParameterRenderer;
	}

	/**
	 * Checks 'Format' constraint.
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

		$format = $this->constraintParameterParser->parseFormatParameter( $constraintParameters, $constraint->getConstraintTypeItemId() );
		$parameters['pattern'] = [ $format ];

		$mainSnak = $statement->getMainSnak();

		/*
		 * error handling:
		 *   $mainSnak must be PropertyValueSnak, neither PropertySomeValueSnak nor PropertyNoValueSnak is allowed
		 */
		if ( !$mainSnak instanceof PropertyValueSnak ) {
			$message = wfMessage( "wbqc-violation-message-value-needed" )
					 ->rawParams( $this->constraintParameterRenderer->formatItemId( $constraint->getConstraintTypeItemId(), ConstraintParameterRenderer::ROLE_CONSTRAINT_TYPE_ITEM ) )
					 ->escaped();
			return new CheckResult( $entity->getId(), $statement, $constraint,  $parameters, CheckResult::STATUS_VIOLATION, $message );
		}

		$dataValue = $mainSnak->getDataValue();

		/*
		 * error handling:
		 *   type of $dataValue for properties with 'Format' constraint has to be 'string' or 'monolingualtext'
		 */
		$type = $dataValue->getType();
		if ( $type !== 'string' && $type !== 'monolingualtext' ) {
			$message = wfMessage( "wbqc-violation-message-value-needed-of-type" )
					 ->rawParams(
						 $this->constraintParameterRenderer->formatItemId( $constraint->getConstraintTypeItemId(), ConstraintParameterRenderer::ROLE_CONSTRAINT_TYPE_ITEM ),
						 wfMessage( 'datatypes-type-string' )->escaped(),
						 wfMessage( 'datatypes-type-monolingualtext' )->escaped()
					 )
					 ->escaped();
			return new CheckResult( $entity->getId(), $statement, $constraint, $parameters, CheckResult::STATUS_VIOLATION, $message );
		}

		$message = wfMessage( "wbqc-violation-message-security-reason" )
				 ->rawParams( $this->constraintParameterRenderer->formatItemId( $constraint->getConstraintTypeItemId(), ConstraintParameterRenderer::ROLE_CONSTRAINT_TYPE_ITEM ) )
				 ->escaped();
		return new CheckResult( $entity->getId(), $statement, $constraint, $parameters, CheckResult::STATUS_TODO, $message );
	}

}
