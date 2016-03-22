<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Helper;

use DataValues\DataValue;
use DataValues\QuantityValue;
use DataValues\TimeValue;
use InvalidArgumentException;

/**
 * Class for helper functions for range checkers.
 *
 * @package WikibaseQuality\ConstraintReport\ConstraintCheck\Helper
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class RangeCheckerHelper {

	public function getComparativeValue( DataValue $dataValue ) {
		if ( $dataValue instanceof TimeValue ) {
			return $dataValue->getTime();
		} elseif ( $dataValue instanceof QuantityValue ) {
			return $dataValue->getAmount()->getValue();
		}

		throw new InvalidArgumentException( 'Unsupported data value type' );
	}

}
