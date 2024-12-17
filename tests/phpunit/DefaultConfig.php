<?php

namespace WikibaseQuality\ConstraintReport\Tests;

use MediaWiki\Config\HashConfig;
use MediaWiki\Config\MutableConfig;

/**
 * @author Lucas Werkmeister
 * @license GPL-2.0-or-later
 */
trait DefaultConfig {

	private static ?MutableConfig $defaultConfig = null;

	public static function getDefaultConfig(): MutableConfig {
		if ( self::$defaultConfig === null ) {
			self::$defaultConfig = new HashConfig();
			$extensionJsonText = file_get_contents( __DIR__ . '/../../extension.json' );
			$extensionJson = json_decode( $extensionJsonText, /* assoc = */ true );
			foreach ( $extensionJson['config'] as $key => $value ) {
				self::$defaultConfig->set( $key, $value['value'] );
			}
			// reduce some limits to make tests run faster
			self::$defaultConfig->set( 'WBQualityConstraintsTypeCheckMaxEntities', 10 );
			// never query remote servers
			self::$defaultConfig->set( 'WBQualityConstraintsSparqlEndpoint', 'http://localhost:65536/' );
			self::$defaultConfig->set( 'WBQualityConstraintsAdditionalSparqlEndpoints', [] );
		}

		return clone self::$defaultConfig;
	}

}
