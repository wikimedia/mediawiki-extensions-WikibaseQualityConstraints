<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Checker;

use DataValues\MonolingualTextValue;
use DataValues\MultilingualTextValue;
use DataValues\StringValue;
use MediaWiki\Config\Config;
use MediaWiki\Shell\ShellboxClientFactory;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shellbox\ShellboxError;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\Context;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterException;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\DummySparqlHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\FormatCheckerHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\SparqlHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessage;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikibaseQuality\ConstraintReport\Role;

/**
 * @author BP2014N1
 * @license GPL-2.0-or-later
 */
class FormatChecker implements ConstraintChecker {

	/**
	 * @var ConstraintParameterParser
	 */
	private $constraintParameterParser;

	/**
	 * @var SparqlHelper
	 */
	private $sparqlHelper;

	/**
	 * @var Config
	 */
	private $config;

	/**
	 * @var ShellboxClientFactory
	 */
	private $shellboxClientFactory;

	private array $knownGoodPatternsAsKeys;

	private LoggerInterface $logger;

	/**
	 * @param ConstraintParameterParser $constraintParameterParser
	 * @param Config $config
	 * @param SparqlHelper $sparqlHelper
	 * @param ShellboxClientFactory $shellboxClientFactory
	 */
	public function __construct(
		ConstraintParameterParser $constraintParameterParser,
		Config $config,
		SparqlHelper $sparqlHelper,
		ShellboxClientFactory $shellboxClientFactory,
		?LoggerInterface $logger = null
	) {
		$this->constraintParameterParser = $constraintParameterParser;
		$this->config = $config;
		$this->sparqlHelper = $sparqlHelper;
		$this->shellboxClientFactory = $shellboxClientFactory;
		$this->knownGoodPatternsAsKeys = array_fill_keys(
			$this->config->get( 'WBQualityConstraintsFormatCheckerKnownGoodRegexPatterns' ),
			null
		);
		$this->logger = $logger ?? new NullLogger();
	}

	/**
	 * @codeCoverageIgnore This method is purely declarative.
	 */
	public function getSupportedContextTypes() {
		return self::ALL_CONTEXT_TYPES_SUPPORTED;
	}

	/**
	 * @codeCoverageIgnore This method is purely declarative.
	 */
	public function getDefaultContextTypes() {
		return Context::ALL_CONTEXT_TYPES;
	}

	/** @codeCoverageIgnore This method is purely declarative. */
	public function getSupportedEntityTypes() {
		return self::ALL_ENTITY_TYPES_SUPPORTED;
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
		$constraintParameters = $constraint->getConstraintParameters();
		$constraintTypeItemId = $constraint->getConstraintTypeItemId();

		$format = $this->constraintParameterParser->parseFormatParameter(
			$constraintParameters,
			$constraintTypeItemId
		);

		$syntaxClarifications = $this->constraintParameterParser->parseSyntaxClarificationParameter(
			$constraintParameters
		);

		$snak = $context->getSnak();

		if ( !$snak instanceof PropertyValueSnak ) {
			// nothing to check
			return new CheckResult( $context, $constraint, CheckResult::STATUS_COMPLIANCE );
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
				'@phan-var MonolingualTextValue $dataValue';
				$text = $dataValue->getText();
				break;
			default:
				$message = ( new ViolationMessage( 'wbqc-violation-message-value-needed-of-types-2' ) )
					->withEntityId( new ItemId( $constraintTypeItemId ), Role::CONSTRAINT_TYPE_ITEM )
					->withDataValueType( 'string' )
					->withDataValueType( 'monolingualtext' );
				return new CheckResult( $context, $constraint, CheckResult::STATUS_VIOLATION, $message );
		}
		$status = $this->runRegexCheck( $text, $format );
		$message = $this->formatMessage(
			$status,
			$text,
			$format,
			$context->getSnak()->getPropertyId(),
			$syntaxClarifications,
			$constraintTypeItemId
		);
		return new CheckResult( $context, $constraint, $status, $message );
	}

