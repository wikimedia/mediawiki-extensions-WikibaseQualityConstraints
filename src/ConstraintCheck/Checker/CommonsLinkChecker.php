<?php

declare( strict_types = 1 );

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Checker;

use MalformedTitleException;
use MediaWiki\Site\MediaWikiPageNameNormalizer;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\Context;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterException;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessage;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikibaseQuality\ConstraintReport\Role;

/**
 * @author BP2014N1
 * @license GPL-2.0-or-later
 */
class CommonsLinkChecker implements ConstraintChecker {

	/**
	 * @var ConstraintParameterParser
	 */
	private $constraintParameterParser;

	/**
	 * @var MediaWikiPageNameNormalizer
	 */
	private $pageNameNormalizer;

	public function __construct(
		ConstraintParameterParser $constraintParameterParser,
		MediaWikiPageNameNormalizer $pageNameNormalizer
	) {
		$this->constraintParameterParser = $constraintParameterParser;
		$this->pageNameNormalizer = $pageNameNormalizer;
	}

	/**
	 * @codeCoverageIgnore This method is purely declarative.
	 */
	public function getSupportedContextTypes(): array {
		return [
			Context::TYPE_STATEMENT => CheckResult::STATUS_COMPLIANCE,
			Context::TYPE_QUALIFIER => CheckResult::STATUS_COMPLIANCE,
			Context::TYPE_REFERENCE => CheckResult::STATUS_COMPLIANCE,
		];
	}

	/**
	 * @codeCoverageIgnore This method is purely declarative.
	 */
	public function getDefaultContextTypes(): array {
		return [
			Context::TYPE_STATEMENT,
			Context::TYPE_QUALIFIER,
			Context::TYPE_REFERENCE,
		];
	}

	/**
	 * Get the number of a namespace on Wikimedia Commons (commonswiki).
	 * All namespaces not known to this function will be looked up by the TitleParser.
	 *
	 * @return array first element is the namespace number (default namespace for TitleParser),
	 * second element is a string to prepend to the title before giving it to the TitleParser
	 */
	private function getCommonsNamespace( string $namespace ): array {
		switch ( $namespace ) {
			case '':
				return [ NS_MAIN, '' ];
			// extra namespaces, see operations/mediawiki-config.git,
			// wmf-config/InitialiseSettings.php, 'wgExtraNamespaces' key, 'commonswiki' subkey
			case 'Creator':
				return [ 100, '' ];
			case 'TimedText':
				return [ 102, '' ];
			case 'Sequence':
				return [ 104, '' ];
			case 'Institution':
				return [ 106, '' ];
			// extension namespace, see mediawiki/extensions/JsonConfig.git,
			// extension.json, 'namespaces' key, third element
			case 'Data':
				return [ 486, '' ];
			default:
				return [ NS_MAIN, $namespace . ':' ];
		}
	}

	/**
	 * Checks 'Commons link' constraint.
	 *
	 * @throws ConstraintParameterException
	 */
	public function checkConstraint( Context $context, Constraint $constraint ): CheckResult {
		$parameters = [];
		$constraintParameters = $constraint->getConstraintParameters();
		$constraintTypeItemId = $constraint->getConstraintTypeItemId();

		$namespace = $this->constraintParameterParser->parseNamespaceParameter(
			$constraintParameters,
			$constraintTypeItemId
		);
		$parameters['namespace'] = [ $namespace ];

		$snak = $context->getSnak();

		if ( !$snak instanceof PropertyValueSnak ) {
			// nothing to check
			return new CheckResult( $context, $constraint, $parameters, CheckResult::STATUS_COMPLIANCE );
		}

		$dataValue = $snak->getDataValue();

		/*
		 * error handling:
		 *   type of $dataValue for properties with 'Commons link' constraint has to be 'string'
		 *   parameter $namespace can be null, works for commons galleries
		 */
		if ( $dataValue->getType() !== 'string' ) {
			$message = ( new ViolationMessage( 'wbqc-violation-message-value-needed-of-type' ) )
				->withEntityId( new ItemId( $constraintTypeItemId ), Role::CONSTRAINT_TYPE_ITEM )
				->withDataValueType( 'string' );
			return new CheckResult( $context, $constraint, $parameters, CheckResult::STATUS_VIOLATION, $message );
		}

		$commonsLink = $dataValue->getValue();

		try {
			if ( !$this->commonsLinkIsWellFormed( $commonsLink ) ) {
				throw new MalformedTitleException( 'caught below', $commonsLink );
			}

			$prefix = $this->getCommonsNamespace( $namespace )[1];
			$normalizedTitle = $this->pageNameNormalizer->normalizePageName(
				$prefix . $commonsLink,
				'https://commons.wikimedia.org/w/api.php'
			);

			if ( $normalizedTitle === false ) {
				if ( $this->valueIncludesNamespace( $commonsLink, $namespace ) ) {
					throw new MalformedTitleException( 'caught below', $commonsLink );
				} else {
					$message = new ViolationMessage( 'wbqc-violation-message-commons-link-no-existent' );
					$status = CheckResult::STATUS_VIOLATION;
				}
			} else {
				$message = null;
				$status = CheckResult::STATUS_COMPLIANCE;
			}
		} catch ( MalformedTitleException $e ) {
			$message = new ViolationMessage( 'wbqc-violation-message-commons-link-not-well-formed' );
			$status = CheckResult::STATUS_VIOLATION;
		}

		return new CheckResult( $context, $constraint, $parameters, $status, $message );
	}

	public function checkConstraintParameters( Constraint $constraint ): array {
		$constraintParameters = $constraint->getConstraintParameters();
		$constraintTypeItemId = $constraint->getConstraintTypeItemId();
		$exceptions = [];
		try {
			$this->constraintParameterParser->parseNamespaceParameter(
				$constraintParameters,
				$constraintTypeItemId
			);
		} catch ( ConstraintParameterException $e ) {
			$exceptions[] = $e;
		}
		return $exceptions;
	}

	private function commonsLinkIsWellFormed( string $commonsLink ): bool {
		$toReplace = [ "_", "%20" ];
		$compareString = trim( str_replace( $toReplace, '', $commonsLink ) );

		return $commonsLink === $compareString;
	}

	/**
	 * Checks whether the value of the statement already includes the namespace.
	 * This special case should be reported as “malformed title” instead of “title does not exist”.
	 */
	private function valueIncludesNamespace( string $value, string $namespace ): bool {
		return $namespace !== '' &&
			strncasecmp( $value, $namespace . ':', strlen( $namespace ) + 1 ) === 0;
	}

}
