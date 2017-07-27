<?php

namespace WikibaseQuality\ConstraintReport;

use Config;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use TitleParser;
use ValueFormatters\FormatterOptions;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use Wikibase\Lib\SnakFormatter;
use Wikibase\Rdf\RdfVocabulary;
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
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\LoggingHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\RangeCheckerHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\SparqlHelper;
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
	 * @var PropertyDataTypeLookup
	 */
	private $propertyDataTypeLookup;

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
	 * @var ConstraintParameterRenderer
	 */
	private $constraintParameterRenderer;

	/**
	 * @var ConstraintParameterParser
	 */
	private $constraintParameterParser;

	/**
	 * @var RdfVocabulary
	 */
	private $rdfVocabulary;

	/**
	 * @var EntityIdParser
	 */
	private $entityIdParser;

	/**
	 * @var TitleParser
	 */
	private $titleParser;

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
			$entityIdFormatter = $wikibaseRepo->getEntityIdHtmlLinkFormatterFactory()->getEntityIdFormatter(
				$wikibaseRepo->getLanguageFallbackLabelDescriptionLookupFactory()->newLabelDescriptionLookup(
					$wikibaseRepo->getUserLanguage()
				)
			);
			$config = MediaWikiServices::getInstance()->getMainConfig();
			$titleParser = MediaWikiServices::getInstance()->getTitleParser();
			$constraintParameterRenderer = new ConstraintParameterRenderer(
				$entityIdFormatter,
				$wikibaseRepo->getValueFormatterFactory()->getValueFormatter(
					SnakFormatter::FORMAT_HTML,
					new FormatterOptions()
				)
			);
			$instance = new self(
				$wikibaseRepo->getEntityLookup(),
				$wikibaseRepo->getPropertyDataTypeLookup(),
				$wikibaseRepo->getStatementGuidParser(),
				$config,
				$constraintParameterRenderer,
				new ConstraintParameterParser(
					$config,
					$wikibaseRepo->getBaseDataModelDeserializerFactory(),
					$constraintParameterRenderer
				),
				$wikibaseRepo->getRdfVocabulary(),
				$wikibaseRepo->getEntityIdParser(),
				$titleParser
			);
		}

		return $instance;
	}

	public function __construct(
		EntityLookup $lookup,
		PropertyDataTypeLookup $propertyDataTypeLookup,
		StatementGuidParser $statementGuidParser,
		Config $config,
		ConstraintParameterRenderer $constraintParameterRenderer,
		ConstraintParameterParser $constraintParameterParser,
		RdfVocabulary $rdfVocabulary,
		EntityIdParser $entityIdParser,
		TitleParser $titleParser
	) {
		$this->lookup = $lookup;
		$this->propertyDataTypeLookup = $propertyDataTypeLookup;
		$this->statementGuidParser = $statementGuidParser;
		$this->config = $config;
		$this->constraintParameterRenderer = $constraintParameterRenderer;
		$this->constraintParameterParser = $constraintParameterParser;
		$this->rdfVocabulary = $rdfVocabulary;
		$this->entityIdParser = $entityIdParser;
		$this->titleParser = $titleParser;
	}

	/**
	 * @return DelegatingConstraintChecker
	 */
	public function getConstraintChecker() {
		if ( $this->delegatingConstraintChecker === null ) {
			$this->delegatingConstraintChecker = new DelegatingConstraintChecker(
				$this->lookup,
				$this->getConstraintCheckerMap(),
				new CachingConstraintLookup( $this->getConstraintRepository() ),
				$this->constraintParameterParser,
				$this->statementGuidParser,
				new LoggingHelper(
					MediaWikiServices::getInstance()->getStatsdDataFactory(),
					LoggerFactory::getInstance( 'WikibaseQualityConstraints' ),
					$this->config
				)
			);
		}

		return $this->delegatingConstraintChecker;
	}

	/**
	 * @return ConstraintChecker[]
	 */
	private function getConstraintCheckerMap() {
		if ( $this->constraintCheckerMap === null ) {
			$connectionCheckerHelper = new ConnectionCheckerHelper();
			$rangeCheckerHelper = new RangeCheckerHelper( $this->config );
			if ( $this->config->get( 'WBQualityConstraintsSparqlEndpoint' ) !== '' ) {
				$sparqlHelper = new SparqlHelper(
					$this->config,
					$this->rdfVocabulary,
					$this->entityIdParser
				);
			} else {
				$sparqlHelper = null;
			}
			$typeCheckerHelper = new TypeCheckerHelper(
				$this->lookup,
				$this->config,
				$this->constraintParameterRenderer,
				$sparqlHelper
			);

			$this->constraintCheckerMap = [
				'Conflicts with' => new ConflictsWithChecker(
					$this->lookup, $this->constraintParameterParser, $connectionCheckerHelper, $this->constraintParameterRenderer ),
				'Item' => new ItemChecker( $this->lookup, $this->constraintParameterParser, $connectionCheckerHelper, $this->constraintParameterRenderer ),
				'Target required claim' => new TargetRequiredClaimChecker(
					$this->lookup, $this->constraintParameterParser, $connectionCheckerHelper, $this->constraintParameterRenderer ),
				'Symmetric' => new SymmetricChecker( $this->lookup, $connectionCheckerHelper, $this->constraintParameterRenderer ),
				'Inverse' => new InverseChecker( $this->lookup, $this->constraintParameterParser, $connectionCheckerHelper, $this->constraintParameterRenderer ),
				'Qualifier' => new QualifierChecker(),
				'Qualifiers' => new QualifiersChecker( $this->constraintParameterParser, $this->constraintParameterRenderer ),
				'Mandatory qualifiers' => new MandatoryQualifiersChecker( $this->constraintParameterParser, $this->constraintParameterRenderer ),
				'Range' => new RangeChecker( $this->propertyDataTypeLookup, $this->constraintParameterParser, $rangeCheckerHelper, $this->constraintParameterRenderer ),
				'Diff within range' => new DiffWithinRangeChecker( $this->constraintParameterParser, $rangeCheckerHelper, $this->constraintParameterRenderer ),
				'Type' => new TypeChecker( $this->lookup, $this->constraintParameterParser, $typeCheckerHelper, $this->config ),
				'Value type' => new ValueTypeChecker( $this->lookup, $this->constraintParameterParser, $typeCheckerHelper, $this->config ),
				'Single value' => new SingleValueChecker(),
				'Multi value' => new MultiValueChecker(),
				'Unique value' => new UniqueValueChecker( $this->constraintParameterRenderer, $sparqlHelper ),
				'Format' => new FormatChecker( $this->constraintParameterParser, $this->constraintParameterRenderer, $this->config, $sparqlHelper ),
				'Commons link' => new CommonsLinkChecker( $this->constraintParameterParser, $this->constraintParameterRenderer, $this->titleParser ),
				'One of' => new OneOfChecker( $this->constraintParameterParser, $this->constraintParameterRenderer ),
			];
			$this->constraintCheckerMap += [
				$this->config->get( 'WBQualityConstraintsDistinctValuesConstraintId' ) => $this->constraintCheckerMap['Unique value'],
				$this->config->get( 'WBQualityConstraintsMultiValueConstraintId' ) => $this->constraintCheckerMap['Multi value'],
				$this->config->get( 'WBQualityConstraintsUsedAsQualifierConstraintId' ) => $this->constraintCheckerMap['Qualifier'],
				$this->config->get( 'WBQualityConstraintsSingleValueConstraintId' ) => $this->constraintCheckerMap['Single value'],
				$this->config->get( 'WBQualityConstraintsSymmetricConstraintId' ) => $this->constraintCheckerMap['Symmetric'],
				$this->config->get( 'WBQualityConstraintsTypeConstraintId' ) => $this->constraintCheckerMap['Type'],
				$this->config->get( 'WBQualityConstraintsValueTypeConstraintId' ) => $this->constraintCheckerMap['Value type'],
				$this->config->get( 'WBQualityConstraintsInverseConstraintId' ) => $this->constraintCheckerMap['Inverse'],
				$this->config->get( 'WBQualityConstraintsItemRequiresClaimConstraintId' ) => $this->constraintCheckerMap['Item'],
				$this->config->get( 'WBQualityConstraintsValueRequiresClaimConstraintId' ) => $this->constraintCheckerMap['Target required claim'],
				$this->config->get( 'WBQualityConstraintsConflictsWithConstraintId' ) => $this->constraintCheckerMap['Conflicts with'],
				$this->config->get( 'WBQualityConstraintsOneOfConstraintId' ) => $this->constraintCheckerMap['One of'],
				$this->config->get( 'WBQualityConstraintsMandatoryQualifierConstraintId' ) => $this->constraintCheckerMap['Mandatory qualifiers'],
				$this->config->get( 'WBQualityConstraintsAllowedQualifiersConstraintId' ) => $this->constraintCheckerMap['Qualifiers'],
				$this->config->get( 'WBQualityConstraintsRangeConstraintId' ) => $this->constraintCheckerMap['Range'],
				$this->config->get( 'WBQualityConstraintsDifferenceWithinRangeConstraintId' ) => $this->constraintCheckerMap['Diff within range'],
				$this->config->get( 'WBQualityConstraintsCommonsLinkConstraintId' ) => $this->constraintCheckerMap['Commons link'],
				$this->config->get( 'WBQualityConstraintsFormatConstraintId' ) => $this->constraintCheckerMap['Format'],
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
