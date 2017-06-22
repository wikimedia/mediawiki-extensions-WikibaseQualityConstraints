<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Helper;

use Config;
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
	 * @var Config
	 */
	private $config;

	/**
	 * @var ValueParser
	 */
	private $timeParser;

	/**
	 * @var TimeValueCalculator
	 */
	private $timeCalculator;

	public function __construct( Config $config ) {
		$this->config = $config;
		$this->timeParser = ( new TimeParserFactory() )->getTimeParser();
		$this->timeCalculator = new TimeValueCalculator();
	}

	/**
	 * Compare two values.
	 * If one of them is null, return 0 (equal).
	 *
	 * @param DataValue|null $lhs left-hand side
	 * @param DataValue|null $rhs right-hand side
	 *
	 * @throws InvalidArgumentException if the values do not both have the same, supported data value type
	 * @return integer An integer less than, equal to, or greater than zero
	 *                 when $lhs is respectively less than, equal to, or greater than $rhs.
	 *                 (In other words, just like the “spaceship” operator <=>.)
	 */
	public function getComparison( DataValue $lhs = null, DataValue $rhs = null ) {
		if ( $lhs === null || $rhs === null ) {
			return 0;
		}

		if ( $lhs->getType() !== $rhs->getType() ) {
			throw new InvalidArgumentException( 'Different data value types' );
		}

		switch ( $lhs->getType() ) {
			case 'time':
				/** @var TimeValue $lhs */
				/** @var TimeValue $rhs */
				$lhsTimestamp = $this->timeCalculator->getTimestamp( $lhs );
				$rhsTimestamp = $this->timeCalculator->getTimestamp( $rhs );

				if ( $lhsTimestamp === $rhsTimestamp ) {
					return 0;
				}

				return $lhsTimestamp < $rhsTimestamp ? -1 : 1;
			case 'quantity':
				/** @var QuantityValue|UnboundedQuantityValue $lhs */
				/** @var QuantityValue|UnboundedQuantityValue $rhs */
				// TODO normalize values: T164371
				$lhsValue = $lhs->getAmount()->getValue();
				$rhsValue = $rhs->getAmount()->getValue();

				if ( $lhsValue === $rhsValue ) {
					return 0;
				}

				return $lhsValue < $rhsValue ? -1 : 1;
		}

		throw new InvalidArgumentException( 'Unsupported data value type' );
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
	 * @return UnboundedQuantityValue
	 */
	public function getDifference( DataValue $minuend, DataValue $subtrahend ) {
		if ( $minuend->getType() === 'time' && $subtrahend->getType() === 'time' ) {
			// difference in years
			// TODO calculate difference in days once we no longer import constraints from statements
			// (then the range for the endpoints will also have units and we can convert as needed)
			if ( !preg_match( '/^([-+]\d{1,16})-/', $minuend->getTime(), $minuendMatches ) ||
				 !preg_match( '/^([-+]\d{1,16})-/', $subtrahend->getTime(), $subtrahendMatches ) ) {
				throw new InvalidArgumentException( 'TimeValue::getTime() did not match expected format' );
			}
			$minuendYear = (float)$minuendMatches[1];
			$subtrahendYear = (float)$subtrahendMatches[1];
			$diff = $minuendYear - $subtrahendYear;
			if ( $minuendYear > 0.0 && $subtrahendYear < 0.0 ) {
				$diff -= 1.0; // there is no year 0, remove it from difference
			} elseif ( $minuendYear < 0.0 && $subtrahendYear > 0.0 ) {
				$diff += 1.0; // there is no year 0, remove it from negative difference
			}
			$unit = $this->config->get( 'WBQualityConstraintsYearUnit' ); // TODO unit for days
			return UnboundedQuantityValue::newFromNumber( $diff, $unit );
		}
		if ( $minuend->getType() === 'quantity' && $subtrahend->getType() === 'quantity' ) {
			// TODO normalize values: T164371
			$diff = (float)$minuend->getAmount()->getValue() - (float)$subtrahend->getAmount()->getValue();
			return UnboundedQuantityValue::newFromNumber( $diff, $minuend->getUnit() );
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
