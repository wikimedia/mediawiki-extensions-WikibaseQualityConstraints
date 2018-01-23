<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Checker;

use MalformedTitleException;
use TitleParser;
use TitleValue;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\Context;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterException;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintParameterRenderer;
use WikibaseQuality\ConstraintReport\Role;

/**
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class CommonsLinkChecker implements ConstraintChecker {

	/**
	 * @var ConstraintParameterParser
	 */
	private $constraintParameterParser;

	/**
	 * @var ConstraintParameterRenderer
	 */
	private $constraintParameterRenderer;

	/**
	 * @var TitleParser
	 */
	private $titleParser;

	public function __construct(
		ConstraintParameterParser $constraintParameterParser,
		ConstraintParameterRenderer $constraintParameterRenderer,
		TitleParser $titleParser
	) {
		$this->constraintParameterParser = $constraintParameterParser;
		$this->constraintParameterRenderer = $constraintParameterRenderer;
		$this->titleParser = $titleParser;
	}

	/**
	 * @codeCoverageIgnore This method is purely declarative.
	 */
	public function getSupportedContextTypes() {
		return [
			Context::TYPE_STATEMENT => CheckResult::STATUS_COMPLIANCE,
			Context::TYPE_QUALIFIER => CheckResult::STATUS_COMPLIANCE,
			Context::TYPE_REFERENCE => CheckResult::STATUS_COMPLIANCE,
		];
	}

	/**
	 * @codeCoverageIgnore This method is purely declarative.
	 */
	public function getDefaultContextTypes() {
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
	 * @param string $namespace
	 *
	 * @return array first element is the namespace number (default namespace for TitleParser),
	 * second element is a string to prepend to the title before giving it to the TitleParser
	 */
	private function getCommonsNamespace( $namespace ) {
		// for namespace numbers see mediawiki-config repo, wmf-config/InitialiseSettings.php,
		// 'wgExtraNamespaces' key, 'commonswiki' subkey
		switch ( $namespace ) {
			case '':
				return [ NS_MAIN, '' ];
			case 'Creator':
				return [ 100, '' ];
			case 'TimedText':
				return [ 102, '' ];
			case 'Sequence':
				return [ 104, '' ];
			case 'Institution':
				return [ 106, '' ];
			default:
				return [ NS_MAIN, $namespace . ':' ];
		}
	}

	/**
	 * Checks 'Commons link' constraint.
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
		$namespace = $this->constraintParameterParser->parseNamespaceParameter( $constraintParameters, $constraint->getConstraintTypeItemId() );
		$parameters['namespace'] = [ $namespace ];

		$snak = $context->getSnak();

		if ( !$snak instanceof PropertyValueSnak ) {
			// nothing to check
			return new CheckResult( $context, $constraint, $parameters, CheckResult::STATUS_COMPLIANCE, '' );
		}

		$dataValue = $snak->getDataValue();

		/*
		 * error handling:
		 *   type of $dataValue for properties with 'Commons link' constraint has to be 'string'
		 *   parameter $namespace can be null, works for commons galleries
		 */
		if ( $dataValue->getType() !== 'string' ) {
			$message = wfMessage( "wbqc-violation-message-value-needed-of-type" )
					 ->rawParams(
						 $this->constraintParameterRenderer->formatItemId( $constraint->getConstraintTypeItemId(), Role::CONSTRAINT_TYPE_ITEM ),
						 wfMessage( 'datatypes-type-string' )->escaped()
					 )
					 ->escaped();
			return new CheckResult( $context, $constraint, $parameters, CheckResult::STATUS_VIOLATION, $message );
		}

		$commonsLink = $dataValue->getValue();

		try {
			if ( !$this->commonsLinkIsWellFormed( $commonsLink ) ) {
				throw new MalformedTitleException( 'wbqc-violation-message-commons-link-not-well-formed', $commonsLink ); // caught below
			}
			list ( $defaultNamespace, $prefix ) = $this->getCommonsNamespace( $namespace );
			$title = $this->titleParser->parseTitle( $prefix . $commonsLink, $defaultNamespace );
			if ( $this->pageExists( $title ) ) {
				$message = '';
				$status = CheckResult::STATUS_COMPLIANCE;
			} else {
				if ( $this->valueIncludesNamespace( $commonsLink, $namespace ) ) {
					throw new MalformedTitleException( 'wbqc-violation-message-commons-link-not-well-formed', $commonsLink ); // caught below
				} else {
					$message = wfMessage( "wbqc-violation-message-commons-link-no-existent" )->escaped();
					$status = CheckResult::STATUS_VIOLATION;
				}
			}
		} catch ( MalformedTitleException $e ) {
			$message = wfMessage( "wbqc-violation-message-commons-link-not-well-formed" )->escaped();
			$status = CheckResult::STATUS_VIOLATION;
		}

		return new CheckResult( $context, $constraint, $parameters, $status, $message );
	}

	public function checkConstraintParameters( Constraint $constraint ) {
		$constraintParameters = $constraint->getConstraintParameters();
		$exceptions = [];
		try {
			$this->constraintParameterParser->parseNamespaceParameter( $constraintParameters, $constraint->getConstraintTypeItemId() );
		} catch ( ConstraintParameterException $e ) {
			$exceptions[] = $e;
		}
		return $exceptions;
	}

	/**
	 * @param TitleValue $title
	 *
	 * @return bool
	 */
	private function pageExists( TitleValue $title ) {
		$commonsWikiId = 'commonswiki';
		if ( defined( 'MW_PHPUNIT_TEST' ) ) {
			$commonsWikiId = false;
		}

		$dbLoadBalancer = wfGetLB( $commonsWikiId );
		$dbConnection = $dbLoadBalancer->getConnection(
			DB_REPLICA, false, $commonsWikiId );
		$row = $dbConnection->selectRow( 'page', '*', [
			'page_title' => $title->getDBkey(),
			'page_namespace' => $title->getNamespace()
		] );

		return $row !== false;
	}

	/**
	 * @param string $commonsLink
	 *
	 * @return bool
	 */
	private function commonsLinkIsWellFormed( $commonsLink ) {
		$toReplace = [ "_", "%20" ];
		$compareString = trim( str_replace( $toReplace, '', $commonsLink ) );
		return $commonsLink === $compareString;
	}

	/**
	 * Checks whether the value of the statement already includes the namespace.
	 * This special case should be reported as “malformed title” instead of “title does not exist”.
	 *
	 * @param string $value
	 * @param string $namespace
	 *
	 * @return bool
	 */
	private function valueIncludesNamespace( $value, $namespace ) {
		return $namespace !== '' &&
			strncasecmp( $value, $namespace . ':', strlen( $namespace ) + 1 ) === 0;
	}

}
