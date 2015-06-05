<?php

namespace WikibaseQuality\ConstraintReport;

use Wikibase\Lib\Store\EntityRevisionLookup;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\Lib\Store\EntityLookup;
use WikibaseQuality\ConstraintReport\ConstraintCheck\DelegatingConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper;
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
use WikibaseQuality\ConstraintReport\Violations\CheckResultToViolationTranslator;
use WikibaseQuality\ConstraintReport\Violations\ConstraintViolationContext;


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
	private function getConstraintCheckerMap(){
		if ( $this->constraintCheckerMap === null ) {
			$constraintReportHelper = new ConstraintReportHelper();
			$connectionCheckerHelper = new ConnectionCheckerHelper();
			$rangeCheckerHelper = new RangeCheckerHelper();
			$typeCheckerHelper = new TypeCheckerHelper( $this->entityLookup );

			$this->constraintCheckerMap = array(
				'Conflicts with' => new ConflictsWithChecker( $this->entityLookup, $constraintReportHelper, $connectionCheckerHelper ),
				'Item' => new ItemChecker( $this->entityLookup, $constraintReportHelper, $connectionCheckerHelper ),
				'Target required claim' => new TargetRequiredClaimChecker( $this->entityLookup, $constraintReportHelper, $connectionCheckerHelper ),
				'Symmetric' => new SymmetricChecker( $this->entityLookup, $constraintReportHelper, $connectionCheckerHelper ),
				'Inverse' => new InverseChecker( $this->entityLookup, $constraintReportHelper, $connectionCheckerHelper ),
				'Qualifier' => new QualifierChecker( $constraintReportHelper ),
				'Qualifiers' => new QualifiersChecker( $constraintReportHelper ),
				'Mandatory qualifiers' => new MandatoryQualifiersChecker( $constraintReportHelper ),
				'Range' => new RangeChecker( $constraintReportHelper, $rangeCheckerHelper ),
				'Diff within range' => new DiffWithinRangeChecker( $constraintReportHelper, $rangeCheckerHelper ),
				'Type' => new TypeChecker( $this->entityLookup, $constraintReportHelper, $typeCheckerHelper ),
				'Value type' => new ValueTypeChecker( $this->entityLookup, $constraintReportHelper, $typeCheckerHelper ),
				'Single value' => new SingleValueChecker( $constraintReportHelper ),
				'Multi value' => new MultiValueChecker( $constraintReportHelper ),
				'Unique value' => new UniqueValueChecker( $constraintReportHelper ),
				'Format' => new FormatChecker( $constraintReportHelper ),
				'Commons link' => new CommonsLinkChecker( $constraintReportHelper ),
				'One of' => new OneOfChecker( $constraintReportHelper ),
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
     * @return ConstraintViolationContext
     */
    public function getViolationContext() {
        return new ConstraintViolationContext(
            array_keys( $this->getConstraintCheckerMap() )
        );
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