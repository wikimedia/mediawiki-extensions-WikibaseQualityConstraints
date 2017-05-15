<?php

namespace WikibaseQuality\ConstraintReport;

use Config;
use MediaWiki\MediaWikiServices;
use Wikibase\DataModel\Services\EntityId\EntityIdFormatter;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
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
use WikibaseQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConnectionCheckerHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\RangeCheckerHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\TypeCheckerHelper;
use Wikibase\DataModel\Services\Statement\StatementGuidParser;

class ConstraintReportFactory {

	/**
	 * @var ConstraintRepository|null
	 */
	private $constraintRepository;

	/**
	 * @var ConstraintChecker[]|null
	 */
	private $constraintCheckerMap;

	/**
	 * @var DelegatingConstraintChecker|null
	 */
	private $delegatingConstraintChecker;

	/**
	 * @var EntityLookup
	 */
	private $lookup;

	/**
	 * @var array[]|null
	 */
	private $constraintParameterMap;

	/**
	 * @var StatementGuidParser
	 */
	private $statementGuidParser;

	/**
	 * @var Config
	 */
	private $config;

	/**
	 * @var EntityIdFormatter
	 */
	private $entityIdFormatter;

	/**
	 * Returns the default instance.
	 * IMPORTANT: Use only when it is not feasible to inject an instance properly.
	 *
	 * @return self
	 */
	public static function getDefaultInstance() {
		static $instance = null;

		if ( $instance === null ) {
			$wikibaseRepo = WikibaseRepo::getDefaultInstance();
			$instance = new self(
				$wikibaseRepo->getEntityLookup(),
				$wikibaseRepo->getStatementGuidParser(),
				MediaWikiServices::getInstance()->getMainConfig(),
				$wikibaseRepo->getEntityIdHtmlLinkFormatterFactory()->getEntityIdFormatter(
					$wikibaseRepo->getLanguageFallbackLabelDescriptionLookupFactory()->newLabelDescriptionLookup(
						$wikibaseRepo->getUserLanguage()
					)
				)
			);
		}

		return $instance;
	}

	public function __construct(
		EntityLookup $lookup,
		StatementGuidParser
		$statementGuidParser,
		Config $config,
		EntityIdFormatter $entityIdFormatter
	) {
		$this->lookup = $lookup;
		$this->statementGuidParser = $statementGuidParser;
		$this->config = $config;
		$this->entityIdFormatter = $entityIdFormatter;
	}

	/**
	 * @return DelegatingConstraintChecker
	 */
	public function getConstraintChecker() {
		if ( $this->delegatingConstraintChecker === null ) {
			$this->delegatingConstraintChecker = new DelegatingConstraintChecker(
				$this->lookup,
				$this->getConstraintCheckerMap(),
				new CachingConstraintLookup( $this->getConstraintRepository() )
			);
		}

		return $this->delegatingConstraintChecker;
	}

	/**
	 * @return ConstraintChecker[]
	 */
	private function getConstraintCheckerMap() {
		if ( $this->constraintCheckerMap === null ) {
			$constraintParameterParser = new ConstraintParameterParser();
			$connectionCheckerHelper = new ConnectionCheckerHelper();
			$rangeCheckerHelper = new RangeCheckerHelper();
			$typeCheckerHelper = new TypeCheckerHelper( $this->lookup, $this->config, $this->entityIdFormatter );

			$this->constraintCheckerMap = [
				'Conflicts with' => new ConflictsWithChecker( $this->lookup, $constraintParameterParser, $connectionCheckerHelper ),
				'Item' => new ItemChecker( $this->lookup, $constraintParameterParser, $connectionCheckerHelper ),
				'Target required claim' => new TargetRequiredClaimChecker( $this->lookup, $constraintParameterParser, $connectionCheckerHelper ),
				'Symmetric' => new SymmetricChecker( $this->lookup, $constraintParameterParser, $connectionCheckerHelper, $this->entityIdFormatter ),
				'Inverse' => new InverseChecker( $this->lookup, $constraintParameterParser, $connectionCheckerHelper, $this->entityIdFormatter ),
				'Qualifier' => new QualifierChecker( $constraintParameterParser ),
				'Qualifiers' => new QualifiersChecker( $constraintParameterParser ),
				'Mandatory qualifiers' => new MandatoryQualifiersChecker( $constraintParameterParser ),
				'Range' => new RangeChecker( $constraintParameterParser, $rangeCheckerHelper ),
				'Diff within range' => new DiffWithinRangeChecker( $constraintParameterParser, $rangeCheckerHelper ),
				'Type' => new TypeChecker( $this->lookup, $constraintParameterParser, $typeCheckerHelper, $this->config ),
				'Value type' => new ValueTypeChecker( $this->lookup, $constraintParameterParser, $typeCheckerHelper, $this->config ),
				'Single value' => new SingleValueChecker(),
				'Multi value' => new MultiValueChecker(),
				'Unique value' => new UniqueValueChecker(),
				'Format' => new FormatChecker( $constraintParameterParser ),
				'Commons link' => new CommonsLinkChecker( $constraintParameterParser ),
				'One of' => new OneOfChecker( $constraintParameterParser, $this->entityIdFormatter ),
			];
		}

		return $this->constraintCheckerMap;
	}

	/**
	 * @return array[]
	 */
	public function getConstraintParameterMap() {
		if ( $this->constraintParameterMap === null ) {
			$this->constraintParameterMap = [
				'Commons link' => [ 'namespace' ],
				'Conflicts with' => [ 'property', 'item' ],
				'Diff within range' => [ 'property', 'minimum_quantity', 'maximum_quantity' ],
				'Format' => [ 'pattern' ],
				'Inverse' => [ 'property' ],
				'Item' => [ 'property', 'item' ],
				'Mandatory qualifiers' => [ 'property' ],
				'Multi value' => [],
				'One of' => [ 'item' ],
				'Qualifier' => [],
				'Qualifiers' => [ 'property' ],
				'Range' => [ 'minimum_quantity', 'maximum_quantity', 'minimum_date', 'maximum_date' ],
				'Single value' => [],
				'Symmetric' => [],
				'Target required claim' => [ 'property', 'item' ],
				'Type' => [ 'class', 'relation' ],
				'Unique value' => [],
				'Value type' => [ 'class', 'relation' ]
			];
		}

		return $this->constraintParameterMap;
	}

	/**
	 * @return ConstraintRepository
	 */
	public function getConstraintRepository() {
		if ( $this->constraintRepository === null ) {
			$this->constraintRepository = new ConstraintRepository();
		}

		return $this->constraintRepository;
	}

}
