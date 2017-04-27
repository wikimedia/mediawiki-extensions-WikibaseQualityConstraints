<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Helper;

use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use InvalidArgumentException;

/**
 * @package WikibaseQuality\ConstraintReport\ConstraintCheck\Helper
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class ConstraintParameterParser {

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
		if ( $templateString === null || $templateString === '' ) {
			return array ( '' );
		} else {
			return explode( ',', $this->removeBrackets( str_replace( ' ', '', $templateString ) ) );
		}
	}

	private function parseParameter( $parameter, $asString = false ) {
		if ( $parameter === null ) {
			return wfMessage( "wbqc-constraintreport-no-parameter" )->escaped();
		}

		if ( $asString ) {
			return strval( $parameter );
		}

		$startsWith = strtoupper( substr( $parameter, 0, 1 ) );

		try {
			if ( $startsWith === 'Q' ) {
				return new ItemId( $parameter );
			} elseif ( $startsWith === 'P' ) {
				return new PropertyId( $parameter );
			}
		} catch ( InvalidArgumentException $e ) {
			return '';
		}

		return '';
	}

	/**
	 * Formats a parameter with a single value and wraps it in an array.
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
	 * Formats a parameter with an array of values and wraps them in an array.
	 *
	 * @param array $parameterArray
	 * @param bool $asString
	 *
	 * @return array
	 */
	public function parseParameterArray( $parameterArray, $asString = false ) {
		if ( $parameterArray[ 0 ] === '' ) { // parameter not given
			return array ( wfMessage( "wbqc-constraintreport-no-parameter" )->escaped() );
		} else {
			$array = array ();
			foreach ( $parameterArray as $parameter ) {
				$array[] = $this->parseParameter( $parameter, $asString );
			}
			return $array;
		}
	}

	/**
	 * @param mixed $json
	 * @param string $parameter
	 *
	 * @return null|string
	 */
	public function getParameterFromJson( $json, $parameter ) {
		return isset( $json->$parameter ) ? $json->$parameter : null;
	}

}