	private function formatMessage(
		string $status,
		string $text,
		string $format,
		PropertyId $propertyId,
		MultilingualTextValue $syntaxClarifications,
		string $constraintTypeItemId
	): ?ViolationMessage {
		$message = null;
		if ( $status === CheckResult::STATUS_VIOLATION ) {
			$message = ( new ViolationMessage( 'wbqc-violation-message-format-clarification' ) )
				->withEntityId( $propertyId, Role::CONSTRAINT_PROPERTY )
				->withDataValue( new StringValue( $text ), Role::OBJECT )
				->withInlineCode( $format, Role::CONSTRAINT_PARAMETER_VALUE )
				->withMultilingualText( $syntaxClarifications, Role::CONSTRAINT_PARAMETER_VALUE );
		} elseif ( $status === CheckResult::STATUS_TODO ) {
			$message = ( new ViolationMessage( 'wbqc-violation-message-security-reason' ) )
				->withEntityId( new ItemId( $constraintTypeItemId ), Role::CONSTRAINT_TYPE_ITEM );
		}

		return $message;
	}

	private function runRegexCheck( string $text, string $format ): string {
		if ( !$this->config->get( 'WBQualityConstraintsCheckFormatConstraint' ) ) {
			return CheckResult::STATUS_TODO;
		}
		if ( \array_key_exists( $format, $this->knownGoodPatternsAsKeys ) ) {
			$checkResult = FormatCheckerHelper::runRegexCheck( $format, $text );
		} elseif (
			$this->config->get( 'WBQualityConstraintsFormatCheckerShellboxRatio' ) > (float)wfRandom()
		) {
			$checkResult = $this->runRegexCheckUsingShellbox( $text, $format );
		} else {
			return $this->runRegexCheckUsingSparql( $text, $format );
		}

		if ( $checkResult === 1 ) {
			return CheckResult::STATUS_COMPLIANCE;
		} elseif ( $checkResult === 0 ) {
			return CheckResult::STATUS_VIOLATION;
		} elseif ( $checkResult === false ) {
			throw new ConstraintParameterException(
				( new ViolationMessage( 'wbqc-violation-message-parameter-regex' ) )
					->withInlineCode( $format, Role::CONSTRAINT_PARAMETER_VALUE )
			);
		} else {
			return $checkResult;
		}
	}

	/**
	 * @return false|int|string Possible return values are:
	 *   - 1 if $format matches $text
	 *   - 0 if $format does not match $text
	 *   - FALSE if $format is invalid regex
	 *   - CheckResult::STATUS_TODO if Shellbox is not enabled
	 */
	private function runRegexCheckUsingShellbox( string $text, string $format ) {
		if ( !$this->shellboxClientFactory->isEnabled( 'constraint-regex-checker' ) ) {
			return CheckResult::STATUS_TODO;
		}

		try {
			return $this->shellboxClientFactory->getClient( [
				'timeout' => $this->config->get( 'WBQualityConstraintsSparqlMaxMillis' ) / 1000,
				'service' => 'constraint-regex-checker',
			] )->call(
				'constraint-regex-checker',
				[ FormatCheckerHelper::class, 'runRegexCheck' ],
				[ $format, $text ],
				[ 'classes' => [ FormatCheckerHelper::class ] ],
			);
		} catch ( ClientExceptionInterface $ce ) {
			$this->logger->notice( __METHOD__ . ': Network error, skipping check: {exception}', [
				'exception' => $ce,
				'text' => $text,
				'format' => $format,
			] );
			return CheckResult::STATUS_TODO;
		} catch ( ShellboxError $e ) {
			$this->logger->error( __METHOD__ . ': Shellbox error, skipping check: {exception}', [
				'exception' => $e,
				'text' => $text,
				'format' => $format,
			] );
			return CheckResult::STATUS_TODO;
		}
	}

	private function runRegexCheckUsingSparql( string $text, string $format ): string {
		if ( $this->sparqlHelper instanceof DummySparqlHelper ) {
			return CheckResult::STATUS_TODO;
		}

		if ( $this->sparqlHelper->matchesRegularExpression( $text, $format ) ) {
			return CheckResult::STATUS_COMPLIANCE;
		} else {
			return CheckResult::STATUS_VIOLATION;
		}
	}

	public function checkConstraintParameters( Constraint $constraint ) {
		$constraintParameters = $constraint->getConstraintParameters();
		$constraintTypeItemId = $constraint->getConstraintTypeItemId();
		$exceptions = [];
		try {
			$this->constraintParameterParser->parseFormatParameter(
				$constraintParameters,
				$constraintTypeItemId
			);
		} catch ( ConstraintParameterException $e ) {
			$exceptions[] = $e;
		}
		try {
			$this->constraintParameterParser->parseSyntaxClarificationParameter(
				$constraintParameters
			);
		} catch ( ConstraintParameterException $e ) {
			$exceptions[] = $e;
		}
		return $exceptions;
	}

}
