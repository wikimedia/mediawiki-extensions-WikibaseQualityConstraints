<?php

namespace WikidataQuality\ConstraintReport\ConstraintCheck\Helper;

use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;


/**
 * Class ConstraintReportHelper
 * Class for helper functions for constraint checkers.
 *
 * @package WikidataQuality\ConstraintReport\ConstraintCheck\Helper
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class ConstraintReportHelper {

	/**
	 * @param string $templateString
	 *
	 * @return string
	 */
	public function removeBrackets( $templateString ) {
		$toReplace = array ( '{', '}', '|', '[', ']' );
		return str_replace( $toReplace, '', $templateString );
	}

	/**
	 * Used to convert a string containing a comma-separated list (as one gets out of the constraints table) to an array.
	 *
	 * @param string $templateString
	 *
	 * @return array
	 */
	public function stringToArray( $templateString ) {
		if ( is_null( $templateString ) or $templateString === '' ) {
			return array ( '' );
		} else {
			return explode( ',', $this->removeBrackets( str_replace( ' ', '', $templateString ) ) );
		}
	}

	private function parseParameter( $parameter, $type = 'String' ) {
		if ( $parameter === null ) {
			return 'null';
		}

		if ( $type === 'String' ) {
			return "$parameter";
		}

		$startsWith = strtoupper( substr( $parameter, 0, 1 ) );
		if ( $startsWith === 'Q' || $startsWith === 'P' ) {
			$type = 'Wikibase\\DataModel\\Entity\\' . $type;
			return new $type( $parameter );
		}

		return '';
	}

	/**
	 * Helps set/format a single parameter depending on its type.
	 *
	 * @param array $parameter
	 * @param string $type
	 *
	 * @return array
	 */
	public function parseSingleParameter( $parameter, $type = 'String' ) {
		return array ( $this->parseParameter( $parameter, $type ) );
	}

	/**
	 * Helps set/format the item/class/property parameter arrays according to their respective type.
	 *
	 * @param array $parameterArray
	 * @param string $type
	 *
	 * @return array
	 */
	public function parseParameterArray( $parameterArray, $type = 'String' ) {
		if ( $parameterArray[ 0 ] === '' ) { // parameter not given
			return array ( 'null' );
		} else {
			$array = array ();
			foreach ( $parameterArray as $parameter ) {
				$array[ ] = $this->parseParameter( $parameter, $type );
			}
			return $array;
		}
	}

	/**
	 * @param $json
	 * @param string $property
	 *
	 * @return null|string
	 */
	public function getPropertyOfJson( $json, $property ) {
		if ( isset( $json->$property ) ) {
			return $json->$property;
		} else {
			return null;
		}
	}

}