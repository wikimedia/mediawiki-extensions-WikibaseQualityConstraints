<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Checker;

use Wikibase\DataModel\Snak\PropertyValueSnak;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikibaseQuality\ConstraintReport\Constraint;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Entity\Entity;


/**
 * @package WikibaseQuality\ConstraintReport\ConstraintCheck\Checker
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class CommonsLinkChecker implements ConstraintChecker {

	/**
	 * @var ConstraintReportHelper
	 */
	private $helper;

	/**
	 * @param ConstraintReportHelper $helper
	 */
	public function __construct( ConstraintReportHelper $helper ) {
		$this->helper = $helper;
	}

	/**
	 * Checks if data value is well-formed and links to an existing page.
	 *
	 * @param Statement $statement
	 * @param Constraint $constraint
	 * @param Entity $entity
	 *
	 * @return CheckResult
	 */
	public function checkConstraint( Statement $statement, Constraint $constraint, Entity $entity = null ) {
		$constraintName = 'Commons link';
		$parameters = array ();
		$constraintParameters = $constraint->getConstraintParameters();
		$namespace = '';
		if ( array_key_exists( 'namespace', $constraintParameters ) ) {
			$namespace = $constraintParameters['namespace'];
			$parameters['namespace'] = $this->helper->parseSingleParameter( $namespace, true );
		}

		if ( array_key_exists( 'constraint_status', $constraintParameters ) ) {
			$parameters['constraint_status'] = $this->helper->parseSingleParameter( $constraintParameters['constraint_status'], true );
		}

		$mainSnak = $statement->getMainSnak();

		/*
		 * error handling:
		 *   $mainSnak must be PropertyValueSnak, neither PropertySomeValueSnak nor PropertyNoValueSnak is allowed
		 */
		if ( !$mainSnak instanceof PropertyValueSnak ) {
			$message = wfMessage( "wbqc-violation-message-value-needed" )->params( $constraintName )->escaped();
			return new CheckResult( $statement, $constraint->getConstraintTypeQid(), $parameters, CheckResult::STATUS_VIOLATION, $message );
		}

		$dataValue = $mainSnak->getDataValue();

		/*
		 * error handling:
		 *   type of $dataValue for properties with 'Commons link' constraint has to be 'string'
		 *   parameter $namespace can be null, works for commons galleries
		 */
		if ( $dataValue->getType() !== 'string' ) {
			$message = wfMessage( "wbqc-violation-message-value-needed-of-type" )->params( $constraintName, 'string' )->escaped();
			return new CheckResult( $statement, $constraint->getConstraintTypeQid(), $parameters, CheckResult::STATUS_VIOLATION, $message );
		}

		$commonsLink = $dataValue->getValue();

		if ( $this->commonsLinkIsWellFormed( $commonsLink ) ) {
			if ( strtolower( $namespace ) === 'file' ) {
				if ( $this->fileExists( $commonsLink ) ) {
					$message = '';
					$status = CheckResult::STATUS_COMPLIANCE;
				} else {
					$message = wfMessage( "wbqc-violation-message-commons-link-non-existent" )->escaped();
					$status = CheckResult::STATUS_VIOLATION;
				}
			}
			else {
				$message = wfMessage( "wbqc-violation-message-commons-link-check-for-namespace-not-yet-implemented" )->params( strtolower( $namespace ) )->escaped();
				$status = CheckResult::STATUS_TODO;
			}
		} else {
			$message = wfMessage( "wbqc-violation-message-commons-link-not-well-formed" )->escaped();
			$status = CheckResult::STATUS_VIOLATION;
		}

		return new CheckResult( $statement, $constraint->getConstraintTypeQid(), $parameters, $status, $message );
	}

	/**
	 * @param string $commonsLink
	 *
	 * @return bool
	 */
	private function fileExists( $commonsLink ) {
		$commonsLink = str_replace( ' ', '_', $commonsLink );
		$commonsWikiId = 'commonswiki';

		if ( defined( 'MW_PHPUNIT_TEST' )) {
			$commonsWikiId = false;
		}
		$dbLoadBalancer = wfGetLB( $commonsWikiId );
		$dbConnection = $dbLoadBalancer->getConnection(
			DB_SLAVE, false, $commonsWikiId );
		$row = $dbConnection->selectRow(
			'image', '*', array( 'img_name' => $commonsLink ) );

		return $row ? true : false;
	}

	/**
	 * @param string $commonsLink
	 *
	 * @return bool
	 */
	private function commonsLinkIsWellFormed( $commonsLink ) {
		$toReplace = array ( "_", ":", "%20" );
		$compareString = trim( str_replace( $toReplace, '', $commonsLink ) );
		return $commonsLink === $compareString;
	}

}