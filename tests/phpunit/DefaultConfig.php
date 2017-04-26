<?php

namespace WikibaseQuality\ConstraintReport\Tests;

use HashConfig;

/**
 * @author Lucas Werkmeister
 * @license GNU GPL v2+
 */
trait DefaultConfig {

	/**
	 * @var HashConfig
	 */
	private $defaultConfig;

	public function getDefaultConfig() {
		if ( $this->defaultConfig === null ) {
			$this->defaultConfig = new HashConfig();
			$extensionJsonFile = __DIR__ . '/../../extension.json';
			$extensionJsonText = file_get_contents( $extensionJsonFile );
			$extensionJson = json_decode( $extensionJsonText, /* assoc = */ true );
			foreach ( $extensionJson['config'] as $key => $value ) {
				$this->defaultConfig->set( $key, $value['value'] );
			}
		}

		return $this->defaultConfig;
	}

}
