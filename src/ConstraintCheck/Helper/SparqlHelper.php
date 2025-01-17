<?php

declare( strict_types = 1 );

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Helper;

use DataValues\Geo\Values\GlobeCoordinateValue;
use DataValues\TimeValue;
use DataValues\UnboundedQuantityValue;
use DateInterval;
use InvalidArgumentException;
use MapCacheLRU;
use MediaWiki\Config\Config;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Json\FormatJson;
use MWHttpRequest;
use UnexpectedValueException;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Entity\EntityIdParsingException;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\NumericPropertyId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\Repo\Rdf\NullDedupeBag;
use Wikibase\Repo\Rdf\NullEntityMentionListener;
use Wikibase\Repo\Rdf\RdfVocabulary;
use Wikibase\Repo\Rdf\ValueSnakRdfBuilderFactory;
use WikibaseQuality\ConstraintReport\Api\ExpiryLock;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachedBool;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachedEntityIds;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachedQueryResults;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachingMetadata;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\Metadata;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\Context;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessage;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageDeserializer;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageSerializer;
use WikibaseQuality\ConstraintReport\Role;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\Purtle\TurtleRdfWriter;
use Wikimedia\Stats\IBufferingStatsdDataFactory;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * Class for running a SPARQL query on some endpoint and getting the results.
 *
 * @author Lucas Werkmeister
 * @license GPL-2.0-or-later
 */
class SparqlHelper {

	private RdfVocabulary $rdfVocabulary;

	/**
	 * A copy of {@link self::$rdfVocabulary}, but with
	 * {@link RdfVocabulary::$normalizedPropertyValueNamespace} set to all-null values;
	 * this is needed so that when {@link self::getSnakPredicateAndObject()} formats values,
	 * {@link \Wikibase\Repo\Rdf\Values\ExternalIdentifierRdfBuilder ExternalIdentifierRdfBuilder}
	 * won’t emit additional triples for the normalized URI.
	 */
	private RdfVocabulary $rdfVocabularyWithoutNormalization;

	private ValueSnakRdfBuilderFactory $valueSnakRdfBuilderFactory;

	/**
	 * @var string[]
	 */
	private array $entityPrefixes;

	private string $prefixes;

	private EntityIdParser $entityIdParser;

	private PropertyDataTypeLookup $propertyDataTypeLookup;

	private WANObjectCache $cache;

	private ViolationMessageSerializer $violationMessageSerializer;

	private ViolationMessageDeserializer $violationMessageDeserializer;

	private IBufferingStatsdDataFactory $dataFactory;

	private LoggingHelper $loggingHelper;

	private string $defaultUserAgent;

	private ExpiryLock $throttlingLock;

	/**
	 * @var int stands for: No Retry-After header-field was sent back
	 */
	private const NO_RETRY_AFTER = -1;
	/**
	 * @var int stands for: Empty Retry-After header-field was sent back
	 */
	private const EMPTY_RETRY_AFTER = -2;
	/**
	 * @var int stands for: Invalid Retry-After header-field was sent back
	 * link a string
	 */
	private const INVALID_RETRY_AFTER = -3;
	/**
	 * @var string ID on which the lock is applied on
	 */
	public const EXPIRY_LOCK_ID = 'SparqlHelper.runQuery';

	/**
	 * @var int HTTP response code for too many requests
	 */
	private const HTTP_TOO_MANY_REQUESTS = 429;

	private HttpRequestFactory $requestFactory;

	private string $primaryEndpoint;

	/**
	 * @var string[]
	 */
	private array $additionalEndpoints;

	private int $maxQueryTimeMillis;

	private PropertyId $subclassOfId;

	private int $cacheMapSize;

	/**
	 * @var string[]
	 */
	private array $timeoutExceptionClasses;

	private bool $sparqlHasWikibaseSupport;

	private int $sparqlThrottlingFallbackDuration;

