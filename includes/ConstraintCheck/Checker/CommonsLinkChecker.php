<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Checker;

use MalformedTitleException;
use TitleParser;
use TitleValue;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\StatementListProvider;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\Context;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterException;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintParameterRenderer;
use WikibaseQuality\ConstraintReport\Role;
use Wikibase\DataModel\Statement\Statement;

/**
 * @package WikibaseQuality\ConstraintReport\ConstraintCheck\Checker
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

	/**
	 * @param ConstraintParameterParser $constraintParameterParser
	 * @param ConstraintParameterRenderer $constraintParameterRenderer
	 * @param TitleParser $titleParser
	 */
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
	 * Checks 'Commons link' constraint.
	 *
	 * @param Context $context
	 * @param Constraint $constraint
	 *
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
			$prefix = $namespace === '' ? '' : $namespace . ':';
			$title = $this->titleParser->parseTitle( $prefix . $commonsLink, NS_MAIN );
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
			'page_title' => $title->getDBKey(),
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
	 * @return bool
	 */
	private function valueIncludesNamespace( $value, $namespace ) {
		return $namespace !== '' &&
			strncasecmp( $value, $namespace . ':', strlen( $namespace ) + 1 ) === 0;
	}

}
