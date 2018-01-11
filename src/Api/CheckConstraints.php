<?php

namespace WikibaseQuality\ConstraintReport\Api;

use ApiBase;
use ApiMain;
use IBufferingStatsdDataFactory;
use MediaWiki\MediaWikiServices;
use RequestContext;
use ValueFormatters\FormatterOptions;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Entity\EntityIdParsingException;
use Wikibase\DataModel\Services\Statement\StatementGuidValidator;
use Wikibase\Lib\SnakFormatter;
use Wikibase\Lib\Store\Sql\WikiPageEntityMetaDataLookup;
use Wikibase\Repo\Api\ApiErrorReporter;
use Wikibase\Repo\Api\ApiHelperFactory;
use Wikibase\Repo\Api\ResultBuilder;
use Wikibase\Repo\EntityIdLabelFormatterFactory;
use Wikibase\Repo\WikibaseRepo;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;
use WikibaseQuality\ConstraintReport\ConstraintParameterRenderer;
use WikibaseQuality\ConstraintReport\ConstraintReportFactory;

/**
 * API module that performs constraint check of entities, claims and constraint ID
 *
 * @author Olga Bode
 * @license GNU GPL v2+
 */
class CheckConstraints extends ApiBase {

	const PARAM_ID = 'id';
	const PARAM_CLAIM_ID = 'claimid';
	const PARAM_CONSTRAINT_ID = 'constraintid';

	/**
	 * @var EntityIdParser
	 */
	private $entityIdParser;

	/**
	 * @var StatementGuidValidator
	 */
	private $statementGuidValidator;

	/**
	 * @var ResultBuilder
	 */
	private $resultBuilder;

	/**
	 * @var ApiErrorReporter
	 */
	private $errorReporter;

	/**
	 * @var ResultsBuilder
	 */
	private $resultsBuilder;

	/**
	 * @var IBufferingStatsdDataFactory
	 */
	private $dataFactory;