	public function __construct(
		Config $config,
		RdfVocabulary $rdfVocabulary,
		ValueSnakRdfBuilderFactory $valueSnakRdfBuilderFactory,
		EntityIdParser $entityIdParser,
		PropertyDataTypeLookup $propertyDataTypeLookup,
		WANObjectCache $cache,
		ViolationMessageSerializer $violationMessageSerializer,
		ViolationMessageDeserializer $violationMessageDeserializer,
		IBufferingStatsdDataFactory $dataFactory,
		ExpiryLock $throttlingLock,
		LoggingHelper $loggingHelper,
		$defaultUserAgent,
		HttpRequestFactory $requestFactory
	) {
		$this->rdfVocabulary = $rdfVocabulary;
		$this->valueSnakRdfBuilderFactory = $valueSnakRdfBuilderFactory;
		$this->entityIdParser = $entityIdParser;
		$this->propertyDataTypeLookup = $propertyDataTypeLookup;
		$this->cache = $cache;
		$this->violationMessageSerializer = $violationMessageSerializer;
		$this->violationMessageDeserializer = $violationMessageDeserializer;
		$this->dataFactory = $dataFactory;
		$this->throttlingLock = $throttlingLock;
		$this->loggingHelper = $loggingHelper;
		$this->defaultUserAgent = $defaultUserAgent;
		$this->requestFactory = $requestFactory;
		$this->entityPrefixes = [];
		foreach ( $rdfVocabulary->entityNamespaceNames as $namespaceName ) {
			$this->entityPrefixes[] = $rdfVocabulary->getNamespaceURI( $namespaceName );
		}

		$this->primaryEndpoint = $config->get( 'WBQualityConstraintsSparqlEndpoint' );
		$this->additionalEndpoints = $config->get( 'WBQualityConstraintsAdditionalSparqlEndpoints' ) ?: [];
		$this->maxQueryTimeMillis = $config->get( 'WBQualityConstraintsSparqlMaxMillis' );
		$this->subclassOfId = new NumericPropertyId( $config->get( 'WBQualityConstraintsSubclassOfId' ) );
		$this->cacheMapSize = $config->get( 'WBQualityConstraintsFormatCacheMapSize' );
		$this->timeoutExceptionClasses = $config->get(
			'WBQualityConstraintsSparqlTimeoutExceptionClasses'
		);
		$this->sparqlHasWikibaseSupport = $config->get(
			'WBQualityConstraintsSparqlHasWikibaseSupport'
		);
		$this->sparqlThrottlingFallbackDuration = (int)$config->get(
			'WBQualityConstraintsSparqlThrottlingFallbackDuration'
		);

		$this->prefixes = $this->getQueryPrefixes( $rdfVocabulary );

		$this->rdfVocabularyWithoutNormalization = clone $rdfVocabulary;
		// @phan-suppress-next-line PhanTypeMismatchProperty
		$this->rdfVocabularyWithoutNormalization->normalizedPropertyValueNamespace = array_fill_keys(
			array_keys( $rdfVocabulary->normalizedPropertyValueNamespace ),
			null
		);
	}

	private function getQueryPrefixes( RdfVocabulary $rdfVocabulary ): string {
		// TODO: it would probably be smarter that RdfVocabulary exposed these prefixes somehow
		$prefixes = '';
		foreach ( $rdfVocabulary->entityNamespaceNames as $sourceName => $namespaceName ) {
			$prefixes .= <<<END
PREFIX {$namespaceName}: <{$rdfVocabulary->getNamespaceURI( $namespaceName )}>\n
END;
		}

		foreach ( $rdfVocabulary->statementNamespaceNames as $sourceName => $sourceNamespaces ) {
			$namespaceName = $sourceNamespaces[RdfVocabulary::NS_VALUE];
			$prefixes .= <<<END
PREFIX {$namespaceName}: <{$rdfVocabulary->getNamespaceURI( $namespaceName )}>\n
END;
		}

		foreach ( $rdfVocabulary->propertyNamespaceNames as $sourceName => $sourceNamespaces ) {
			$namespaceName = $sourceNamespaces[RdfVocabulary::NSP_DIRECT_CLAIM];
			$prefixes .= <<<END
PREFIX {$namespaceName}: <{$rdfVocabulary->getNamespaceURI( $namespaceName )}>\n
END;
			$namespaceName = $sourceNamespaces[RdfVocabulary::NSP_CLAIM];
			$prefixes .= <<<END
PREFIX {$namespaceName}: <{$rdfVocabulary->getNamespaceURI( $namespaceName )}>\n
END;
			$namespaceName = $sourceNamespaces[RdfVocabulary::NSP_CLAIM_STATEMENT];
			$prefixes .= <<<END
PREFIX {$namespaceName}: <{$rdfVocabulary->getNamespaceURI( $namespaceName )}>\n
END;
			$namespaceName = $sourceNamespaces[RdfVocabulary::NSP_CLAIM_VALUE];
			$prefixes .= <<<END
PREFIX {$namespaceName}: <{$rdfVocabulary->getNamespaceURI( $namespaceName )}>\n
END;
			$namespaceName = $sourceNamespaces[RdfVocabulary::NSP_QUALIFIER];
			$prefixes .= <<<END
PREFIX {$namespaceName}: <{$rdfVocabulary->getNamespaceURI( $namespaceName )}>\n
END;
			$namespaceName = $sourceNamespaces[RdfVocabulary::NSP_QUALIFIER_VALUE];
			$prefixes .= <<<END
PREFIX {$namespaceName}: <{$rdfVocabulary->getNamespaceURI( $namespaceName )}>\n
END;
			$namespaceName = $sourceNamespaces[RdfVocabulary::NSP_REFERENCE];
			$prefixes .= <<<END
PREFIX {$namespaceName}: <{$rdfVocabulary->getNamespaceURI( $namespaceName )}>\n
END;
			$namespaceName = $sourceNamespaces[RdfVocabulary::NSP_REFERENCE_VALUE];
			$prefixes .= <<<END
PREFIX {$namespaceName}: <{$rdfVocabulary->getNamespaceURI( $namespaceName )}>\n
END;
		}
		$namespaceName = RdfVocabulary::NS_ONTOLOGY;
		$prefixes .= <<<END
PREFIX {$namespaceName}: <{$rdfVocabulary->getNamespaceURI( $namespaceName )}>\n
END;
		return $prefixes;
	}

