<?php

namespace WikibaseQuality\ConstraintReport;

use Config;
use MediaWiki\MediaWikiServices;
use ValueFormatters\FormatterOptions;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\Lib\SnakFormatter;
use Wikibase\Rdf\RdfVocabulary;
use Wikibase\Repo\WikibaseRepo;
use WikibaseQuality\ConstraintReport\ConstraintCheck\DelegatingConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintStatementParameterParser;
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
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\TypeSparqlChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\ValueTypeSparqlChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConnectionCheckerHelper;
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
	 * @var ConstraintStatementParameterParser
	 */
	private $constraintStatementParameterParser;

	/**
	 * @var RdfVocabulary
	 */
	private $rdfVocabulary;

	/**
	 * @var EntityIdParser
	 */
	private $entityIdParser;

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
			$constraintParameterRenderer = new ConstraintParameterRenderer(
				$entityIdFormatter,
				$wikibaseRepo->getValueFormatterFactory()->getValueFormatter(
					SnakFormatter::FORMAT_HTML,
					new FormatterOptions()
				)
			);
			$instance = new self(
				$wikibaseRepo->getEntityLookup(),
				$wikibaseRepo->getStatementGuidParser(),
				$config,
				$constraintParameterRenderer,
				new ConstraintStatementParameterParser(
					$config,
					$wikibaseRepo->getBaseDataModelDeserializerFactory(),
					$constraintParameterRenderer
				),
				$wikibaseRepo->getRdfVocabulary(),
				$wikibaseRepo->getEntityIdParser()
			);
		}

		return $instance;
	}

	public function __construct(
		EntityLookup $lookup,
		StatementGuidParser $statementGuidParser,
		Config $config,
		ConstraintParameterRenderer $constraintParameterRenderer,
		ConstraintStatementParameterParser $constraintStatementParameterParser,
		RdfVocabulary $rdfVocabulary,
		EntityIdParser $entityIdParser
	) {
		$this->lookup = $lookup;
		$this->statementGuidParser = $statementGuidParser;
		$this->config = $config;
		$this->constraintParameterRenderer = $constraintParameterRenderer;
		$this->constraintStatementParameterParser = $constraintStatementParameterParser;
		$this->rdfVocabulary = $rdfVocabulary;
		$this->entityIdParser = $entityIdParser;
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
					$this->lookup, $this->constraintStatementParameterParser, $connectionCheckerHelper, $this->constraintParameterRenderer ),
				'Item' => new ItemChecker( $this->lookup, $this->constraintStatementParameterParser, $connectionCheckerHelper, $this->constraintParameterRenderer ),
				'Target required claim' => new TargetRequiredClaimChecker(
					$this->lookup, $this->constraintStatementParameterParser, $connectionCheckerHelper, $this->constraintParameterRenderer ),
				'Symmetric' => new SymmetricChecker( $this->lookup, $constraintParameterParser, $connectionCheckerHelper, $this->constraintParameterRenderer ),
				'Inverse' => new InverseChecker( $this->lookup, $this->constraintStatementParameterParser, $connectionCheckerHelper, $this->constraintParameterRenderer ),
				'Qualifier' => new QualifierChecker(),
				'Qualifiers' => new QualifiersChecker( $this->constraintStatementParameterParser, $this->constraintParameterRenderer ),
				'Mandatory qualifiers' => new MandatoryQualifiersChecker( $this->constraintStatementParameterParser, $this->constraintParameterRenderer ),
				'Range' => new RangeChecker( $constraintParameterParser, $rangeCheckerHelper, $this->constraintParameterRenderer ),
				'Diff within range' => new DiffWithinRangeChecker( $constraintParameterParser, $rangeCheckerHelper, $this->constraintParameterRenderer ),
				'Type' => new TypeChecker( $this->lookup, $this->constraintStatementParameterParser, $typeCheckerHelper, $this->config ),
				'Value type' => new ValueTypeChecker( $this->lookup, $this->constraintStatementParameterParser, $typeCheckerHelper, $this->config ),
				'Single value' => new SingleValueChecker(),
				'Multi value' => new MultiValueChecker(),
				'Unique value' => new UniqueValueChecker( $sparqlHelper ),
				'Format' => new FormatChecker( $constraintParameterParser ),
				'Commons link' => new CommonsLinkChecker( $constraintParameterParser ),
				'One of' => new OneOfChecker( $this->constraintStatementParameterParser, $this->constraintParameterRenderer ),
				'Type (SPARQL)' => new TypeSparqlChecker( $this->lookup, $constraintParameterParser, $sparqlHelper ),
				'Value type (SPARQL)' => new ValueTypeSparqlChecker( $this->lookup, $constraintParameterParser, $sparqlHelper ),
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
