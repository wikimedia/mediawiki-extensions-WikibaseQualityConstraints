<?php

namespace WikibaseQuality\ConstraintReport;

use Config;
use DataValues\DataValueFactory;
use IBufferingStatsdDataFactory;
use MediaWiki\MediaWikiServices;
use TitleParser;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use Wikibase\Lib\Store\EntityNamespaceLookup;
use Wikibase\Lib\Store\Sql\WikiPageEntityMetaDataAccessor;
use Wikibase\Lib\Store\Sql\WikiPageEntityMetaDataLookup;
use Wikibase\Lib\Units\UnitConverter;
use Wikibase\Rdf\RdfVocabulary;
use Wikibase\Repo\WikibaseRepo;
use WikibaseQuality\ConstraintReport\Api\CachingResultsSource;
use WikibaseQuality\ConstraintReport\Api\CheckingResultsSource;
use WikibaseQuality\ConstraintReport\Api\ResultsCache;
use WikibaseQuality\ConstraintReport\Api\ResultsSource;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\CitationNeededChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\EntityTypeChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\IntegerChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\NoBoundsChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\AllowedUnitsChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\NoneOfChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\PropertyScopeChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\ReferenceChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\SingleBestValueChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\DelegatingConstraintChecker;
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
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\RangeCheckerHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\SparqlHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\TypeCheckerHelper;
use Wikibase\DataModel\Services\Statement\StatementGuidParser;

/**
 * Factory for {@link DelegatingConstraintChecker}
 * and {@link ConstraintRepository}.
 *
 * @license GPL-2.0-or-later
 */
class ConstraintReportFactory {

	// services created by this factory

	/**
	 * @var DelegatingConstraintChecker|null
	 */
	private $delegatingConstraintChecker;

	/**
	 * @var ConstraintChecker[]|null
	 */
	private $constraintCheckerMap;

	/**
	 * @var WikiPageEntityMetaDataAccessor|null
	 */
	private $wikiPageEntityMetaDataAccessor;

	/**
	 * @var ResultsSource|null
	 */
	private $resultsSource;

	// services used by this factory

	/**
	 * @var EntityLookup
	 */
	private $lookup;

	/**
	 * @var PropertyDataTypeLookup
	 */
	private $propertyDataTypeLookup;

	/**
	 * @var StatementGuidParser
	 */
	private $statementGuidParser;

	/**
	 * @var Config
	 */
	private $config;

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
	 * @var DataValueFactory
	 */
	private $dataValueFactory;