	/** Return a SPARQL term like `wd:Q123` for the given ID. */
	private function wd( EntityId $id ): string {
		$repository = $this->rdfVocabulary->getEntityRepositoryName( $id );
		$prefix = $this->rdfVocabulary->entityNamespaceNames[$repository];
		return "$prefix:{$id->getSerialization()}";
	}

	/** Return a SPARQL term like `wdt:P123` for the given ID. */
	private function wdt( PropertyId $id ): string {
		$repository = $this->rdfVocabulary->getEntityRepositoryName( $id );
		$prefix = $this->rdfVocabulary->propertyNamespaceNames[$repository][RdfVocabulary::NSP_DIRECT_CLAIM];
		return "$prefix:{$id->getSerialization()}";
	}

	/** Return a SPARQL term like `p:P123` for the given ID. */
	private function p( PropertyId $id ): string {
		$repository = $this->rdfVocabulary->getEntityRepositoryName( $id );
		$prefix = $this->rdfVocabulary->propertyNamespaceNames[$repository][RdfVocabulary::NSP_CLAIM];
		return "$prefix:{$id->getSerialization()}";
	}

	/** Return a SPARQL term like `pq:P123` for the given ID. */
	private function pq( PropertyId $id ): string {
		$repository = $this->rdfVocabulary->getEntityRepositoryName( $id );
		$prefix = $this->rdfVocabulary->propertyNamespaceNames[$repository][RdfVocabulary::NSP_QUALIFIER];
		return "$prefix:{$id->getSerialization()}";
	}

	/** Return a SPARQL term like `wdno:P123` for the given ID. */
	private function wdno( PropertyId $id ): string {
		$repository = $this->rdfVocabulary->getEntityRepositoryName( $id );
		$prefix = $this->rdfVocabulary->propertyNamespaceNames[$repository][RdfVocabulary::NSP_NOVALUE];
		return "$prefix:{$id->getSerialization()}";
	}

	/** Return a SPARQL term like `prov:NAME` for the given name. */
	private function prov( string $name ): string {
		$prefix = RdfVocabulary::NS_PROV;
		return "$prefix:$name";
	}

	/** Return a SPARQL term like `wikibase:NAME` for the given name. */
	private function wikibase( string $name ): string {
		$prefix = RdfVocabulary::NS_ONTOLOGY;
		return "$prefix:$name";
	}

	/** Return a SPARQL snippet like `MINUS { ?var wikibase:rank wikibase:DeprecatedRank. }`. */
	private function minusDeprecatedRank( string $varName ): string {
		$deprecatedRank = RdfVocabulary::RANK_MAP[Statement::RANK_DEPRECATED];
		return "MINUS { $varName {$this->wikibase( 'rank' )} {$this->wikibase( $deprecatedRank )}. }";
	}

	/**
	 * @param EntityId $id entity ID of the entity to check
	 * @param string[] $classes entity ID serializations of the expected types
	 *
	 * @return CachedBool
	 * @throws SparqlHelperException if the query times out or some other error occurs
	 */
	public function hasType( EntityId $id, array $classes ): CachedBool {
		// TODO hint:gearing is a workaround for T168973 and can hopefully be removed eventually
		$gearingHint = $this->sparqlHasWikibaseSupport ?
			' hint:Prior hint:gearing "forward".' :
			'';

		$metadatas = [];

		foreach ( array_chunk( $classes, 20 ) as $classesChunk ) {
			$classesValues = implode( ' ', array_map(
				function ( string $class ) {
					return $this->wd( new ItemId( $class ) );
				},
				$classesChunk
			) );

			$query = <<<EOF
ASK {
  BIND({$this->wd( $id )} AS ?item)
  VALUES ?class { $classesValues }
  ?item {$this->wdt( $this->subclassOfId )}* ?class.$gearingHint
}
EOF;

			$result = $this->runQuery( $query, $this->primaryEndpoint );
			$metadatas[] = $result->getMetadata();
			if ( $result->getArray()['boolean'] ) {
				return new CachedBool(
					true,
					Metadata::merge( $metadatas )
				);
			}
		}

		return new CachedBool(
			false,
			Metadata::merge( $metadatas )
		);
	}

