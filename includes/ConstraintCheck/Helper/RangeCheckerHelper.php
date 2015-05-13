<?php

namespace WikidataQuality\ConstraintReport\ConstraintCheck\Helper;

/**
 * Class for helper functions for range checkers.
 *
 * @package WikidataQuality\ConstraintReport\ConstraintCheck\Helper
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class RangeCheckerHelper {

	public function getComparativeValue( $dataValue ) {
		if ( $dataValue->getType() === 'time' ) {
			return $dataValue->getTime();
		} else {
			// 'quantity'
			return $dataValue->getAmount()->getValue();
		}
	}
}