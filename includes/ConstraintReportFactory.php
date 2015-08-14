<?php

namespace WikibaseQuality\ConstraintReport;

use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\Lib\Store\EntityRevisionLookup;
use Wikibase\Repo\WikibaseRepo;
use WikibaseQuality\ConstraintReport\ConstraintCheck\DelegatingConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\CommonsLinkChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\FormatChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\OneOfChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\QualifierChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\RangeChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\TypeChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\ConflictsWithChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\QualifiersChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\TargetRequiredClaimChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\ItemChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\MandatoryQualifiersChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\ValueTypeChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\SymmetricChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\InverseChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\DiffWithinRangeChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\SingleValueChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\MultiValueChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\UniqueValueChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConnectionCheckerHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\RangeCheckerHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\TypeCheckerHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ValueCountCheckerHelper;
use WikibaseQuality\ConstraintReport\Violations\CheckResultToViolationTranslator;
use WikibaseQuality\ConstraintReport\Violations\ConstraintViolationFormatter;
use WikibaseQuality\Violations\ViolationFormatter;


class ConstraintReportFactory {

	/**
	 * @var EntityLookup
	 */
	private $entityLookup;

	/**
	 * @var EntityRevisionLookup
	 */
	private $entityRevisionLookup;

	/**
	 * @var constraintRepository
	 */
	private $constraintRepository;

	/**
	 * @var array
	 */
	private $constraintCheckerMap;

	/**
	 * @var DelegatingConstraintChecker
	 */
	private $delegatingConstraintChecker;

	/**
	 * @var array
	 */
	private $constraintParameterMap;

	/**
	 * @var ViolationFormatter
	 */
	private $violationFormatter;

	/**
	 * @var CheckResultToViolationTranslator
	 */
	private $checkResultToViolationTranslator;

	/**
	 * Returns the default instance.
	 * IMPORTANT: Use only when it is not feasible to inject an instance properly.
	 *
	 * @return ConstraintReportFactory
	 */
	public static function getDefaultInstance() {
		static $instance = null;

		if ( $instance === null ) {
			$instance = new self(
				WikibaseRepo::getDefaultInstance()->getEntityLookup(),
				WikibaseRepo::getDefaultInstance()->getEntityRevisionLookup()
			);
		}

		return $instance;
	}

	/**
	 * @param EntityLookup $entityLookup
	 * @param EntityRevisionLookup $entityRevisionLookup
	 */
	public function __construct( EntityLookup $entityLookup, EntityRevisionLookup $entityRevisionLookup ) {
		$this->entityLookup = $entityLookup;
		$this->entityRevisionLookup = $entityRevisionLookup;
	}

	/**
	 * @return DelegatingConstraintChecker
	 */
	public function getConstraintChecker() {
		if ( $this->delegatingConstraintChecker === null ) {
			$this->delegatingConstraintChecker = new DelegatingConstraintChecker( $this->entityLookup, $this->getConstraintCheckerMap( $this->entityLookup ) );
		}

		return $this->delegatingConstraintChecker;
	}

	/**
	 * @return array
	 */
	public function getConstraintCheckerMap(){
		if ( $this->constraintCheckerMap === null ) {
			$constraintParameterParser = new ConstraintParameterParser();
			$connectionCheckerHelper = new ConnectionCheckerHelper();
			$rangeCheckerHelper = new RangeCheckerHelper();
			$typeCheckerHelper = new TypeCheckerHelper( $this->entityLookup );
			$valueCountCheckerHelper = new ValueCountCheckerHelper();

			$this->constraintCheckerMap = array(
				'Conflicts with' => new ConflictsWithChecker( $this->entityLookup, $constraintParameterParser, $connectionCheckerHelper ),
				'Item' => new ItemChecker( $this->entityLookup, $constraintParameterParser, $connectionCheckerHelper ),
				'Target required claim' => new TargetRequiredClaimChecker( $this->entityLookup, $constraintParameterParser, $connectionCheckerHelper ),
				'Symmetric' => new SymmetricChecker( $this->entityLookup, $constraintParameterParser, $connectionCheckerHelper ),
				'Inverse' => new InverseChecker( $this->entityLookup, $constraintParameterParser, $connectionCheckerHelper ),
				'Qualifier' => new QualifierChecker( $constraintParameterParser ),
				'Qualifiers' => new QualifiersChecker( $constraintParameterParser ),
				'Mandatory qualifiers' => new MandatoryQualifiersChecker( $constraintParameterParser ),
				'Range' => new RangeChecker( $constraintParameterParser, $rangeCheckerHelper ),
				'Diff within range' => new DiffWithinRangeChecker( $constraintParameterParser, $rangeCheckerHelper ),
				'Type' => new TypeChecker( $this->entityLookup, $constraintParameterParser, $typeCheckerHelper ),
				'Value type' => new ValueTypeChecker( $this->entityLookup, $constraintParameterParser, $typeCheckerHelper ),
				'Single value' => new SingleValueChecker( $constraintParameterParser, $valueCountCheckerHelper ),
				'Multi value' => new MultiValueChecker( $constraintParameterParser, $valueCountCheckerHelper ),
				'Unique value' => new UniqueValueChecker( $constraintParameterParser, $valueCountCheckerHelper ),
				'Format' => new FormatChecker( $constraintParameterParser ),
				'Commons link' => new CommonsLinkChecker( $constraintParameterParser ),
				'One of' => new OneOfChecker( $constraintParameterParser ),
			);
		}

		return $this->constraintCheckerMap;
	}

	/**
	 * @return array
	 */
	public function getConstraintParameterMap() {
		if ( $this->constraintParameterMap === null ) {
			$this->constraintParameterMap = array(
				'Commons link' => array( 'namespace' ),
				'Conflicts with' => array( 'property', 'item' ),
				'Diff within range' => array( 'property', 'minimum_quantity', 'maximum_quantity' ),
				'Format' => array( 'pattern' ),
				'Inverse' => array( 'property' ),
				'Item' => array( 'property', 'item' ),
				'Mandatory qualifiers' => array( 'property' ),
				'Multi value' => array(),
				'One of' => array( 'item' ),
				'Qualifier' => array(),
				'Qualifiers' => array( 'property' ),
				'Range' => array( 'minimum_quantity', 'maximum_quantity', 'minimum_date', 'maximum_date' ),
				'Single value' => array(),
				'Symmetric' => array(),
				'Target required claim' => array( 'property', 'item' ),
				'Type' => array( 'class', 'relation' ),
				'Unique value' => array(),
				'Value type' => array( 'class', 'relation' )
			);
		}

		return $this->constraintParameterMap;
	}

	/**
	 * @return ConstraintRepository
	 */
	public function getConstraintRepository() {
		if ( $this->constraintRepository === null ) {
			$this->constraintRepository = new ConstraintRepository( CONSTRAINT_TABLE );
		}

		return $this->constraintRepository;
	}

	/**
	 * @return ViolationFormatter
	 */
	public function getViolationFormatter() {
		if ( $this->violationFormatter === null ) {
			$this->violationFormatter = new ConstraintViolationFormatter();
		}

		return $this->violationFormatter;
	}

	/**
	 * @return CheckResultToViolationTranslator
	 */
	public function getCheckResultToViolationTranslator() {
		if ( $this->checkResultToViolationTranslator === null ) {
			$this->checkResultToViolationTranslator = new CheckResultToViolationTranslator( $this->entityRevisionLookup );
		}

		return $this->checkResultToViolationTranslator;
	}

}