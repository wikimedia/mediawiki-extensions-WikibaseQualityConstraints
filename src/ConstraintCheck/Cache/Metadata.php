<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Cache;

use Wikimedia\Assert\Assert;

/**
 * Collection of information about a value.
 *
 * @author Lucas Werkmeister
 * @license GNU GPL v2+
 */
class Metadata {

	/**
	 * @var CachingMetadata|null
	 */
	private $cachingMetadata = null;

	/**
	 * @var DependencyMetadata|null
	 */
	private $dependencyMetadata = null;

	/**
	 * @return self Empty collection.
	 */
	public static function blank() {
		return new self;
	}

	public static function ofCachingMetadata( CachingMetadata $cachingMetadata ) {
		$ret = new self;
		$ret->cachingMetadata = $cachingMetadata;
		return $ret;
	}

	public static function ofDependencyMetadata( DependencyMetadata $dependencyMetadata ) {
		$ret = new self;
		$ret->dependencyMetadata = $dependencyMetadata;
		return $ret;
	}

	/**
	 * @param self[] $metadatas
	 * @return self
	 */
	public static function merge( array $metadatas ) {
		Assert::parameterElementType( self::class, $metadatas, '$metadatas' );
		$cachingMetadatas = [];
		$dependencyMetadatas = [];
		foreach ( $metadatas as $metadata ) {
			if ( $metadata->cachingMetadata !== null ) {
				$cachingMetadatas[] = $metadata->cachingMetadata;
			}
			if ( $metadata->dependencyMetadata !== null ) {
				$dependencyMetadatas[] = $metadata->dependencyMetadata;
			}
		}
		$ret = new self;
		$ret->cachingMetadata = CachingMetadata::merge( $cachingMetadatas );
		$ret->dependencyMetadata = DependencyMetadata::merge( $dependencyMetadatas );
		return $ret;
	}

	/**
	 * @return CachingMetadata
	 */
	public function getCachingMetadata() {
		return $this->cachingMetadata ?: CachingMetadata::fresh();
	}

	/**
	 * @return DependencyMetadata
	 */
	public function getDependencyMetadata() {
		return $this->dependencyMetadata ?: DependencyMetadata::blank();
	}

}