	/**
	 * @param Statement $statement
	 * @param PropertyId[] $separators
	 *
	 * @return CachedEntityIds
	 * @throws SparqlHelperException if the query times out or some other error occurs
	 */
	public function findEntitiesWithSameStatement(
		EntityId $entityId,
		Statement $statement,
		array $separators
	): CachedEntityIds {
		$mainSnak = $statement->getMainSnak();
		if ( !( $mainSnak instanceof PropertyValueSnak ) ) {
			return new CachedEntityIds( [], Metadata::blank() );
		}

		$propertyId = $statement->getPropertyId();
		$pPredicateAndObject = "{$this->p( $propertyId )} ?otherStatement."; // p:P123 ?otherStatement.
		$otherStatementPredicateAndObject = $this->getSnakPredicateAndObject(
			$entityId,
			$mainSnak,
			RdfVocabulary::NSP_CLAIM_STATEMENT
		);

		$isSeparator = [];
		$unusedSeparators = [];
		foreach ( $separators as $separator ) {
			$isSeparator[$separator->getSerialization()] = true;
			$unusedSeparators[$separator->getSerialization()] = $separator;
		}
		$separatorFilters = '';
		foreach ( $statement->getQualifiers() as $qualifier ) {
			$qualPropertyId = $qualifier->getPropertyId();
			if ( !( $isSeparator[$qualPropertyId->getSerialization()] ?? false ) ) {
				continue;
			}
			unset( $unusedSeparators[$qualPropertyId->getSerialization()] );
			// only look for other statements with the same qualifier
			if ( $qualifier instanceof PropertyValueSnak ) {
				$sepPredicateAndObject = $this->getSnakPredicateAndObject(
					$entityId,
					$qualifier,
					RdfVocabulary::NSP_QUALIFIER
				);
				$separatorFilters .= "  ?otherStatement $sepPredicateAndObject\n";
			} elseif ( $qualifier instanceof PropertyNoValueSnak ) {
				$sepPredicateAndObject = "a {$this->wdno( $qualPropertyId )}."; // a wdno:P123.
				$separatorFilters .= "  ?otherStatement $sepPredicateAndObject\n";
			} else {
				// "some value" / "unknown value" is always different from everything else,
				// therefore the whole statement has no duplicates and we can return immediately
				return new CachedEntityIds( [], Metadata::blank() );
			}
		}
		foreach ( $unusedSeparators as $unusedSeparator ) {
			// exclude other statements which have a separator that this one lacks
			$separatorFilters .= "  MINUS { ?otherStatement {$this->pq( $unusedSeparator )} []. }\n";
			$separatorFilters .= "  MINUS { ?otherStatement a {$this->wdno( $unusedSeparator )}. }\n";
		}

		$query = <<<SPARQL
SELECT DISTINCT ?otherEntity WHERE {
  ?otherEntity $pPredicateAndObject
  ?otherStatement $otherStatementPredicateAndObject
  {$this->minusDeprecatedRank( '?otherStatement' )}
  FILTER(?otherEntity != {$this->wd( $entityId )})
$separatorFilters
}
LIMIT 10
SPARQL;

		$results = [ $this->runQuery( $query, $this->primaryEndpoint ) ];
		foreach ( $this->additionalEndpoints as $endpoint ) {
			$results[] = $this->runQuery( $query, $endpoint );
		}

		return $this->getOtherEntities( $results );
	}

	/**
	 * @param EntityId $entityId The entity ID on the containing entity
	 * @param PropertyValueSnak $snak
	 * @param string $type Context::TYPE_QUALIFIER or Context::TYPE_REFERENCE
	 * @param boolean $ignoreDeprecatedStatements Whether to ignore deprecated statements or not.
	 *
	 * @return CachedEntityIds
	 * @throws SparqlHelperException if the query times out or some other error occurs
	 */
	public function findEntitiesWithSameQualifierOrReference(
		EntityId $entityId,
		PropertyValueSnak $snak,
		string $type,
		bool $ignoreDeprecatedStatements
	): CachedEntityIds {
		$propertyId = $snak->getPropertyId();
		$pPredicateAndObject = "{$this->p( $propertyId )} ?otherStatement."; // p:P123 ?otherStatement.

		$otherSubject = $type === Context::TYPE_QUALIFIER ?
			'?otherStatement' :
			"?otherStatement {$this->prov( 'wasDerivedFrom' )} ?reference.\n  ?reference";
		$otherPredicateAndObject = $this->getSnakPredicateAndObject(
			$entityId,
			$snak,
			$type === Context::TYPE_QUALIFIER ?
				RdfVocabulary::NSP_QUALIFIER :
				RdfVocabulary::NSP_REFERENCE
		);

		$deprecatedFilter = '';
		if ( $ignoreDeprecatedStatements ) {
			$deprecatedFilter = '  ' . $this->minusDeprecatedRank( '?otherStatement' );
		}

		$query = <<<SPARQL
SELECT DISTINCT ?otherEntity WHERE {
  ?otherEntity $pPredicateAndObject
  $otherSubject $otherPredicateAndObject
  FILTER(?otherEntity != {$this->wd( $entityId )})
$deprecatedFilter
}
LIMIT 10
SPARQL;

		$results = [ $this->runQuery( $query, $this->primaryEndpoint ) ];
		foreach ( $this->additionalEndpoints as $endpoint ) {
			$results[] = $this->runQuery( $query, $endpoint );
		}

		return $this->getOtherEntities( $results );
	}

