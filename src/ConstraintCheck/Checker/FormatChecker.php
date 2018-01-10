<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Checker;

use Config;
use DataValues\MonolingualTextValue;
use DataValues\StringValue;
use Language;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\Repo\WikibaseRepo;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\Context;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterException;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\SparqlHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikibaseQuality\ConstraintReport\ConstraintParameterRenderer;
use WikibaseQuality\ConstraintReport\Role;

/**
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
	 * @param Context $context
	 * @param Constraint $constraint
	 *
	 * @throws ConstraintParameterException
	 * @return CheckResult
	 */
	public function checkConstraint( Context $context, Constraint $constraint ) {
		$parameters = [];
		$constraintParameters = $constraint->getConstraintParameters();

		$format = $this->constraintParameterParser->parseFormatParameter( $constraintParameters, $constraint->getConstraintTypeItemId() );
		$parameters['pattern'] = [ $format ];

		$syntaxClarification = $this->constraintParameterParser->parseSyntaxClarificationParameter(
			$constraintParameters,
			WikibaseRepo::getDefaultInstance()->getUserLanguage() // TODO make this part of the Context?
		);
		if ( $syntaxClarification !== null ) {
			$parameters['clarification'] = [ $syntaxClarification ];
		}

		$snak = $context->getSnak();

		if ( !$snak instanceof PropertyValueSnak ) {
			// nothing to check
			return new CheckResult( $context, $constraint, $parameters, CheckResult::STATUS_COMPLIANCE, '' );
		}

		$dataValue = $snak->getDataValue();

		/*
		 * error handling:
		 *   type of $dataValue for properties with 'Format' constraint has to be 'string' or 'monolingualtext'
		 */
		switch ( $dataValue->getType() ) {
			case 'string':
				$text = $dataValue->getValue();
				break;
			case 'monolingualtext':
				/** @var MonolingualTextValue $dataValue */
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
				return new CheckResult( $context, $constraint, $parameters, CheckResult::STATUS_VIOLATION, $message );
		}

		if ( $this->sparqlHelper !== null && $this->config->get( 'WBQualityConstraintsCheckFormatConstraint' ) ) {
			if ( $this->sparqlHelper->matchesRegularExpression( $text, $format ) ) {
				$message = '';
				$status = CheckResult::STATUS_COMPLIANCE;
			} else {
				$message = wfMessage(
					$syntaxClarification !== null ?
						'wbqc-violation-message-format-clarification' :
						'wbqc-violation-message-format'
				)->rawParams(
					$this->constraintParameterRenderer->formatEntityId( $context->getSnak()->getPropertyId(), Role::CONSTRAINT_PROPERTY ),
					$this->constraintParameterRenderer->formatDataValue( new StringValue( $text ), Role::OBJECT ),
					$this->constraintParameterRenderer->formatByRole( Role::CONSTRAINT_PARAMETER_VALUE,
						'<code><nowiki>' . htmlspecialchars( $format ) . '</nowiki></code>' )
				);
				if ( $syntaxClarification !== null ) {
					$message->params( $syntaxClarification );
				}
				$message = $message->escaped();
				$status = CheckResult::STATUS_VIOLATION;
			}
		} else {
			$message = wfMessage( "wbqc-violation-message-security-reason" )
					 ->rawParams( $this->constraintParameterRenderer->formatItemId( $constraint->getConstraintTypeItemId(), Role::CONSTRAINT_TYPE_ITEM ) )
					 ->escaped();
			$status = CheckResult::STATUS_TODO;
		}
		return new CheckResult( $context, $constraint, $parameters, $status, $message );
	}

	public function checkConstraintParameters( Constraint $constraint ) {
		$constraintParameters = $constraint->getConstraintParameters();
		$exceptions = [];
		try {
			$this->constraintParameterParser->parseFormatParameter( $constraintParameters, $constraint->getConstraintTypeItemId() );
		} catch ( ConstraintParameterException $e ) {
			$exceptions[] = $e;
		}
		try {
			$this->constraintParameterParser->parseSyntaxClarificationParameter(
				$constraintParameters,
				Language::factory( 'en' ) // errors are reported independent of language requested
			);
		} catch ( ConstraintParameterException $e ) {
			$exceptions[] = $e;
		}
		return $exceptions;
	}

}