	/**
	 * Creates new instance from global state.
	 *
	 * @param ApiMain $main
	 * @param string $name
	 * @param string $prefix
	 *
	 * @return self
	 */
	public static function newFromGlobalState( ApiMain $main, $name, $prefix = '' ) {
		$repo = WikibaseRepo::getDefaultInstance();

		$language = $repo->getUserLanguage();
		$formatterOptions = new FormatterOptions();
		$formatterOptions->setOption( SnakFormatter::OPT_LANG, $language->getCode() );
		$valueFormatterFactory = $repo->getValueFormatterFactory();
		$valueFormatter = $valueFormatterFactory->getValueFormatter( SnakFormatter::FORMAT_HTML, $formatterOptions );

		$languageFallbackLabelDescriptionLookupFactory = $repo->getLanguageFallbackLabelDescriptionLookupFactory();
		$labelDescriptionLookup = $languageFallbackLabelDescriptionLookupFactory->newLabelDescriptionLookup( $language );
		$entityIdHtmlLinkFormatterFactory = $repo->getEntityIdHtmlLinkFormatterFactory();
		$entityIdHtmlLinkFormatter = $entityIdHtmlLinkFormatterFactory->getEntityIdFormatter( $labelDescriptionLookup );
		$entityIdLabelFormatterFactory = new EntityIdLabelFormatterFactory();
		$entityIdLabelFormatter = $entityIdLabelFormatterFactory->getEntityIdFormatter( $labelDescriptionLookup );
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$titleParser = MediaWikiServices::getInstance()->getTitleParser();
		$unitConverter = $repo->getUnitConverter();
		$dataFactory = MediaWikiServices::getInstance()->getStatsdDataFactory();
		$constraintParameterRenderer = new ConstraintParameterRenderer(
			$entityIdHtmlLinkFormatter,
			$valueFormatter,
			$config
		);
		$constraintReportFactory = new ConstraintReportFactory(
			$repo->getEntityLookup(),
			$repo->getPropertyDataTypeLookup(),
			$repo->getStatementGuidParser(),
			$config,
			$constraintParameterRenderer,
			new ConstraintParameterParser(
				$config,
				$repo->getBaseDataModelDeserializerFactory(),
				$constraintParameterRenderer
			),
			$repo->getRdfVocabulary(),
			$repo->getEntityIdParser(),
			$titleParser,
			$unitConverter,
			$dataFactory
		);

		$resultsBuilder = new CheckingResultsBuilder(
			$constraintReportFactory->getConstraintChecker(),
			$repo->getEntityTitleLookup(),
			$entityIdLabelFormatter,
			$constraintParameterRenderer,
			$config
		);
		if ( $config->get( 'WBQualityConstraintsCacheCheckConstraintsResults' ) ) {
			$wikiPageEntityMetaDataAccessor = new WikiPageEntityMetaDataLookup(
				$repo->getEntityNamespaceLookup()
			);
			$entityRevisionLookup = $repo->getEntityRevisionLookup();
			$entityIdParser = $repo->getEntityIdParser();
			$resultsBuilder = new CachingResultsBuilder(
				$resultsBuilder,
				ResultsCache::getDefaultInstance(),
				$wikiPageEntityMetaDataAccessor,
				$entityIdParser,
				$config->get( 'WBQualityConstraintsCacheCheckConstraintsTTLSeconds' ),
				[
					$config->get( 'WBQualityConstraintsCommonsLinkConstraintId' ),
					$config->get( 'WBQualityConstraintsTypeConstraintId' ),
					$config->get( 'WBQualityConstraintsValueTypeConstraintId' ),
					$config->get( 'WBQualityConstraintsDistinctValuesConstraintId' ),
				],
				$dataFactory
			);
		}

		return new CheckConstraints(
			$main,
			$name,
			$prefix,
			$repo->getEntityIdParser(),
			$repo->getStatementGuidValidator(),
			$repo->getApiHelperFactory( RequestContext::getMain() ),
			$resultsBuilder,
			$dataFactory
		);
	}

	/**
	 * @param ApiMain $main
	 * @param string $name
	 * @param string $prefix
	 * @param EntityIdParser $entityIdParser
	 * @param StatementGuidValidator $statementGuidValidator
	 * @param ApiHelperFactory $apiHelperFactory
	 * @param ResultsBuilder $resultsBuilder
	 * @param IBufferingStatsdDataFactory $dataFactory
	 */
	public function __construct(
		ApiMain $main,
		$name,
		$prefix = '',
		EntityIdParser $entityIdParser,
		StatementGuidValidator $statementGuidValidator,
		ApiHelperFactory $apiHelperFactory,
		ResultsBuilder $resultsBuilder,
		IBufferingStatsdDataFactory $dataFactory
	) {
		parent::__construct( $main, $name, $prefix );
		$this->entityIdParser = $entityIdParser;
		$this->statementGuidValidator = $statementGuidValidator;
		$this->resultBuilder = $apiHelperFactory->getResultBuilder( $this );
		$this->errorReporter = $apiHelperFactory->getErrorReporter( $this );
		$this->resultsBuilder = $resultsBuilder;
		$this->dataFactory = $dataFactory;
	}

	/**
	 * Evaluates the parameters, runs the requested constraint check, and sets up the result
	 */
	public function execute() {
		$this->dataFactory->increment(
			'wikibase.quality.constraints.api.checkConstraints.execute'
		);

		$params = $this->extractRequestParams();

		$this->validateParameters( $params );
		$entityIds = $this->parseEntityIds( $params );
		$claimIds = $this->parseClaimIds( $params );
		$constraintIDs = $params[self::PARAM_CONSTRAINT_ID];

		$this->getResult()->addValue(
			null,
			$this->getModuleName(),
			$this->resultsBuilder->getResults( $entityIds, $claimIds, $constraintIDs )->getArray()
		);
		// ensure that result contains the given entity IDs even if they have no statements
		foreach ( $entityIds as $entityId ) {
			$this->getResult()->addArrayType(
				[ $this->getModuleName(), $entityId->getSerialization() ],
				'assoc'
			);
		}
		$this->resultBuilder->markSuccess( 1 );
	}

