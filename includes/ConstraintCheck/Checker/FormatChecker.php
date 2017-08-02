<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Checker;

use Config;
use DataValues\StringValue;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\StatementListProvider;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterException;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\SparqlHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikibaseQuality\ConstraintReport\ConstraintParameterRenderer;
use WikibaseQuality\ConstraintReport\Role;
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
	 * @var Config
	 */
	private $config;

	/**
	 * @param ConstraintParameterParser $constraintParameterParser
	 * @param ConstraintParameterRenderer $constraintParameterRenderer
	 * @param Config $config
	 * @param SparqlHelper|null $sparqlHelper
	 */
	public function __construct(
		ConstraintParameterParser $constraintParameterParser,
		ConstraintParameterRenderer $constraintParameterRenderer,
		Config $config,
		SparqlHelper $sparqlHelper = null
	) {
		$this->constraintParameterParser = $constraintParameterParser;
		$this->constraintParameterRenderer = $constraintParameterRenderer;
		$this->config = $config;
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

		if ( !$mainSnak instanceof PropertyValueSnak ) {
			// nothing to check
			return new CheckResult( $entity->getId(), $statement, $constraint, $parameters, CheckResult::STATUS_COMPLIANCE, '' );
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
							 $this->constraintParameterRenderer->formatItemId( $constraint->getConstraintTypeItemId(), Role::CONSTRAINT_TYPE_ITEM ),
							 wfMessage( 'datatypes-type-string' )->escaped(),
							 wfMessage( 'datatypes-type-monolingualtext' )->escaped()
						 )
						 ->escaped();
				return new CheckResult( $entity->getId(), $statement, $constraint, $parameters, CheckResult::STATUS_VIOLATION, $message );
		}

		if ( $this->sparqlHelper !== null && $this->config->get( 'WBQualityConstraintsCheckFormatConstraint' ) ) {
			if ( $this->sparqlHelper->matchesRegularExpression( $text, $format ) ) {
				$message = '';
				$status = CheckResult::STATUS_COMPLIANCE;
			} else {
				$message = wfMessage( 'wbqc-violation-message-format' )
						 ->rawParams(
							$this->constraintParameterRenderer->formatEntityId( $statement->getPropertyId(), Role::CONSTRAINT_PROPERTY ),
							$this->constraintParameterRenderer->formatDataValue( new StringValue( $text ), Role::OBJECT ),
							$this->constraintParameterRenderer->formatByRole( Role::CONSTRAINT_PARAMETER_VALUE,
								'<code><nowiki>' . htmlspecialchars( $format ) . '</nowiki></code>' )
						 )
						 ->escaped();
				$status = CheckResult::STATUS_VIOLATION;
			}
		} else {
			$message = wfMessage( "wbqc-violation-message-security-reason" )
					 ->rawParams( $this->constraintParameterRenderer->formatItemId( $constraint->getConstraintTypeItemId(), Role::CONSTRAINT_TYPE_ITEM ) )
					 ->escaped();
			$status = CheckResult::STATUS_TODO;
		}
		return new CheckResult( $entity->getId(), $statement, $constraint, $parameters, $status, $message );
	}

	public function checkConstraintParameters( Constraint $constraint ) {
		$constraintParameters = $constraint->getConstraintParameters();
		$exceptions = [];
		try {
			$this->constraintParameterParser->parseFormatParameter( $constraintParameters, $constraint->getConstraintTypeItemId() );
		} catch ( ConstraintParameterException $e ) {
			$exceptions[] = $e;
		}
		return $exceptions;
	}

}
