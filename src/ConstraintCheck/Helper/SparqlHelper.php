<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Helper;

use Config;
use DataValues\DataValue;
use DataValues\MonolingualTextValue;
use IBufferingStatsdDataFactory;
use InvalidArgumentException;
use MapCacheLRU;
use MWException;
use MWHttpRequest;
use WANObjectCache;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Entity\EntityIdParsingException;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\Rdf\RdfVocabulary;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachedBool;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachedEntityIds;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachedQueryResults;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachingMetadata;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\Metadata;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\Context;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageDeserializer;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageSerializer;
use WikibaseQuality\ConstraintReport\ConstraintParameterRenderer;
use WikibaseQuality\ConstraintReport\Role;

/**
 * Class for running a SPARQL query on some endpoint and getting the results.
 *
 * @author Lucas Werkmeister
 * @license GNU GPL v2+
 */
class SparqlHelper {

	/**
	 * @var Config
	 */
	private $config;

	/**
	 * @var RdfVocabulary
	 */
	private $rdfVocabulary;

	/**
	 * @var string
	 */
	private $entityPrefix;

	/**
	 * @var string
	 */
	private $prefixes;

	/**
	 * @var EntityIdParser
	 */
	private $entityIdParser;

	/**
	 * @var PropertyDataTypeLookup
	 */
	private $propertyDataTypeLookup;

	/**
	 * @var WANObjectCache
	 */
	private $cache;

	/**
	 * @var ViolationMessageSerializer
	 */
	private $violationMessageSerializer;

	/**
	 * @var ViolationMessageDeserializer
	 */
	private $violationMessageDeserializer;

	/**
	 * @var IBufferingStatsdDataFactory
	 */
	private $dataFactory;

	public function __construct(
		Config $config,
		RdfVocabulary $rdfVocabulary,
		EntityIdParser $entityIdParser,
		PropertyDataTypeLookup $propertyDataTypeLookup,
		WANObjectCache $cache,
		ViolationMessageSerializer $violationMessageSerializer,
		ViolationMessageDeserializer $violationMessageDeserializer,
		IBufferingStatsdDataFactory $dataFactory
	) {
		$this->config = $config;
		$this->rdfVocabulary = $rdfVocabulary;
		$this->entityIdParser = $entityIdParser;
		$this->propertyDataTypeLookup = $propertyDataTypeLookup;
		$this->cache = $cache;
		$this->violationMessageSerializer = $violationMessageSerializer;
		$this->violationMessageDeserializer = $violationMessageDeserializer;
		$this->dataFactory = $dataFactory;

		$this->entityPrefix = $rdfVocabulary->getNamespaceURI( RdfVocabulary::NS_ENTITY );
		$this->prefixes = <<<EOT
PREFIX wd: <{$rdfVocabulary->getNamespaceURI( RdfVocabulary::NS_ENTITY )}>
PREFIX wds: <{$rdfVocabulary->getNamespaceURI( RdfVocabulary::NS_STATEMENT )}>
PREFIX wdt: <{$rdfVocabulary->getNamespaceURI( RdfVocabulary::NSP_DIRECT_CLAIM )}>
PREFIX wdv: <{$rdfVocabulary->getNamespaceURI( RdfVocabulary::NS_VALUE )}>
PREFIX p: <{$rdfVocabulary->getNamespaceURI( RdfVocabulary::NSP_CLAIM )}>
PREFIX ps: <{$rdfVocabulary->getNamespaceURI( RdfVocabulary::NSP_CLAIM_STATEMENT )}>
PREFIX pq: <{$rdfVocabulary->getNamespaceURI( RdfVocabulary::NSP_QUALIFIER )}>
PREFIX pqv: <{$rdfVocabulary->getNamespaceURI( RdfVocabulary::NSP_QUALIFIER_VALUE )}>
PREFIX pr: <{$rdfVocabulary->getNamespaceURI( RdfVocabulary::NSP_REFERENCE )}>
PREFIX prv: <{$rdfVocabulary->getNamespaceURI( RdfVocabulary::NSP_REFERENCE_VALUE )}>
PREFIX wikibase: <http://wikiba.se/ontology#>
PREFIX wikibase-beta: <http://wikiba.se/ontology-beta#>
EOT;
		// TODO get wikibase: prefix from vocabulary once -beta is dropped (T112127)
	}

