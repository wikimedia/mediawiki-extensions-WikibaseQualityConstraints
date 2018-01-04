<?php

namespace WikibaseQuality\ConstraintReport;

use Config;
use IBufferingStatsdDataFactory;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use TitleParser;
use ValueFormatters\FormatterOptions;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use Wikibase\Lib\SnakFormatter;
use Wikibase\Lib\Units\UnitConverter;
use Wikibase\Rdf\RdfVocabulary;
use Wikibase\Repo\WikibaseRepo;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\ReferenceChecker;
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
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\ValueOnlyChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConnectionCheckerHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\LoggingHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\RangeCheckerHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\SparqlHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\TypeCheckerHelper;
use Wikibase\DataModel\Services\Statement\StatementGuidParser;

/**
 * Factory for {@link DelegatingConstraintChecker}
 * and {@link ConstraintRepository}.
 *
 * @license GNU GPL v2+
 */
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
	 * @var UnitConverter|null
	 */
	private $unitConverter;

	/**
	 * @var IBufferingStatsdDataFactory
	 */
	private $dataFactory;

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
				$titleParser,
				$wikibaseRepo->getUnitConverter(),
				MediaWikiServices::getInstance()->getStatsdDataFactory()
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
		TitleParser $titleParser,
		UnitConverter $unitConverter = null,
		IBufferingStatsdDataFactory $dataFactory
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
		$this->unitConverter = $unitConverter;
		$this->dataFactory = $dataFactory;
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
					$this->dataFactory,
					LoggerFactory::getInstance( 'WikibaseQualityConstraints' ),
					$this->config
				),
				$this->config->get( 'WBQualityConstraintsCheckQualifiers' ),
				$this->config->get( 'WBQualityConstraintsCheckReferences' ),
				$this->config->get( 'WBQualityConstraintsPropertiesWithViolatingQualifiers' )
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
			$rangeCheckerHelper = new RangeCheckerHelper( $this->config, $this->unitConverter );
			if ( $this->config->get( 'WBQualityConstraintsSparqlEndpoint' ) !== '' ) {
				$sparqlHelper = new SparqlHelper(
					$this->config,
					$this->rdfVocabulary,
					$this->entityIdParser,
					$this->propertyDataTypeLookup,
					MediaWikiServices::getInstance()->getMainWANObjectCache(),
					$this->dataFactory
				);
			} else {
				$sparqlHelper = null;
			}
			$typeCheckerHelper = new TypeCheckerHelper(
				$this->lookup,
				$this->config,
				$this->constraintParameterRenderer,
				$sparqlHelper,
				$this->dataFactory
			);

			$this->constraintCheckerMap = [
				$this->config->get( 'WBQualityConstraintsConflictsWithConstraintId' )
					=> new ConflictsWithChecker(
						$this->lookup,
						$this->constraintParameterParser,
						$connectionCheckerHelper,
						$this->constraintParameterRenderer
					),
				$this->config->get( 'WBQualityConstraintsItemRequiresClaimConstraintId' )
					=> new ItemChecker(
						$this->lookup,
						$this->constraintParameterParser,
						$connectionCheckerHelper,
						$this->constraintParameterRenderer
					),
				$this->config->get( 'WBQualityConstraintsValueRequiresClaimConstraintId' )
					=> new TargetRequiredClaimChecker(
						$this->lookup,
						$this->constraintParameterParser,
						$connectionCheckerHelper,
						$this->constraintParameterRenderer
					),
				$this->config->get( 'WBQualityConstraintsSymmetricConstraintId' )
					=> new SymmetricChecker(
						$this->lookup,
						$connectionCheckerHelper,
						$this->constraintParameterRenderer
					),
				$this->config->get( 'WBQualityConstraintsInverseConstraintId' )
					=> new InverseChecker(
						$this->lookup,
						$this->constraintParameterParser,
						$connectionCheckerHelper,
						$this->constraintParameterRenderer
					),
				$this->config->get( 'WBQualityConstraintsUsedAsQualifierConstraintId' )
					=> new QualifierChecker(),
				$this->config->get( 'WBQualityConstraintsAllowedQualifiersConstraintId' )
					=> new QualifiersChecker(
						$this->constraintParameterParser,
						$this->constraintParameterRenderer
					),
				$this->config->get( 'WBQualityConstraintsMandatoryQualifierConstraintId' )
					=> new MandatoryQualifiersChecker(
						$this->constraintParameterParser,
						$this->constraintParameterRenderer
					),
				$this->config->get( 'WBQualityConstraintsRangeConstraintId' )
					=> new RangeChecker(
						$this->propertyDataTypeLookup,
						$this->constraintParameterParser,
						$rangeCheckerHelper,
						$this->constraintParameterRenderer
					),
				$this->config->get( 'WBQualityConstraintsDifferenceWithinRangeConstraintId' )
					=> new DiffWithinRangeChecker(
						$this->constraintParameterParser,
						$rangeCheckerHelper,
						$this->constraintParameterRenderer,
						$this->config
					),
				$this->config->get( 'WBQualityConstraintsTypeConstraintId' )
					=> new TypeChecker(
						$this->lookup,
						$this->constraintParameterParser,
						$typeCheckerHelper,
						$this->config
					),
				$this->config->get( 'WBQualityConstraintsValueTypeConstraintId' )
					=> new ValueTypeChecker(
						$this->lookup,
						$this->constraintParameterParser,
						$this->constraintParameterRenderer,
						$typeCheckerHelper,
						$this->config
					),
				$this->config->get( 'WBQualityConstraintsSingleValueConstraintId' )
					=> new SingleValueChecker(),
				$this->config->get( 'WBQualityConstraintsMultiValueConstraintId' )
					=> new MultiValueChecker(),
				$this->config->get( 'WBQualityConstraintsDistinctValuesConstraintId' )
					=> new UniqueValueChecker(
						$this->constraintParameterRenderer,
						$sparqlHelper
					),
				$this->config->get( 'WBQualityConstraintsFormatConstraintId' )
					=> new FormatChecker(
						$this->constraintParameterParser,
						$this->constraintParameterRenderer,
						$this->config,
						$sparqlHelper
					),
				$this->config->get( 'WBQualityConstraintsCommonsLinkConstraintId' )
					=> new CommonsLinkChecker(
						$this->constraintParameterParser,
						$this->constraintParameterRenderer,
						$this->titleParser
					),
				$this->config->get( 'WBQualityConstraintsOneOfConstraintId' )
					=> new OneOfChecker(
						$this->constraintParameterParser,
						$this->constraintParameterRenderer
					),
				$this->config->get( 'WBQualityConstraintsUsedForValuesOnlyConstraintId' )
					=> new ValueOnlyChecker(),
				$this->config->get( 'WBQualityConstraintsUsedAsReferenceConstraintId' )
					=> new ReferenceChecker(),
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
