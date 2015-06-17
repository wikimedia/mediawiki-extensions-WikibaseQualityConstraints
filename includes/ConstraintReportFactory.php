<?php

namespace WikibaseQuality\ConstraintReport;

use Wikibase\Repo\WikibaseRepo;
use Wikibase\Lib\Store\EntityLookup;
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


class ConstraintReportFactory {

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
	 * @var EntityLookup
	 */
	private $lookup;

	/**
	 * @var array
	 */
	private $constraintParameterMap;

	/**
	 * Returns the default instance.
	 * IMPORTANT: Use only when it is not feasible to inject an instance properly.
	 *
	 * @return ConstraintReportFactory
	 */
	public static function getDefaultInstance() {
		static $instance = null;

		if ( $instance === null ) {
			$instance = new self( WikibaseRepo::getDefaultInstance()->getEntityLookup() );
		}

		return $instance;
	}

	/**
	 * @param EntityLookup $lookup
	 */
	public function __construct( EntityLookup $lookup ) {
		$this->lookup = $lookup;
	}

	/**
	 * @return DelegatingConstraintChecker
	 */
	public function getConstraintChecker() {
		if ( $this->delegatingConstraintChecker === null ) {
			$this->delegatingConstraintChecker = new DelegatingConstraintChecker( $this->lookup, $this->getConstraintCheckerMap( $this->lookup ) );
		}

		return $this->delegatingConstraintChecker;
	}

	/**
	 * @return array
	 */
	private function getConstraintCheckerMap(){
		if ( $this->constraintCheckerMap === null ) {
			$constraintParameterParser = new ConstraintParameterParser();
			$connectionCheckerHelper = new ConnectionCheckerHelper();
			$rangeCheckerHelper = new RangeCheckerHelper();
			$typeCheckerHelper = new TypeCheckerHelper( $this->lookup );

			$this->constraintCheckerMap = array(
				'Conflicts with' => new ConflictsWithChecker( $this->lookup, $constraintParameterParser, $connectionCheckerHelper ),
				'Item' => new ItemChecker( $this->lookup, $constraintParameterParser, $connectionCheckerHelper ),
				'Target required claim' => new TargetRequiredClaimChecker( $this->lookup, $constraintParameterParser, $connectionCheckerHelper ),
				'Symmetric' => new SymmetricChecker( $this->lookup, $constraintParameterParser, $connectionCheckerHelper ),
				'Inverse' => new InverseChecker( $this->lookup, $constraintParameterParser, $connectionCheckerHelper ),
				'Qualifier' => new QualifierChecker( $constraintParameterParser ),
				'Qualifiers' => new QualifiersChecker( $constraintParameterParser ),
				'Mandatory qualifiers' => new MandatoryQualifiersChecker( $constraintParameterParser ),
				'Range' => new RangeChecker( $constraintParameterParser, $rangeCheckerHelper ),
				'Diff within range' => new DiffWithinRangeChecker( $constraintParameterParser, $rangeCheckerHelper ),
				'Type' => new TypeChecker( $this->lookup, $constraintParameterParser, $typeCheckerHelper ),
				'Value type' => new ValueTypeChecker( $this->lookup, $constraintParameterParser, $typeCheckerHelper ),
				'Single value' => new SingleValueChecker(),
				'Multi value' => new MultiValueChecker(),
				'Unique value' => new UniqueValueChecker(),
				'Format' => new FormatChecker( $constraintParameterParser ),
				'Commons link' => new CommonsLinkChecker( $constraintParameterParser ),
				'One of' => new OneOfChecker( $constraintParameterParser ),
			);
		}

		return $this->constraintCheckerMap;
	}

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

	public function getConstraintRepository() {
		if ( $this->constraintRepository === null ) {
			$this->constraintRepository = new ConstraintRepository( CONSTRAINT_TABLE );
		}

		return $this->constraintRepository;
	}

}