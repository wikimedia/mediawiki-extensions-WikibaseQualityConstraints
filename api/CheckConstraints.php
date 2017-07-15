<?php

namespace WikibaseQuality\ConstraintReport\Api;

use ApiBase;
use ApiMain;
use ApiResult;
use Config;
use MediaWiki\MediaWikiServices;
use RequestContext;
use ValueFormatters\FormatterOptions;
use Wikibase\Repo\ChangeOp\StatementChangeOpFactory;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Entity\EntityIdParsingException;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Services\EntityId\EntityIdFormatter;
use Wikibase\DataModel\Services\Statement\StatementGuidParser;
use Wikibase\DataModel\Services\Statement\StatementGuidParsingException;
use Wikibase\DataModel\Services\Statement\StatementGuidValidator;
use Wikibase\Lib\SnakFormatter;
use Wikibase\Lib\Store\EntityTitleLookup;
use Wikibase\Repo\Api\ApiErrorReporter;
use Wikibase\Repo\Api\ApiHelperFactory;
use Wikibase\Repo\Api\ResultBuilder;
use Wikibase\Repo\EntityIdLabelFormatterFactory;
use Wikibase\Repo\WikibaseRepo;
use WikibaseQuality\ConstraintReport\ConstraintCheck\DelegatingConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
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
	 *
	 * @var EntityIdParser
	 */
	private $entityIdParser;

	/**
	 *
	 * @var StatementGuidValidator
	 */
	private $statementGuidValidator;

	/**
	 *
	 * @var StatementGuidParser
	 */
	private $statementGuidParser;

	/**
	 *
	 * @var DelegatingConstraintChecker
	 */
	private $delegatingConstraintChecker;

	/**
	 * @var ResultBuilder
	 */
	private $resultBuilder;

	/**
	 *
	 * @var ApiErrorReporter
	 */
	private $errorReporter;

	/**
	 *
	 * @var StatementChangeOpFactory
	 */
	private $statementChangeOpFactory;

	/**
	 *
	 * @var ConstraintParameterRenderer
	 */
	private $constraintParameterRenderer;

	/**
	 * @var EntityTitleLookup
	 */
	private $entityTitleLookup;

	/**
	 * @var EntityIdFormatter
	 */
	private $entityIdLabelFormatter;

	/**
	 * @var Config
	 */
	private $config;

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
		$statementGuidParser = $repo->getStatementGuidParser();
		$constraintParameterRenderer = new ConstraintParameterRenderer( $entityIdHtmlLinkFormatter, $valueFormatter );
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$titleParser = MediaWikiServices::getInstance()->getTitleParser();
		$constraintReportFactory = new ConstraintReportFactory(
			$repo->getEntityLookup(),
			$repo->getPropertyDataTypeLookup(),
			$statementGuidParser,
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
			$statementGuidParser
		);

		return new CheckConstraints( $main, $name, $prefix, $repo->getEntityIdParser(),
			$repo->getStatementGuidValidator(), $statementGuidParser, $constraintReportFactory->getConstraintChecker(),
			$constraintParameterRenderer,
			$repo->getApiHelperFactory( RequestContext::getMain() ),
			$repo->getEntityTitleLookup(),
			$entityIdLabelFormatter,
			$config );
	}

	/**
	 * @param ApiMain $main
	 * @param string $name
	 * @param string $prefix
	 * @param EntityIdParser $entityIdParser
	 * @param StatementGuidValidator $statementGuidValidator
	 * @param StatementGuidParser $statementGuidParser
	 * @param DelegatingConstraintChecker $delegatingConstraintChecker
	 * @param ConstraintParameterRenderer $constraintParameterRenderer
	 * @param ApiHelperFactory $apiHelperFactory
	 * @param EntityTitleLookup $entityTitleLookup
	 * @param EntityIdFormatter $entityIdLabelFormatter
	 * @param Config $config
	 */
	public function __construct( ApiMain $main, $name, $prefix = '', EntityIdParser $entityIdParser,
		StatementGuidValidator $statementGuidValidator,
		StatementGuidParser $statementGuidParser,
		DelegatingConstraintChecker $delegatingConstraintChecker,
		ConstraintParameterRenderer $constraintParameterRenderer,
		ApiHelperFactory $apiHelperFactory,
		EntityTitleLookup $entityTitleLookup,
		EntityIdFormatter $entityIdLabelFormatter,
		Config $config
	) {
		parent::__construct( $main, $name, $prefix );

		$repo = WikibaseRepo::getDefaultInstance();
		$changeOpFactoryProvider = $repo->getChangeOpFactoryProvider();

		$this->statementChangeOpFactory = $changeOpFactoryProvider->getStatementChangeOpFactory();

		$this->entityIdParser = $entityIdParser;
		$this->statementGuidValidator = $statementGuidValidator;
		$this->statementGuidParser = $statementGuidParser;
		$this->delegatingConstraintChecker = $delegatingConstraintChecker;
		$this->resultBuilder = $apiHelperFactory->getResultBuilder( $this );
		$this->errorReporter = $apiHelperFactory->getErrorReporter( $this );

		$this->constraintParameterRenderer = $constraintParameterRenderer;
		$this->entityTitleLookup = $entityTitleLookup;
		$this->entityIdLabelFormatter = $entityIdLabelFormatter;
		$this->config = $config;
	}

	/**
	 * Evaluates the parameters, runs the requested constraint check, and sets up the result
	 */
	public function execute() {
		MediaWikiServices::getInstance()->getStatsdDataFactory()
			->increment( 'wikibase.quality.constraints.api.checkConstraints.execute' );

		$params = $this->extractRequestParams();
		$output = [];

		$this->validateParameters( $params );
		$entityIds = $this->parseEntityIds( $params );
		$claimIds = $this->parseClaimIds( $params );

		$output = array_merge( $output, $this->checkItems( $entityIds, $params[self::PARAM_CONSTRAINT_ID] ) );
		$output = array_merge( $output, $this->checkClaimIds( $claimIds, $params[self::PARAM_CONSTRAINT_ID] ) );

		$this->getResult()->addValue( null, $this->getModuleName(), $this->buildResult( $output, $params[self::PARAM_ID] ) );
		$this->resultBuilder->markSuccess( 1 );
	}

	private function checkItems( array $entityIds, $constraintIds ) {
		$checkResults = [];

		foreach ( $entityIds as $entityId ) {
			$currentCheckResults = $this->delegatingConstraintChecker->checkAgainstConstraintsOnEntityId(
				$entityId, $constraintIds );
			if ( $currentCheckResults ) {
				$checkResults = array_merge( $checkResults, $currentCheckResults );
			}
		}

		return $checkResults;
	}

	private function checkClaimIds( array $claimIds, $constraintIds ) {
		$checkResults = [];

		foreach ( $claimIds as $claimId ) {
			$currentCheckResults = $this->delegatingConstraintChecker->checkAgainstConstraintsOnClaimId(
				$claimId, $constraintIds );
			if ( $currentCheckResults ) {
				$checkResults = array_merge( $checkResults, $currentCheckResults );
			}
		}

		return $checkResults;
	}

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
	 * Converts a flat list of constraint check results
	 * to a nested array structure which can be stored in the ApiResult.
	 * The array is keyed by entity ID, then by property ID,
	 * then by claim ID, and then contains a list of individual results:
	 * { "Q1": { "P1": { "Q1$1a2b...": [ { "status": "compliance", ... }, { ... } ] } } }
	 *
	 * @param CheckResult[] $checkResults
	 * @param string[]|null $entityIds optionally, a list of entity IDs that should be present in the output even if there are no check results for them
	 *
	 * @return array
	 */
	private function buildResult( array $checkResults, $entityIds = null ) {
		$constraintReport = [];
		ApiResult::setArrayType( $constraintReport, 'assoc' );

		// ensure that the report contains the given IDs even if there are no results for them
		if ( $entityIds ) {
			foreach ( $entityIds as $entityId ) {
				$constraintReport[$entityId] = [];
				ApiResult::setArrayType( $constraintReport[$entityId], 'assoc' );
			}
		}

		foreach ( $checkResults as $checkResult ) {
			$statement = $checkResult->getStatement();

			$entityId = $checkResult->getEntityId()->getSerialization();
			$propertyId = $checkResult->getPropertyId()->getSerialization();
			$claimId = $statement->getGuid();
			$constraintId = $checkResult->getConstraint()->getConstraintId();
			$typeItemId = $checkResult->getConstraint()->getConstraintTypeItemId();

			$title = $this->entityTitleLookup->getTitleForId( $checkResult->getPropertyId() );
			try {
				$statementGuid = $this->statementGuidParser->parse( $constraintId );
				// constraint statement
				$typeLabel = $this->entityIdLabelFormatter->formatEntityId( new ItemId( $typeItemId ) );
				// TODO link to the statement when possible (T169224)
				$link = $title->getFullUrl() . '#' . $this->config->get( 'WBQualityConstraintsPropertyConstraintId' );
			} catch ( StatementGuidParsingException $e ) {
				// constraint template on talk page
				$typeLabel = htmlspecialchars( $typeItemId );
				$link = $title->getTalkPage()->getFullUrl();
			}

			$result = [
				'status' => $checkResult->getStatus(),
				'property' => $checkResult->getPropertyId()->getSerialization(),
				'claim' => $checkResult->getStatement()->getGuid(),
				'constraint' => [
					'id' => $checkResult->getConstraintId(),
					'type' => $typeItemId,
					'typeLabel' => $typeLabel,
					'link' => $link,
					'detail' => $checkResult->getParameters(),
					'detailHTML' => $this->constraintParameterRenderer->formatParameters( $checkResult->getParameters() )
				]
			];
			if ( $checkResult->getMessage() ) {
				$result['message-html'] = $checkResult->getMessage();
			}

			$constraintReport[$entityId][$propertyId][$claimId][] = $result;
		}
		return $constraintReport;
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
						ApiBase::PARAM_ISMULTI => true
				],
				self::PARAM_CLAIM_ID => [
						ApiBase::PARAM_TYPE => 'string',
						ApiBase::PARAM_ISMULTI => true
				],
				self::PARAM_CONSTRAINT_ID => [
						ApiBase::PARAM_TYPE => 'string',
						ApiBase::PARAM_ISMULTI => true
				]
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
			'action=wbcheckconstraints&claimid=Q42%248419C20C-8EF8-4EC0-80D6-AF1CA55E7557'
				=> 'apihelp-wbcheckconstraints-example-2'
			// TODO add more examples, at least one for the constraintid parameter
		];
	}

}