	/**
	 * @var EntityNamespaceLookup
	 */
	private $entityNamespaceLookup;

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
			$config = MediaWikiServices::getInstance()->getMainConfig();
			$titleParser = MediaWikiServices::getInstance()->getTitleParser();
			$instance = new self(
				$wikibaseRepo->getEntityLookup(),
				$wikibaseRepo->getPropertyDataTypeLookup(),
				$wikibaseRepo->getStatementGuidParser(),
				$config,
				$wikibaseRepo->getRdfVocabulary(),
				$wikibaseRepo->getEntityIdParser(),
				$titleParser,
				$wikibaseRepo->getUnitConverter(),
				$wikibaseRepo->getDataValueFactory(),
				$wikibaseRepo->getEntityNamespaceLookup(),
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
		RdfVocabulary $rdfVocabulary,
		EntityIdParser $entityIdParser,
		TitleParser $titleParser,
		UnitConverter $unitConverter = null,
		DataValueFactory $dataValueFactory,
		EntityNamespaceLookup $entityNamespaceLookup,
		IBufferingStatsdDataFactory $dataFactory
	) {
		$this->lookup = $lookup;
		$this->propertyDataTypeLookup = $propertyDataTypeLookup;
		$this->statementGuidParser = $statementGuidParser;
		$this->config = $config;
		$this->rdfVocabulary = $rdfVocabulary;
		$this->entityIdParser = $entityIdParser;
		$this->titleParser = $titleParser;
		$this->unitConverter = $unitConverter;
		$this->dataValueFactory = $dataValueFactory;
		$this->entityNamespaceLookup = $entityNamespaceLookup;
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
				ConstraintsServices::getConstraintLookup(),
				ConstraintsServices::getConstraintParameterParser(),
				$this->statementGuidParser,
				ConstraintsServices::getLoggingHelper(),
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
					ConstraintsServices::getViolationMessageSerializer(),
					ConstraintsServices::getViolationMessageDeserializer(),
					$this->dataFactory
				);
			} else {
				$sparqlHelper = null;
			}
			$typeCheckerHelper = new TypeCheckerHelper(
				$this->lookup,
				$this->config,
				$sparqlHelper,
				$this->dataFactory
			);

			$this->constraintCheckerMap = [
				$this->config->get( 'WBQualityConstraintsConflictsWithConstraintId' )
					=> new ConflictsWithChecker(
						$this->lookup,
						ConstraintsServices::getConstraintParameterParser(),
						$connectionCheckerHelper
					),
				$this->config->get( 'WBQualityConstraintsItemRequiresClaimConstraintId' )
					=> new ItemChecker(
						$this->lookup,
						ConstraintsServices::getConstraintParameterParser(),
						$connectionCheckerHelper
					),
				$this->config->get( 'WBQualityConstraintsValueRequiresClaimConstraintId' )
					=> new TargetRequiredClaimChecker(
						$this->lookup,
						ConstraintsServices::getConstraintParameterParser(),
						$connectionCheckerHelper
					),
				$this->config->get( 'WBQualityConstraintsSymmetricConstraintId' )
					=> new SymmetricChecker(
						$this->lookup,
						$connectionCheckerHelper
					),
				$this->config->get( 'WBQualityConstraintsInverseConstraintId' )
					=> new InverseChecker(
						$this->lookup,
						ConstraintsServices::getConstraintParameterParser(),
						$connectionCheckerHelper
					),
				$this->config->get( 'WBQualityConstraintsUsedAsQualifierConstraintId' )
					=> new QualifierChecker(),
				$this->config->get( 'WBQualityConstraintsAllowedQualifiersConstraintId' )
					=> new QualifiersChecker(
						ConstraintsServices::getConstraintParameterParser()
					),
				$this->config->get( 'WBQualityConstraintsMandatoryQualifierConstraintId' )
					=> new MandatoryQualifiersChecker(
						ConstraintsServices::getConstraintParameterParser()
					),
				$this->config->get( 'WBQualityConstraintsRangeConstraintId' )
					=> new RangeChecker(
						$this->propertyDataTypeLookup,
						ConstraintsServices::getConstraintParameterParser(),
						$rangeCheckerHelper
					),
				$this->config->get( 'WBQualityConstraintsDifferenceWithinRangeConstraintId' )
					=> new DiffWithinRangeChecker(
						ConstraintsServices::getConstraintParameterParser(),
						$rangeCheckerHelper,
						$this->config
					),
				$this->config->get( 'WBQualityConstraintsTypeConstraintId' )
					=> new TypeChecker(
						$this->lookup,
						ConstraintsServices::getConstraintParameterParser(),
						$typeCheckerHelper,
						$this->config
					),
				$this->config->get( 'WBQualityConstraintsValueTypeConstraintId' )
					=> new ValueTypeChecker(
						$this->lookup,
						ConstraintsServices::getConstraintParameterParser(),
						$typeCheckerHelper,
						$this->config
					),
				$this->config->get( 'WBQualityConstraintsSingleValueConstraintId' )
					=> new SingleValueChecker( ConstraintsServices::getConstraintParameterParser() ),
				$this->config->get( 'WBQualityConstraintsMultiValueConstraintId' )
					=> new MultiValueChecker( ConstraintsServices::getConstraintParameterParser() ),
				$this->config->get( 'WBQualityConstraintsDistinctValuesConstraintId' )
					=> new UniqueValueChecker(
						$sparqlHelper
					),
				$this->config->get( 'WBQualityConstraintsFormatConstraintId' )
					=> new FormatChecker(
						ConstraintsServices::getConstraintParameterParser(),
						$this->config,
						$sparqlHelper
					),
				$this->config->get( 'WBQualityConstraintsCommonsLinkConstraintId' )
					=> new CommonsLinkChecker(
						ConstraintsServices::getConstraintParameterParser(),
						$this->titleParser
					),
				$this->config->get( 'WBQualityConstraintsOneOfConstraintId' )
					=> new OneOfChecker(
						ConstraintsServices::getConstraintParameterParser()
					),
				$this->config->get( 'WBQualityConstraintsUsedForValuesOnlyConstraintId' )
					=> new ValueOnlyChecker(),
				$this->config->get( 'WBQualityConstraintsUsedAsReferenceConstraintId' )
					=> new ReferenceChecker(),
				$this->config->get( 'WBQualityConstraintsNoBoundsConstraintId' )
					=> new NoBoundsChecker(),
				$this->config->get( 'WBQualityConstraintsAllowedUnitsConstraintId' )
					=> new AllowedUnitsChecker(
						ConstraintsServices::getConstraintParameterParser(),
						$this->unitConverter
					),
				$this->config->get( 'WBQualityConstraintsSingleBestValueConstraintId' )
					=> new SingleBestValueChecker( ConstraintsServices::getConstraintParameterParser() ),
				$this->config->get( 'WBQualityConstraintsAllowedEntityTypesConstraintId' )
					=> new EntityTypeChecker(
						ConstraintsServices::getConstraintParameterParser()
					),
				$this->config->get( 'WBQualityConstraintsNoneOfConstraintId' )
					=> new NoneOfChecker(
						ConstraintsServices::getConstraintParameterParser()
					),
				$this->config->get( 'WBQualityConstraintsIntegerConstraintId' )
					=> new IntegerChecker(),
				$this->config->get( 'WBQualityConstraintsCitationNeededConstraintId' )
					=> new CitationNeededChecker(),
				$this->config->get( 'WBQualityConstraintsPropertyScopeConstraintId' )
					=> new PropertyScopeChecker(
						ConstraintsServices::getConstraintParameterParser()
					),
			];
		}

		return $this->constraintCheckerMap;
	}

	/**
	 * @return WikiPageEntityMetaDataAccessor
	 */
	public function getWikiPageEntityMetaDataAccessor() {
		if ( $this->wikiPageEntityMetaDataAccessor === null ) {
			$this->wikiPageEntityMetaDataAccessor = new WikiPageEntityMetaDataLookup(
				$this->entityNamespaceLookup
			);
		}

		return $this->wikiPageEntityMetaDataAccessor;
	}

	/**
	 * @return ResultsSource
	 */
	public function getResultsSource() {
		if ( $this->resultsSource === null ) {
			$this->resultsSource = new CheckingResultsSource(
				$this->getConstraintChecker()
			);

			if ( $this->config->get( 'WBQualityConstraintsCacheCheckConstraintsResults' ) ) {
				$this->resultsSource = new CachingResultsSource(
					$this->resultsSource,
					ResultsCache::getDefaultInstance(),
					ConstraintsServices::getCheckResultSerializer(),
					ConstraintsServices::getCheckResultDeserializer(),
					$this->getWikiPageEntityMetaDataAccessor(),
					$this->entityIdParser,
					$this->config->get( 'WBQualityConstraintsCacheCheckConstraintsTTLSeconds' ),
					$this->getPossiblyStaleConstraintTypes(),
					$this->config->get( 'WBQualityConstraintsCacheCheckConstraintsMaximumRevisionIds' ),
					ConstraintsServices::getLoggingHelper()
				);
			}
		}

		return $this->resultsSource;
	}

	/**
	 * @return string[]
	 */
	public function getPossiblyStaleConstraintTypes() {
		return [
			$this->config->get( 'WBQualityConstraintsCommonsLinkConstraintId' ),
			$this->config->get( 'WBQualityConstraintsTypeConstraintId' ),
			$this->config->get( 'WBQualityConstraintsValueTypeConstraintId' ),
			$this->config->get( 'WBQualityConstraintsDistinctValuesConstraintId' ),
		];
	}

}
