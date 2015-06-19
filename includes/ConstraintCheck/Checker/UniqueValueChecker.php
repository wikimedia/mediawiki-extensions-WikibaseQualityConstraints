<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Checker;

use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ValueCountCheckerHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Entity\Entity;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;


/**
 * @package WikibaseQuality\ConstraintReport\ConstraintCheck\Checker
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class UniqueValueChecker implements ConstraintChecker {

	/**
	 * @var ValueCountCheckerHelper
	 */
	private $valueCountCheckerHelper;

	/**
	 * @var ConstraintParameterParser
	 */
	private $constraintParameterParser;

	/**
	 * @param ConstraintParameterParser $helper
	 * @param ValueCountCheckerHelper $valueCountCheckerHelper
	 */
	public function __construct( ConstraintParameterParser $helper, ValueCountCheckerHelper $valueCountCheckerHelper ) {
		$this->constraintParameterParser = $helper;
		$this->valueCountCheckerHelper = $valueCountCheckerHelper;
	}

	// todo: implement when index exists that makes it possible in reasonable time
	/**
	 * @param Statement $statement
	 * @param Constraint $constraint
	 * @param Entity $entity
	 *
	 * @return CheckResult
	 */
	public function checkConstraint( Statement $statement, Constraint $constraint, Entity $entity = null ) {
		$parameters = array ();

		$constraintParameters = $constraint->getConstraintParameters();
		if ( array_key_exists( 'constraint_status', $constraintParameters ) ) {
			$parameters['constraint_status'] = $this->constraintParameterParser->parseSingleParameter( $constraintParameters['constraint_status'], true );
		}

		$message = wfMessage( "wbqc-violation-message-not-yet-implemented" )->escaped();
		return new CheckResult( $statement, $constraint->getConstraintTypeQid(), $parameters, CheckResult::STATUS_TODO, $message );
	}

}