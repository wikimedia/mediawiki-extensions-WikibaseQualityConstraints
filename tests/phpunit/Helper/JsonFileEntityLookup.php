<?php

namespace WikibaseQuality\ConstraintReport\Tests\Helper;

use DataValues\BooleanValue;
use DataValues\Deserializers\DataValueDeserializer;
use DataValues\Geo\Values\GlobeCoordinateValue;
use DataValues\MonolingualTextValue;
use DataValues\MultilingualTextValue;
use DataValues\NumberValue;
use DataValues\QuantityValue;
use DataValues\StringValue;
use DataValues\TimeValue;
use DataValues\UnknownValue;
use Deserializers\Deserializer;
use Wikibase\DataModel\DeserializerFactory;
use Wikibase\DataModel\Entity\BasicEntityIdParser;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Services\Lookup\EntityLookup;

/**
 * @license GPL-2.0-or-later
 * @author BP2014N1
 */
class JsonFileEntityLookup implements EntityLookup {

	/**
	 * Base dir which contains serialized entities as json files.
	 *
	 * @var string
	 */
	private $baseDir;

	/**
	 * @var Deserializer
	 */
	private $entityDeserializer;

	/**
	 * @param string $baseDir
	 */
	public function __construct( $baseDir ) {
		$this->baseDir = $baseDir;

		$factory = new DeserializerFactory(
			new DataValueDeserializer(
				[
					'boolean' => BooleanValue::class,
					'number' => NumberValue::class,
					'string' => StringValue::class,
					'unknown' => UnknownValue::class,
					'globecoordinate' => GlobeCoordinateValue::class,
					'monolingualtext' => MonolingualTextValue::class,
					'multilingualtext' => MultilingualTextValue::class,
					'quantity' => QuantityValue::class,
					'time' => TimeValue::class,
					'wikibase-entityid' => EntityIdValue::class,
				]
			),
			new BasicEntityIdParser()
		);

		$this->entityDeserializer = $factory->newEntityDeserializer();
	}

	/**
	 * Returns the entity with the provided id or null if there is no such entity.
	 *
	 * @param EntityId $entityId
	 *
	 * @return EntityDocument|null
	 */
	public function getEntity( EntityId $entityId ) {
		if ( !$this->hasEntity( $entityId ) ) {
			return null;
		}

		$filePath = $this->buildFilePath( $entityId );
		$serializedEntity = json_decode( file_get_contents( $filePath ), true );

		if ( $serializedEntity === null ) {
			return null;
		}

		return $this->entityDeserializer->deserialize( $serializedEntity );
	}

	/**
	 * Returns whether the given entity can bee looked up using getEntity().
	 *
	 * @param EntityId $entityId
	 *
	 * @return boolean
	 */
	public function hasEntity( EntityId $entityId ) {
		return file_exists( $this->buildFilePath( $entityId ) );
	}

	/**
	 * Returns path of the file, which contains the serialized entity.
	 *
	 * @param EntityId $entityId
	 *
	 * @return string
	 */
	private function buildFilePath( EntityId $entityId ) {
		$filePath = sprintf( '%s/%s.json', $this->baseDir, (string)$entityId );
		return $filePath;
	}

}
