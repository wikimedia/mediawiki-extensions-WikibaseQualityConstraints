<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Checker;

use DataValues\StringValue;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\StatementListProvider;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\SparqlHelper;
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
	 * @var SparqlHelper
	 */
	private $sparqlHelper;

	/**
	 * @param ConstraintParameterParser $constraintParameterParser
	 * @param ConstraintParameterRenderer $constraintParameterRenderer
	 * @param SparqlHelper|null $sparqlHelper
	 */
	public function __construct(
		ConstraintParameterParser $constraintParameterParser,
		ConstraintParameterRenderer $constraintParameterRenderer,
		SparqlHelper $sparqlHelper = null
	) {
		$this->constraintParameterParser = $constraintParameterParser;
		$this->constraintParameterRenderer = $constraintParameterRenderer;
		$this->sparqlHelper = $sparqlHelper;
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
		switch ( $dataValue->getType() ) {
			case 'string':
				$text = $dataValue->getValue();
				break;
			case 'monolingualtext':
				$text = $dataValue->getText();
				break;
			default:
				$message = wfMessage( "wbqc-violation-message-value-needed-of-type" )
						 ->rawParams(
							 $this->constraintParameterRenderer->formatItemId( $constraint->getConstraintTypeItemId(), ConstraintParameterRenderer::ROLE_CONSTRAINT_TYPE_ITEM ),
							 wfMessage( 'datatypes-type-string' )->escaped(),
							 wfMessage( 'datatypes-type-monolingualtext' )->escaped()
						 )
						 ->escaped();
				return new CheckResult( $entity->getId(), $statement, $constraint, $parameters, CheckResult::STATUS_VIOLATION, $message );
		}

		if ( $this->sparqlHelper !== null ) {
			if ( $this->sparqlHelper->matchesRegularExpression( $text, $format ) ) {
				$message = '';
				$status = CheckResult::STATUS_COMPLIANCE;
			} else {
				$message = wfMessage( 'wbqc-violation-message-format' )
						 ->rawParams(
							$this->constraintParameterRenderer->formatEntityId( $statement->getPropertyId(), ConstraintParameterRenderer::ROLE_CONSTRAINT_PROPERTY ),
							$this->constraintParameterRenderer->formatDataValue( new StringValue( $text ), ConstraintParameterRenderer::ROLE_OBJECT ),
							$this->constraintParameterRenderer->formatByRole( ConstraintParameterRenderer::ROLE_CONSTRAINT_PARAMETER_VALUE,
								'<code><nowiki>' . htmlspecialchars( $format ) . '</nowiki></code>' )
						 )
						 ->escaped();
				$status = CheckResult::STATUS_VIOLATION;
			}
		} else {
			$message = wfMessage( "wbqc-violation-message-security-reason" )
					 ->rawParams( $this->constraintParameterRenderer->formatItemId( $constraint->getConstraintTypeItemId(), ConstraintParameterRenderer::ROLE_CONSTRAINT_TYPE_ITEM ) )
					 ->escaped();
			$status = CheckResult::STATUS_TODO;
		}
		return new CheckResult( $entity->getId(), $statement, $constraint, $parameters, $status, $message );
	}

}
