<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Helper;

use DataValues\DataValue;
use DataValues\QuantityValue;
use DataValues\TimeValue;
use DataValues\TimeValueCalculator;
use DataValues\UnboundedQuantityValue;
use InvalidArgumentException;
use ValueParsers\ValueParser;
use Wikibase\Repo\Parsers\TimeParserFactory;

/**
 * Class for helper functions for range checkers.
 *
 * @package WikibaseQuality\ConstraintReport\ConstraintCheck\Helper
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class RangeCheckerHelper {

	/**
	 * @var ValueParser
	 */
	private $timeParser;

	/**
	 * @var TimeValueCalculator
	 */
	private $timeCalculator;

	public function __construct() {
		$this->timeParser = (new TimeParserFactory())->getTimeParser();
		$this->timeCalculator = new TimeValueCalculator();
	}

	/**
	 * @param DataValue $lhs left-hand side
	 * @param DataValue $rhs right-hand side
	 *
	 * @throws InvalidArgumentException if the values do not both have the same, supported data value type
	 * @return integer An integer less then, equal to, or greater than zero
	 *                 when $lhs is respectively less than, equal to, or greater than $rhs.
	 *                 (In other words, just like the “spaceship” operator <=>.)
	 */
	public function getComparison( DataValue $lhs, DataValue $rhs ) {
		if ( $lhs->getType() === 'time' && $rhs->getType() === 'time' ) {
			$lhsTimestamp = $this->timeCalculator->getTimestamp( $lhs );
			$rhsTimestamp = $this->timeCalculator->getTimestamp( $rhs );
			if ( $lhsTimestamp < $rhsTimestamp ) {
				return -1;
			} elseif ( $lhsTimestamp > $rhsTimestamp ) {
				return 1;
			} else {
				return 0;
			}
		}
		if ( $lhs->getType() === 'quantity' && $rhs->getType() === 'quantity' ) {
			// TODO normalize values: T164371
			$lhsValue = $lhs->getAmount()->getValue();
			$rhsValue = $rhs->getAmount()->getValue();
			if ( $lhsValue < $rhsValue ) {
				return -1;
			} elseif ( $lhsValue > $rhsValue ) {
				return 1;
			} else {
				return 0;
			}
		}

		throw new InvalidArgumentException( 'Unsupported or different data value types' );
	}

	/**
	 * Computes $minuend - $subtrahend, in a format depending on the data type.
	 * For time values, the difference is in years;
	 * otherwise, the difference is simply the numerical difference between the quantities.
	 * (The units of the quantities are currently ignored: see T164371.)
	 *
	 * @param TimeValue|QuantityValue|UnboundedQuantityValue $minuend
	 * @param TimeValue|QuantityValue|UnboundedQuantityValue $subtrahend
	 *
	 * @throws InvalidArgumentException if the values do not both have the same, supported data value type
	 * @return float
	 */
	public function getDifference( DataValue $minuend, DataValue $subtrahend ) {
		if ( $minuend->getType() === 'time' && $subtrahend->getType() === 'time' ) {
			// difference in years
			if ( !preg_match( '/^([-+]\d{1,16})-/', $minuend->getTime(), $minuendMatches ) ||
				 !preg_match( '/^([-+]\d{1,16})-/', $subtrahend->getTime(), $subtrahendMatches ) ) {
				throw new InvalidArgumentException( 'TimeValue::getTime() did not match expected format' );
			}
			$minuendYear = (float)$minuendMatches[1];
			$subtrahendYear = (float)$subtrahendMatches[1];
			$diff = $minuendYear - $subtrahendYear;
			if ( $minuendYear > 0.0 && $subtrahendYear < 0.0 ) {
				$diff -= 1.0; // there is no year 0, remove it from difference
			} elseif ( $minuendYear < 0.0 && $subtrahendYear > 0.0) {
				$diff += 1.0; // there is no year 0, remove it from negative difference
			}
			return $diff;
		}
		if ( $minuend->getType() === 'quantity' && $subtrahend->getType() === 'quantity' ) {
			// TODO normalize values: T164371
			return (float)$minuend->getAmount()->getValue() - (float)$subtrahend->getAmount()->getValue();
		}

		throw new InvalidArgumentException( 'Unsupported or different data value types' );
	}

	/**
	 * @param string $timeString
	 * @return TimeValue
	 */
	public function parseTime( $timeString ) {
		return $this->timeParser->parse( $timeString );
	}

	/**
	 * @param string $quantityString
	 * @return QuantityValue|UnboundedQuantityValue
	 */
	public function parseQuantity( $quantityString ) {
		return UnboundedQuantityValue::newFromNumber( $quantityString );
	}

}