	/**
	 * @param array $params
	 *
	 * @return EntityId[]
	 */
	private function parseEntityIds( array $params ) {
		$ids = $params[self::PARAM_ID];

		if ( $ids === null ) {
			return [];
		} elseif ( $ids === [] ) {
			$this->errorReporter->dieError(
				'If ' . self::PARAM_ID . ' is specified, it must be nonempty.', 'no-data' );
		}

		return array_map( function ( $id ) {
			try {
				return $this->entityIdParser->parse( $id );
			} catch ( EntityIdParsingException $e ) {
				$this->errorReporter->dieError(
					"Invalid id: $id", 'invalid-entity-id', 0, [ self::PARAM_ID => $id ] );
			}
		}, $ids );
	}

	/**
	 * @param array $params
	 *
	 * @return string[]
	 */
	private function parseClaimIds( array $params ) {
		$ids = $params[self::PARAM_CLAIM_ID];

		if ( $ids === null ) {
			return [];
		} elseif ( $ids === [] ) {
			$this->errorReporter->dieError(
				'If ' . self::PARAM_CLAIM_ID . ' is specified, it must be nonempty.', 'no-data' );
		}

		foreach ( $ids as $id ) {
			if ( !$this->statementGuidValidator->validate( $id ) ) {
				$this->errorReporter->dieError(
					"Invalid claim id: $id", 'invalid-guid', 0, [ self::PARAM_CLAIM_ID => $id ] );
			}
		}

		return $ids;
	}

	private function validateParameters( array $params ) {
		if ( $params[self::PARAM_CONSTRAINT_ID] !== null
			 && empty( $params[self::PARAM_CONSTRAINT_ID] )
		) {
			$paramConstraintId = self::PARAM_CONSTRAINT_ID;
			$this->errorReporter->dieError(
				"If $paramConstraintId is specified, it must be nonempty.", 'no-data' );
		}
		if ( $params[self::PARAM_ID] === null && $params[self::PARAM_CLAIM_ID] === null ) {
			$paramId = self::PARAM_ID;
			$paramClaimId = self::PARAM_CLAIM_ID;
			$this->errorReporter->dieError(
				"At least one of $paramId, $paramClaimId must be specified.", 'no-data' );
		}
		// contents of PARAM_ID and PARAM_CLAIM_ID are validated by parse{Entity,Claim}Ids()
	}

	/**
	 * Returns an array of allowed parameters
	 *
	 * @return array @codeCoverageIgnore
	 */
	public function getAllowedParams() {
		return [
			self::PARAM_ID => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_ISMULTI => true,
			],
			self::PARAM_CLAIM_ID => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_ISMULTI => true,
			],
			self::PARAM_CONSTRAINT_ID => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_ISMULTI => true,
			],
		];
	}

	/**
	 * Returns usage examples for this module
	 *
	 * @return array
	 * @codeCoverageIgnore
	 */
	public function getExamplesMessages() {
		return [
			'action=wbcheckconstraints&id=Q5|Q42'
				=> 'apihelp-wbcheckconstraints-example-1',
			'action=wbcheckconstraints&claimid=q42%248419C20C-8EF8-4EC0-80D6-AF1CA55E7557'
				=> 'apihelp-wbcheckconstraints-example-2',
			'action=wbcheckconstraints&format=json&id=Q2&constraintid=P1082%24DA39C2DA-47DA-48FB-8A9A-DA80200FB2DB'
				=> 'apihelp-wbcheckconstraints-example-3',
		];
	}

}