	/**
	 * Generate a SPARQL snippet for the property and value of the given snak.
	 *
	 * This reuses Wikibase’s RDF export using the Turtle (TTL) format. Turtle and SPARQL are
	 * {@link https://www.w3.org/2011/rdf-wg/wiki/Diff_SPARQL_Turtle not fully compatible},
	 * but most of the differences are additional SPARQL constructs not allowed in Turtle
	 * (i.e. not relevant for the direction we use here),
	 * and the main other issue, `\u` escape processing, should not affect us either
	 * (N3Quoter escapes `"` as the unproblematic `\"` rather than the problematic `\u0022`).
	 *
	 * @param EntityId $entityId The subject to which the statement belongs
	 * @param PropertyValueSnak $snak The snak we’re looking for
	 * @param string $namespace Specifies which kind of snak we’re looking for:
	 *  {@link RdfVocabulary::NSP_CLAIM_STATEMENT} for the main snak,
	 *  {@link RdfVocabulary::NSP_QUALIFIER} for a qualifier
	 *  or {@link RdfVocabulary::NSP_REFERENCE} for a reference.
	 * @return string SPARQL snippet like `wdt:P31 wd:Q5.`
	 */
	private function getSnakPredicateAndObject(
		EntityId $entityId,
		PropertyValueSnak $snak,
		string $namespace
	): string {
		// set up the writer, flush out the header (prefixes) and initialize the fake subject
		$writer = new TurtleRdfWriter();
		$writer->start();
		$writer->drain();
		$placeholder1 = 'wbqc';
		$placeholder2 = 'x' . wfRandomString( 32 );
		$writer->about( $placeholder1, $placeholder2 );

		$propertyId = $snak->getPropertyId();
		$pid = $propertyId->getSerialization();
		$propertyRepository = $this->rdfVocabulary->getEntityRepositoryName( $propertyId );
		$entityRepository = $this->rdfVocabulary->getEntityRepositoryName( $entityId );
		$propertyNamespace = $this->rdfVocabulary->propertyNamespaceNames[$propertyRepository][$namespace];
		$value = $snak->getDataValue();
		if (
			$value instanceof GlobeCoordinateValue ||
			$value instanceof UnboundedQuantityValue ||
			$value instanceof TimeValue
		) {
			// use the full value node via its hash
			// ComplexValueRdfHelper::attachValueNode() always uses $valueLName = $value->getHash();
			$writer->say(
				$this->rdfVocabulary->claimToValue[$propertyNamespace],
				$pid
			)->is(
				$this->rdfVocabulary->statementNamespaceNames[$entityRepository][RdfVocabulary::NS_VALUE],
				$value->getHash()
			);
		} else {
			// use the simple value directly
			$valueSnakRdfBuilder = $this->valueSnakRdfBuilderFactory
				->getValueSnakRdfBuilder(
					0,
					$this->rdfVocabularyWithoutNormalization,
					$writer,
					new NullEntityMentionListener(),
					new NullDedupeBag()
				);
			$valueSnakRdfBuilder->addValue(
				$writer,
				$propertyNamespace,
				$pid,
				$this->propertyDataTypeLookup->getDataTypeIdForProperty( $propertyId ),
				$this->rdfVocabulary->statementNamespaceNames[$entityRepository][RdfVocabulary::NS_VALUE], // should be unused
				$snak
			);
		}

		$triple = $writer->drain(); // wbqc:xRANDOM ps:PID "value". or similar
		return trim( str_replace( "$placeholder1:$placeholder2", '', $triple ) );
	}

	/**
	 * Return SPARQL code for a string literal with $text as content.
	 */
	private function stringLiteral( string $text ): string {
		return '"' . strtr( $text, [ '"' => '\\"', '\\' => '\\\\' ] ) . '"';
	}

