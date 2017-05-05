<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Checker;

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
	private $helper;

	/**
	 * @param ConstraintParameterParser $helper
	 */
	public function __construct( ConstraintParameterParser $helper ) {
		$this->helper = $helper;
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
			$message = wfMessage( "wbqc-violation-message-value-needed" )->params( $constraint->getConstraintTypeName() )->escaped();
			return new CheckResult( $entity->getId(), $statement, $constraint->getConstraintTypeQid(), $constraint->getConstraintId(), $parameters, CheckResult::STATUS_VIOLATION, $message );
		}

		$dataValue = $mainSnak->getDataValue();

		/*
		 * error handling:
		 *   type of $dataValue for properties with 'Commons link' constraint has to be 'string'
		 *   parameter $namespace can be null, works for commons galleries
		 */
		if ( $dataValue->getType() !== 'string' ) {
			$message = wfMessage( "wbqc-violation-message-value-needed-of-type" )->params( $constraint->getConstraintTypeName(), 'string' )->escaped();
			return new CheckResult( $entity->getId(), $statement, $constraint->getConstraintTypeQid(), $constraint->getConstraintId(), $parameters, CheckResult::STATUS_VIOLATION, $message );
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
			} else {
				$message = wfMessage( "wbqc-violation-message-commons-link-check-for-namespace-not-yet-implemented" )->params( strtolower( $namespace ) )->escaped();
				$status = CheckResult::STATUS_TODO;
			}
		} else {
			$message = wfMessage( "wbqc-violation-message-commons-link-not-well-formed" )->escaped();
			$status = CheckResult::STATUS_VIOLATION;
		}

		return new CheckResult( $entity->getId(), $statement, $constraint->getConstraintTypeQid(), $constraint->getConstraintId(),  $parameters, $status, $message );
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
			DB_REPLICA, false, $commonsWikiId );
		$row = $dbConnection->selectRow(
			'image', '*', [ 'img_name' => $commonsLink ] );

		return $row ? true : false;
	}

	/**
	 * @param string $commonsLink
	 *
	 * @return bool
	 */
	private function commonsLinkIsWellFormed( $commonsLink ) {
		$toReplace = [ "_", ":", "%20" ];
		$compareString = trim( str_replace( $toReplace, '', $commonsLink ) );
		return $commonsLink === $compareString;
	}

}
