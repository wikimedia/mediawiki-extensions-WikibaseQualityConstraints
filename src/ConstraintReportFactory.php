<?php

namespace WikibaseQuality\ConstraintReport;

use Config;
use DataValues\DataValueFactory;
use MediaWiki\MediaWikiServices;
use TitleParser;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use Wikibase\Lib\Store\EntityNamespaceLookup;
use Wikibase\Lib\Store\Sql\WikiPageEntityMetaDataAccessor;
use Wikibase\Lib\Store\Sql\WikiPageEntityMetaDataLookup;
use Wikibase\Lib\Units\UnitConverter;
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
				$wikibaseRepo->getEntityIdParser(),
				$titleParser,
				$wikibaseRepo->getUnitConverter(),
				$wikibaseRepo->getDataValueFactory(),
				$wikibaseRepo->getEntityNamespaceLookup()
			);
		}

		return $instance;
	}

	public function __construct(
		EntityLookup $lookup,
		PropertyDataTypeLookup $propertyDataTypeLookup,
		StatementGuidParser $statementGuidParser,
		Config $config,
		EntityIdParser $entityIdParser,
		TitleParser $titleParser,
		UnitConverter $unitConverter = null,
		DataValueFactory $dataValueFactory,
		EntityNamespaceLookup $entityNamespaceLookup
	) {
		$this->lookup = $lookup;
		$this->propertyDataTypeLookup = $propertyDataTypeLookup;
		$this->statementGuidParser = $statementGuidParser;
		$this->config = $config;
		$this->entityIdParser = $entityIdParser;
		$this->titleParser = $titleParser;
		$this->unitConverter = $unitConverter;
		$this->dataValueFactory = $dataValueFactory;
		$this->entityNamespaceLookup = $entityNamespaceLookup;
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
			$this->constraintCheckerMap = [
				$this->config->get( 'WBQualityConstraintsConflictsWithConstraintId' )
					=> new ConflictsWithChecker(
						$this->lookup,
						ConstraintsServices::getConstraintParameterParser(),
						ConstraintsServices::getConnectionCheckerHelper()
					),
				$this->config->get( 'WBQualityConstraintsItemRequiresClaimConstraintId' )
					=> new ItemChecker(
						$this->lookup,
						ConstraintsServices::getConstraintParameterParser(),
						ConstraintsServices::getConnectionCheckerHelper()
					),
				$this->config->get( 'WBQualityConstraintsValueRequiresClaimConstraintId' )
					=> new TargetRequiredClaimChecker(
						$this->lookup,
						ConstraintsServices::getConstraintParameterParser(),
						ConstraintsServices::getConnectionCheckerHelper()
					),
				$this->config->get( 'WBQualityConstraintsSymmetricConstraintId' )
					=> new SymmetricChecker(
						$this->lookup,
						ConstraintsServices::getConnectionCheckerHelper()
					),
				$this->config->get( 'WBQualityConstraintsInverseConstraintId' )
					=> new InverseChecker(
						$this->lookup,
						ConstraintsServices::getConstraintParameterParser(),
						ConstraintsServices::getConnectionCheckerHelper()
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
						ConstraintsServices::getRangeCheckerHelper()
					),
				$this->config->get( 'WBQualityConstraintsDifferenceWithinRangeConstraintId' )
					=> new DiffWithinRangeChecker(
						ConstraintsServices::getConstraintParameterParser(),
						ConstraintsServices::getRangeCheckerHelper(),
						$this->config
					),
				$this->config->get( 'WBQualityConstraintsTypeConstraintId' )
					=> new TypeChecker(
						$this->lookup,
						ConstraintsServices::getConstraintParameterParser(),
						ConstraintsServices::getTypeCheckerHelper(),
						$this->config
					),
				$this->config->get( 'WBQualityConstraintsValueTypeConstraintId' )
					=> new ValueTypeChecker(
						$this->lookup,
						ConstraintsServices::getConstraintParameterParser(),
						ConstraintsServices::getTypeCheckerHelper(),
						$this->config
					),
				$this->config->get( 'WBQualityConstraintsSingleValueConstraintId' )
					=> new SingleValueChecker( ConstraintsServices::getConstraintParameterParser() ),
				$this->config->get( 'WBQualityConstraintsMultiValueConstraintId' )
					=> new MultiValueChecker( ConstraintsServices::getConstraintParameterParser() ),
				$this->config->get( 'WBQualityConstraintsDistinctValuesConstraintId' )
					=> new UniqueValueChecker(
						ConstraintsServices::getSparqlHelper()
					),
				$this->config->get( 'WBQualityConstraintsFormatConstraintId' )
					=> new FormatChecker(
						ConstraintsServices::getConstraintParameterParser(),
						$this->config,
						ConstraintsServices::getSparqlHelper()
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