	/**
	 * Extract and parse entity IDs from the ?otherEntity column of SPARQL query results.
	 *
	 * @param CachedQueryResults[] $results
	 *
	 * @return CachedEntityIds
	 */
	private function getOtherEntities( array $results ): CachedEntityIds {
		$allResultBindings = [];
		$metadatas = [];

		foreach ( $results as $result ) {
			$metadatas[] = $result->getMetadata();
			$allResultBindings = array_merge( $allResultBindings, $result->getArray()['results']['bindings'] );
		}

		$entityIds = array_map(
			function ( $resultBindings ) {
				$entityIRI = $resultBindings['otherEntity']['value'];
				foreach ( $this->entityPrefixes as $entityPrefix ) {
					$entityPrefixLength = strlen( $entityPrefix );
					if ( substr( $entityIRI, 0, $entityPrefixLength ) === $entityPrefix ) {
						try {
							return $this->entityIdParser->parse(
								substr( $entityIRI, $entityPrefixLength )
							);
						} catch ( EntityIdParsingException $e ) {
							// fall through
						}
					}

					return null;
				}

				return null;
			},
			$allResultBindings
		);

		return new CachedEntityIds(
			array_values( array_filter( array_unique( $entityIds ) ) ),
			Metadata::merge( $metadatas )
		);
	}

	/**
	 * @throws SparqlHelperException if the query times out or some other error occurs
	 * @throws ConstraintParameterException if the $regex is invalid
	 */
	public function matchesRegularExpression( string $text, string $regex ): bool {
		// caching wrapper around matchesRegularExpressionWithSparql

		$textHash = hash( 'sha256', $text );
		$cacheKey = $this->cache->makeKey(
			'WikibaseQualityConstraints', // extension
			'regex', // action
			'WDQS-Java', // regex flavor
			hash( 'sha256', $regex )
		);

		$cacheMapArray = $this->cache->getWithSetCallback(
			$cacheKey,
			WANObjectCache::TTL_DAY,
			function ( $cacheMapArray ) use ( $text, $regex, $textHash ) {
				// Initialize the cache map if not set
				if ( $cacheMapArray === false ) {
					$key = 'wikibase.quality.constraints.regex.cache.refresh.init';
					$this->dataFactory->increment( $key );
					return [];
				}

				$key = 'wikibase.quality.constraints.regex.cache.refresh';
				$this->dataFactory->increment( $key );
				$cacheMap = MapCacheLRU::newFromArray( $cacheMapArray, $this->cacheMapSize );
				if ( $cacheMap->has( $textHash ) ) {
					$key = 'wikibase.quality.constraints.regex.cache.refresh.hit';
					$this->dataFactory->increment( $key );
					$cacheMap->get( $textHash ); // ping cache
				} else {
					$key = 'wikibase.quality.constraints.regex.cache.refresh.miss';
					$this->dataFactory->increment( $key );
					try {
						$matches = $this->matchesRegularExpressionWithSparql( $text, $regex );
					} catch ( ConstraintParameterException $e ) {
						$matches = $this->serializeConstraintParameterException( $e );
					} catch ( SparqlHelperException $e ) {
						// don’t cache this
						return $cacheMap->toArray();
					}
					$cacheMap->set(
						$textHash,
						$matches,
						3 / 8
					);
				}

				return $cacheMap->toArray();
			},
			[
				// Once map is > 1 sec old, consider refreshing
				'ageNew' => 1,
				// Update 5 seconds after "ageNew" given a 1 query/sec cache check rate
				'hotTTR' => 5,
				// avoid querying cache servers multiple times in a request
				// (e. g. when checking format of a reference URL used multiple times on an entity)
				'pcTTL' => WANObjectCache::TTL_PROC_LONG,
			]
		);

		if ( isset( $cacheMapArray[$textHash] ) ) {
			$key = 'wikibase.quality.constraints.regex.cache.hit';
			$this->dataFactory->increment( $key );
			$matches = $cacheMapArray[$textHash];
			if ( is_bool( $matches ) ) {
				return $matches;
			} elseif ( is_array( $matches ) &&
				$matches['type'] == ConstraintParameterException::class ) {
				throw $this->deserializeConstraintParameterException( $matches );
			} else {
				throw new UnexpectedValueException(
					'Value of unknown type in object cache (' .
					'cache key: ' . $cacheKey . ', ' .
					'cache map key: ' . $textHash . ', ' .
					'value type: ' . get_debug_type( $matches ) . ')'
				);
			}
		} else {
			$key = 'wikibase.quality.constraints.regex.cache.miss';
			$this->dataFactory->increment( $key );
			return $this->matchesRegularExpressionWithSparql( $text, $regex );
		}
	}

	private function serializeConstraintParameterException( ConstraintParameterException $cpe ): array {
		return [
			'type' => ConstraintParameterException::class,
			'violationMessage' => $this->violationMessageSerializer->serialize( $cpe->getViolationMessage() ),
		];
	}

