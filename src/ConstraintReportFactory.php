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
				ConstraintsServices::getDelegatingConstraintChecker()
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
