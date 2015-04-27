<?php

namespace WikidataQuality\ConstraintReport\ConstraintCheck\Helper;

use Wikibase\DataModel\Entity\Item;
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
		if ( is_null( $templateString ) || $templateString === '' ) {
			return array ( '' );
		} else {
			return explode( ',', $this->removeBrackets( str_replace( ' ', '', $templateString ) ) );
		}
	}

	private function parseParameter( $parameter, $asString = false ) {
		if ( $parameter === null ) {
			return 'null';
		}

		if ( $asString ) {
			return "$parameter";
		}

		$startsWith = strtoupper( substr( $parameter, 0, 1 ) );
		if ( $startsWith === 'Q' ) {
			return new ItemId( $parameter );
		} elseif ( $startsWith === 'P' ) {
			return new PropertyId( $parameter );
		}

		return '';
	}

	/**
	 * Helps set/format a single parameter depending on its type.
	 *
	 * @param string $parameter
	 * @param bool $asString
	 *
	 * @return array
	 */
	public function parseSingleParameter( $parameter, $asString = false ) {
		return array ( $this->parseParameter( $parameter, $asString ) );
	}

	/**
	 * Helps set/format the item/class/property parameter arrays according to their respective type.
	 *
	 * @param array $parameterArray
	 * @param bool $asString
	 *
	 * @return array
	 */
	public function parseParameterArray( $parameterArray, $asString = false ) {
		if ( $parameterArray[ 0 ] === '' ) { // parameter not given
			return array ( 'null' );
		} else {
			$array = array ();
			foreach ( $parameterArray as $parameter ) {
				$array[ ] = $this->parseParameter( $parameter, $asString );
			}
			return $array;
		}
	}

	/**
	 * @param $json
	 * @param string $parameter
	 *
	 * @return null|string
	 */
	public function getParameterFromJson( $json, $parameter ) {
		if ( isset( $json->$parameter ) ) {
			return $json->$parameter;
		} else {
			return null;
		}
	}

}