	private function deserializeConstraintParameterException( array $serialization ): ConstraintParameterException {
		$message = $this->violationMessageDeserializer->deserialize(
			$serialization['violationMessage']
		);
		return new ConstraintParameterException( $message );
	}

	/**
	 * This function is only public for testing purposes;
	 * use matchesRegularExpression, which is equivalent but caches results.
	 *
	 * @throws SparqlHelperException if the query times out or some other error occurs
	 * @throws ConstraintParameterException if the $regex is invalid
	 */
	public function matchesRegularExpressionWithSparql( string $text, string $regex ): bool {
		$textStringLiteral = $this->stringLiteral( $text );
		$regexStringLiteral = $this->stringLiteral( '^(?:' . $regex . ')$' );

		$query = <<<EOF
SELECT (REGEX($textStringLiteral, $regexStringLiteral) AS ?matches) {}
EOF;

		$result = $this->runQuery( $query, $this->primaryEndpoint, false );

		$vars = $result->getArray()['results']['bindings'][0];
		if ( array_key_exists( 'matches', $vars ) ) {
			// true or false ⇒ regex okay, text matches or not
			return $vars['matches']['value'] === 'true';
		} else {
			// empty result: regex broken
			throw new ConstraintParameterException(
				( new ViolationMessage( 'wbqc-violation-message-parameter-regex' ) )
					->withInlineCode( $regex, Role::CONSTRAINT_PARAMETER_VALUE )
			);
		}
	}

	/**
	 * Check whether the text content of an error response indicates a query timeout.
	 */
	public function isTimeout( string $responseContent ): bool {
		$timeoutRegex = implode( '|', array_map(
			static function ( $fqn ) {
				return preg_quote( $fqn, '/' );
			},
			$this->timeoutExceptionClasses
		) );
		return (bool)preg_match( '/' . $timeoutRegex . '/', $responseContent );
	}

	/**
	 * Return the max-age of a cached response,
	 * or a boolean indicating whether the response was cached or not.
	 *
	 * @param array $responseHeaders see MWHttpRequest::getResponseHeaders()
	 *
	 * @return int|boolean the max-age (in seconds)
	 * or a plain boolean if no max-age can be determined
	 */
	public function getCacheMaxAge( array $responseHeaders ) {
		if (
			array_key_exists( 'x-cache-status', $responseHeaders ) &&
			preg_match( '/^hit(?:-.*)?$/', $responseHeaders['x-cache-status'][0] )
		) {
			$maxage = [];
			if (
				array_key_exists( 'cache-control', $responseHeaders ) &&
				preg_match( '/\bmax-age=(\d+)\b/', $responseHeaders['cache-control'][0], $maxage )
			) {
				return intval( $maxage[1] );
			} else {
				return true;
			}
		} else {
			return false;
		}
	}

	/**
	 * Get the delay date of a 429 headered response, which is caused by
	 * throttling of to many SPARQL-Requests. The header-format is defined
	 * in RFC7231 see: https://tools.ietf.org/html/rfc7231#section-7.1.3
	 *
	 * @param MWHttpRequest $request
	 *
	 * @return int|ConvertibleTimestamp
	 * or SparlHelper::NO_RETRY_AFTER if there is no Retry-After header
	 * or SparlHelper::EMPTY_RETRY_AFTER if there is an empty Retry-After
	 * or SparlHelper::INVALID_RETRY_AFTER if there is something wrong with the format
	 */
	public function getThrottling( MWHttpRequest $request ) {
		$retryAfterValue = $request->getResponseHeader( 'Retry-After' );
		if ( $retryAfterValue === null ) {
			return self::NO_RETRY_AFTER;
		}

		$trimmedRetryAfterValue = trim( $retryAfterValue );
		if ( $trimmedRetryAfterValue === '' ) {
			return self::EMPTY_RETRY_AFTER;
		}

		if ( is_numeric( $trimmedRetryAfterValue ) ) {
			$delaySeconds = (int)$trimmedRetryAfterValue;
			if ( $delaySeconds >= 0 ) {
				return $this->getTimestampInFuture( new DateInterval( 'PT' . $delaySeconds . 'S' ) );
			}
		} else {
			$return = strtotime( $trimmedRetryAfterValue );
			if ( $return !== false ) {
				return new ConvertibleTimestamp( $return );
			}
		}
		return self::INVALID_RETRY_AFTER;
	}

	private function getTimestampInFuture( DateInterval $delta ) {
		$now = new ConvertibleTimestamp();
		return new ConvertibleTimestamp( $now->timestamp->add( $delta ) );
	}

