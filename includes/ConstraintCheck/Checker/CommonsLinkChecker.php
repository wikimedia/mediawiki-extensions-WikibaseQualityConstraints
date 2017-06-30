<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Checker;

use MalformedTitleException;
use TitleParser;
use TitleValue;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\StatementListProvider;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikibaseQuality\ConstraintReport\Constraint;
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
	 * @var TitleParser
	 */
	private $titleParser;

	/**
	 * @param ConstraintParameterParser $constraintParameterParser
	 * @param TitleParser $titleParser
	 */
	public function __construct(
		ConstraintParameterParser $constraintParameterParser,
		TitleParser $titleParser
	) {
		$this->constraintParameterParser = $constraintParameterParser;
		$this->titleParser = $titleParser;
	}

	/**
	 * Checks 'Commons link' constraint.
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
		$namespace = $this->constraintParameterParser->parseNamespaceParameter( $constraintParameters, $constraint->getConstraintTypeItemId() );
		$parameters['namespace'] = [ $namespace ];

		$mainSnak = $statement->getMainSnak();

		/*
		 * error handling:
		 *   $mainSnak must be PropertyValueSnak, neither PropertySomeValueSnak nor PropertyNoValueSnak is allowed
		 */
		if ( !$mainSnak instanceof PropertyValueSnak ) {
			$message = wfMessage( "wbqc-violation-message-value-needed" )->params( $constraint->getConstraintTypeName() )->escaped();
			return new CheckResult( $entity->getId(), $statement, $constraint, $parameters, CheckResult::STATUS_VIOLATION, $message );
		}

		$dataValue = $mainSnak->getDataValue();

		/*
		 * error handling:
		 *   type of $dataValue for properties with 'Commons link' constraint has to be 'string'
		 *   parameter $namespace can be null, works for commons galleries
		 */
		if ( $dataValue->getType() !== 'string' ) {
			$message = wfMessage( "wbqc-violation-message-value-needed-of-type" )->params( $constraint->getConstraintTypeName(), 'string' )->escaped();
			return new CheckResult( $entity->getId(), $statement, $constraint, $parameters, CheckResult::STATUS_VIOLATION, $message );
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

		return new CheckResult( $entity->getId(), $statement, $constraint,  $parameters, $status, $message );
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