	/**
	 * @param string $id entity ID serialization of the entity to check
	 * @param string[] $classes entity ID serializations of the expected types
	 * @param boolean $withInstance true for “instance” relation, false for “subclass” relation
	 *
	 * @return CachedBool
	 * @throws SparqlHelperException if the query times out or some other error occurs
	 */
	public function hasType( $id, array $classes, $withInstance ) {
		$instanceOfId = $this->config->get( 'WBQualityConstraintsInstanceOfId' );
		$subclassOfId = $this->config->get( 'WBQualityConstraintsSubclassOfId' );

		$path = ( $withInstance ? "wdt:$instanceOfId/" : "" ) . "wdt:$subclassOfId*";

		$metadatas = [];

		foreach ( array_chunk( $classes, 20 ) as $classesChunk ) {
			$classesValues = implode( ' ', array_map(
				function( $class ) {
					return 'wd:' . $class;
				},
				$classesChunk
			) );

			$query = <<<EOF
ASK {
  BIND(wd:$id AS ?item)
  VALUES ?class { $classesValues }
  ?item $path ?class. hint:Prior hint:gearing "forward".
}
EOF;
			// TODO hint:gearing is a workaround for T168973 and can hopefully be removed eventually

			$result = $this->runQuery( $query );
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
	 * @param boolean $ignoreDeprecatedStatements Whether to ignore deprecated statements or not.
	 *
	 * @return CachedEntityIds
	 * @throws SparqlHelperException if the query times out or some other error occurs
	 */
	public function findEntitiesWithSameStatement(
		Statement $statement,
		$ignoreDeprecatedStatements
	) {
		$pid = $statement->getPropertyId()->serialize();
		$guid = str_replace( '$', '-', $statement->getGuid() );

		$deprecatedFilter = '';
		if ( $ignoreDeprecatedStatements ) {
			$deprecatedFilter .= 'MINUS { ?otherStatement wikibase:rank wikibase:DeprecatedRank. }';
			$deprecatedFilter .= 'MINUS { ?otherStatement wikibase-beta:rank wikibase-beta:DeprecatedRank. }';
		}

		$query = <<<EOF
SELECT DISTINCT ?otherEntity WHERE {
  BIND(wds:$guid AS ?statement)
  BIND(p:$pid AS ?p)
  BIND(ps:$pid AS ?ps)
  ?entity ?p ?statement.
  ?statement ?ps ?value.
  ?otherStatement ?ps ?value.
  ?otherEntity ?p ?otherStatement.
  FILTER(?otherEntity != ?entity)
  $deprecatedFilter
}
LIMIT 10
EOF;

		$result = $this->runQuery( $query );

		return $this->getOtherEntities( $result );
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
		$type,
		$ignoreDeprecatedStatements
	) {
		$eid = $entityId->getSerialization();
		$pid = $snak->getPropertyId()->getSerialization();
		$prefix = $type === Context::TYPE_QUALIFIER ? 'pq' : 'pr';
		$dataValue = $snak->getDataValue();
		$dataType = $this->propertyDataTypeLookup->getDataTypeIdForProperty(
			$snak->getPropertyId()
		);
		list( $value, $isFullValue ) = $this->getRdfLiteral( $dataType, $dataValue );
		if ( $isFullValue ) {
			$prefix .= 'v';
		}
		$path = $type === Context::TYPE_QUALIFIER ?
			"$prefix:$pid" :
			"prov:wasDerivedFrom/$prefix:$pid";

		$deprecatedFilter = '';
		if ( $ignoreDeprecatedStatements ) {
			$deprecatedFilter = <<< EOF
  MINUS { ?otherStatement wikibase:rank wikibase:DeprecatedRank. }
  MINUS { ?otherStatement wikibase-beta:rank wikibase-beta:DeprecatedRank. }
EOF;
		}

		$query = <<<EOF
SELECT DISTINCT ?otherEntity WHERE {
  BIND(wd:$eid AS ?entity)
  BIND($value AS ?value)
  ?entity ?p ?statement.
  ?statement $path ?value.
  ?otherStatement $path ?value.
  ?otherEntity ?otherP ?otherStatement.
  FILTER(?otherEntity != ?entity)
$deprecatedFilter
}
LIMIT 10
EOF;

		$result = $this->runQuery( $query );

		return $this->getOtherEntities( $result );
	}

	/**
	 * Return SPARQL code for a string literal with $text as content.
	 *
	 * @param string $text
	 *
	 * @return string
	 */
	private function stringLiteral( $text ) {
		return '"' . strtr( $text, [ '"' => '\\"', '\\' => '\\\\' ] ) . '"';
	}

	/**
	 * Extract and parse entity IDs from the ?otherEntity column of a SPARQL query result.
	 *
	 * @param CachedQueryResults $results
	 *
	 * @return CachedEntityIds
	 */
	private function getOtherEntities( CachedQueryResults $results ) {
		return new CachedEntityIds( array_map(
			function ( $resultBindings ) {
				$entityIRI = $resultBindings['otherEntity']['value'];
				$entityPrefixLength = strlen( $this->entityPrefix );
				if ( substr( $entityIRI, 0, $entityPrefixLength ) === $this->entityPrefix ) {
					try {
						return $this->entityIdParser->parse(
							substr( $entityIRI, $entityPrefixLength )
						);
					} catch ( EntityIdParsingException $e ) {
						// fall through
					}
				}

				return null;
			},
			$results->getArray()['results']['bindings']
		), $results->getMetadata() );
	}

	// @codingStandardsIgnoreStart cyclomatic complexity of this function is too high
	/**
	 * Get an RDF literal or IRI with which the given data value can be matched in a query.
	 *
	 * @param string $dataType
	 * @param DataValue $dataValue
	 *
	 * @return array the literal or IRI as a string in SPARQL syntax,
	 * and a boolean indicating whether it refers to a full value node or not
	 */
	private function getRdfLiteral( $dataType, DataValue $dataValue ) {
		switch ( $dataType ) {
			case 'string':
			case 'external-id':
				return [ $this->stringLiteral( $dataValue->getValue() ), false ];
			case 'commonsMedia':
				$url = $this->rdfVocabulary->getMediaFileURI( $dataValue->getValue() );
				return [ '<' . $url . '>', false ];
			case 'geo-shape':
				$url = $this->rdfVocabulary->getGeoShapeURI( $dataValue->getValue() );
				return [ '<' . $url . '>', false ];
			case 'tabular-data':
				$url = $this->rdfVocabulary->getTabularDataURI( $dataValue->getValue() );
				return [ '<' . $url . '>', false ];
			case 'url':
				$url = $dataValue->getValue();
				if ( !preg_match( '/^[^<>"{}\\\\|^`\\x00-\\x20]*$/D', $url ) ) {
					// not a valid URL for SPARQL (see SPARQL spec, production 139 IRIREF)
					// such an URL should never reach us, so just throw
					throw new InvalidArgumentException( 'invalid URL: ' . $url );
				}
				return [ '<' . $url . '>', false ];
			case 'wikibase-item':
			case 'wikibase-property':
				/** @var EntityIdValue $dataValue */
				return [ 'wd:' . $dataValue->getEntityId()->getSerialization(), false ];
			case 'monolingualtext':
				/** @var MonolingualTextValue $dataValue */
				$lang = $dataValue->getLanguageCode();
				if ( !preg_match( '/^[a-zA-Z]+(-[a-zA-Z0-9]+)*$/D', $lang ) ) {
					// not a valid language tag for SPARQL (see SPARQL spec, production 145 LANGTAG)
					// such a language tag should never reach us, so just throw
					throw new InvalidArgumentException( 'invalid language tag: ' . $lang );
				}
				return [ $this->stringLiteral( $dataValue->getText() ) . '@' . $lang, false ];
			case 'globe-coordinate':
			case 'quantity':
			case 'time':
				return [ 'wdv:' . $dataValue->getHash(), true ];
			default:
				throw new InvalidArgumentException( 'unknown data type: ' . $dataType );
		}
	}
	// @codingStandardsIgnoreEnd

	/**
	 * @param string $text
	 * @param string $regex
	 *
	 * @return boolean
	 * @throws SparqlHelperException if the query times out or some other error occurs
	 * @throws ConstraintParameterException if the $regex is invalid
	 */
	public function matchesRegularExpression( $text, $regex ) {
		// caching wrapper around matchesRegularExpressionWithSparql

		$textHash = hash( 'sha256', $text );
		$cacheKey = $this->cache->makeKey(
			'WikibaseQualityConstraints', // extension
			'regex', // action
			'WDQS-Java', // regex flavor
			hash( 'sha256', $regex )
		);
		$cacheMapSize = $this->config->get( 'WBQualityConstraintsFormatCacheMapSize' );

		$cacheMapArray = $this->cache->getWithSetCallback(
			$cacheKey,
			WANObjectCache::TTL_DAY,
			function( $cacheMapArray ) use ( $text, $regex, $textHash, $cacheMapSize ) {
				// Initialize the cache map if not set
				if ( $cacheMapArray === false ) {
					$key = 'wikibase.quality.constraints.regex.cache.refresh.init';
					$this->dataFactory->increment( $key );
					return [];
				}

				$key = 'wikibase.quality.constraints.regex.cache.refresh';
				$this->dataFactory->increment( $key );
				$cacheMap = MapCacheLRU::newFromArray( $cacheMapArray, $cacheMapSize );
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
						$matches = [
							'type' => ConstraintParameterException::class,
							'message' => $e->getMessage(),
						];
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
				throw new ConstraintParameterException( $matches['message'] );
			} else {
				throw new MWException(
					'Value of unknown type in object cache (' .
					'cache key: ' . $cacheKey . ', ' .
					'cache map key: ' . $textHash . ', ' .
					'value type: ' . gettype( $matches ) . ')'
				);
			}
		} else {
			$key = 'wikibase.quality.constraints.regex.cache.miss';
			$this->dataFactory->increment( $key );
			return $this->matchesRegularExpressionWithSparql( $text, $regex );
		}
	}

	/**
	 * This function is only public for testing purposes;
	 * use matchesRegularExpression, which is equivalent but caches results.
	 *
	 * @param string $text
	 * @param string $regex
	 *
	 * @return boolean
	 * @throws SparqlHelperException if the query times out or some other error occurs
	 * @throws ConstraintParameterException if the $regex is invalid
	 */
	public function matchesRegularExpressionWithSparql( $text, $regex ) {
		$textStringLiteral = $this->stringLiteral( $text );
		$regexStringLiteral = $this->stringLiteral( '^' . $regex . '$' );

		$query = <<<EOF
SELECT (REGEX($textStringLiteral, $regexStringLiteral) AS ?matches) {}
EOF;

		$result = $this->runQuery( $query );

		$vars = $result->getArray()['results']['bindings'][0];
		if ( array_key_exists( 'matches', $vars ) ) {
			// true or false ⇒ regex okay, text matches or not
			return $vars['matches']['value'] === 'true';
		} else {
			// empty result: regex broken
			throw new ConstraintParameterException(
				wfMessage( 'wbqc-violation-message-parameter-regex' )
					->rawParams( ConstraintParameterRenderer::formatByRole( Role::CONSTRAINT_PARAMETER_VALUE,
						'<code><nowiki>' . htmlspecialchars( $regex ) . '</nowiki></code>' ) )
					->escaped()
			);
		}
	}

	/**
	 * Check whether the text content of an error response indicates a query timeout.
	 *
	 * @param string $responseContent
	 *
	 * @return boolean
	 */
	public function isTimeout( $responseContent ) {
		$timeoutRegex = implode( '|', array_map(
			function ( $fqn ) {
				return preg_quote( $fqn, '/' );
			},
			$this->config->get( 'WBQualityConstraintsSparqlTimeoutExceptionClasses' )
		) );
		return (bool)preg_match( '/' . $timeoutRegex . '/', $responseContent );
	}

	/**
	 * Return the max-age of a cached response,
	 * or a boolean indicating whether the response was cached or not.
	 *
	 * @param array $responseHeaders see MWHttpRequest::getResponseHeaders()
	 *
	 * @return integer|boolean the max-age (in seconds)
	 * or a plain boolean if no max-age can be determined
	 */
	public function getCacheMaxAge( $responseHeaders ) {
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
	 * Runs a query against the configured endpoint and returns the results.
	 *
	 * @param string $query The query, unencoded (plain string).
	 *
	 * @return CachedQueryResults
	 *
	 * @throws SparqlHelperException if the query times out or some other error occurs
	 */
	public function runQuery( $query ) {

		$endpoint = $this->config->get( 'WBQualityConstraintsSparqlEndpoint' );
		$maxQueryTimeMillis = $this->config->get( 'WBQualityConstraintsSparqlMaxMillis' );
		$url = $endpoint . '?' . http_build_query(
			[
				'query' => "#wbqc\n" . $this->prefixes . $query,
				'format' => 'json',
				'maxQueryTimeMillis' => $maxQueryTimeMillis,
			],
			null, ini_get( 'arg_separator.output' ),
			// encode spaces with %20, not +
			PHP_QUERY_RFC3986
		);

		$options = [
			'method' => 'GET',
			'timeout' => (int)round( ( $maxQueryTimeMillis + 1000 ) / 1000 ),
			'connectTimeout' => 'default',
		];
		$request = MWHttpRequest::factory( $url, $options );
		$startTime = microtime( true );
		$status = $request->execute();
		$endTime = microtime( true );
		$this->dataFactory->timing(
			'wikibase.quality.constraints.sparql.timing',
			( $endTime - $startTime ) * 1000
		);

		$maxAge = $this->getCacheMaxAge( $request->getResponseHeaders() );
		if ( $maxAge ) {
			$this->dataFactory->increment( 'wikibase.quality.constraints.sparql.cached' );
		}

		if ( $status->isOK() ) {
			$json = $request->getContent();
			$arr = json_decode( $json, true );
			return new CachedQueryResults(
				$arr,
				Metadata::ofCachingMetadata(
					$maxAge ?
						CachingMetadata::ofMaximumAgeInSeconds( $maxAge ) :
						CachingMetadata::fresh()
				)
			);
		} else {
			$this->dataFactory->increment( 'wikibase.quality.constraints.sparql.error' );

			$this->dataFactory->increment(
				"wikibase.quality.constraints.sparql.error.http.{$request->getStatus()}"
			);

			if ( $this->isTimeout( $request->getContent() ) ) {
				$this->dataFactory->increment(
					'wikibase.quality.constraints.sparql.error.timeout'
				);
			}

			throw new SparqlHelperException();
		}
	}

}