	/**
	 * Runs a query against the configured endpoint and returns the results.
	 * TODO: See if Sparql Client in core can be used instead of rolling our own
	 *
	 * @param string $query The query, unencoded (plain string).
	 * @param string $endpoint The endpoint to query.
	 * @param bool $needsPrefixes Whether the query requires prefixes or they can be omitted.
	 *
	 * @return CachedQueryResults
	 *
	 * @throws SparqlHelperException if the query times out or some other error occurs
	 */
	protected function runQuery( string $query, string $endpoint, bool $needsPrefixes = true ): CachedQueryResults {
		if ( $this->throttlingLock->isLocked( self::EXPIRY_LOCK_ID ) ) {
			$this->dataFactory->increment( 'wikibase.quality.constraints.sparql.throttling' );
			throw new TooManySparqlRequestsException();
		}

		if ( $this->sparqlHasWikibaseSupport ) {
			$needsPrefixes = false;
		}

		if ( $needsPrefixes ) {
			$query = $this->prefixes . $query;
		}
		$query = "#wbqc\n" . $query;

		$url = $endpoint . '?' . http_build_query(
			[
				'query' => $query,
				'format' => 'json',
				'maxQueryTimeMillis' => $this->maxQueryTimeMillis,
			],
			'', ini_get( 'arg_separator.output' ),
			// encode spaces with %20, not +
			PHP_QUERY_RFC3986
		);

		$options = [
			'method' => 'GET',
			'timeout' => (int)round( ( $this->maxQueryTimeMillis + 1000 ) / 1000 ),
			'connectTimeout' => 'default',
			'userAgent' => $this->defaultUserAgent,
		];
		$request = $this->requestFactory->create( $url, $options, __METHOD__ );
		$startTime = microtime( true );
		$requestStatus = $request->execute();
		$endTime = microtime( true );
		$this->dataFactory->timing(
			'wikibase.quality.constraints.sparql.timing',
			( $endTime - $startTime ) * 1000
		);

		$this->guardAgainstTooManyRequestsError( $request );

		$maxAge = $this->getCacheMaxAge( $request->getResponseHeaders() );
		if ( $maxAge ) {
			$this->dataFactory->increment( 'wikibase.quality.constraints.sparql.cached' );
		}

		if ( $requestStatus->isOK() ) {
			$json = $request->getContent();
			$jsonStatus = FormatJson::parse( $json, FormatJson::FORCE_ASSOC );
			if ( $jsonStatus->isOK() ) {
				return new CachedQueryResults(
					$jsonStatus->getValue(),
					Metadata::ofCachingMetadata(
						$maxAge ?
							CachingMetadata::ofMaximumAgeInSeconds( $maxAge ) :
							CachingMetadata::fresh()
					)
				);
			} else {
				$jsonErrorCode = $jsonStatus->getErrors()[0]['message'];
				$this->dataFactory->increment(
					"wikibase.quality.constraints.sparql.error.json.$jsonErrorCode"
				);
				// fall through to general error handling
			}
		} else {
			$this->dataFactory->increment(
				"wikibase.quality.constraints.sparql.error.http.{$request->getStatus()}"
			);
			// fall through to general error handling
		}

		$this->dataFactory->increment( 'wikibase.quality.constraints.sparql.error' );

		if ( $this->isTimeout( $request->getContent() ) ) {
			$this->dataFactory->increment(
				'wikibase.quality.constraints.sparql.error.timeout'
			);
		}

		throw new SparqlHelperException();
	}

	/**
	 * Handle a potential “too many requests” error.
	 *
	 * @param MWHttpRequest $request
	 * @throws TooManySparqlRequestsException
	 */
	private function guardAgainstTooManyRequestsError( MWHttpRequest $request ): void {
		if ( $request->getStatus() !== self::HTTP_TOO_MANY_REQUESTS ) {
			return;
		}

		$fallbackBlockDuration = $this->sparqlThrottlingFallbackDuration;

		if ( $fallbackBlockDuration < 0 ) {
			throw new InvalidArgumentException( 'Fallback duration must be positive int but is: ' .
				$fallbackBlockDuration );
		}

		$this->dataFactory->increment( 'wikibase.quality.constraints.sparql.throttling' );
		$throttlingUntil = $this->getThrottling( $request );
		if ( !( $throttlingUntil instanceof ConvertibleTimestamp ) ) {
			$this->loggingHelper->logSparqlHelperTooManyRequestsRetryAfterInvalid( $request );
			$this->throttlingLock->lock(
				self::EXPIRY_LOCK_ID,
				$this->getTimestampInFuture( new DateInterval( 'PT' . $fallbackBlockDuration . 'S' ) )
			);
		} else {
			$this->loggingHelper->logSparqlHelperTooManyRequestsRetryAfterPresent( $throttlingUntil, $request );
			$this->throttlingLock->lock( self::EXPIRY_LOCK_ID, $throttlingUntil );
		}
		throw new TooManySparqlRequestsException();
	}

}
