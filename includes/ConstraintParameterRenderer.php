<?php
namespace WikibaseQuality\ConstraintReport;

use DataValues\DataValue;
use ValueFormatters\ValueFormatter;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\EntityId\EntityIdFormatter;

/**
 * Class ConstraintParameterRenderer
 *
 * Used to format the constraint values for output.
 *
 * @package WikibaseQuality\ConstraintReport
 * @author BP2014N1
 * @license GNU GPL v2+
 */

class ConstraintParameterRenderer{

	/**
	 * Maximum number of displayed values for parameters with multiple ones.
	 *
	 * @var int
	 */
	const MAX_PARAMETER_ARRAY_LENGTH = 10;

	/**
	 *
	 * @var EntityIdFormatter
	 */
	private $entityIdLabelFormatter;

	/**
	 * @var ValueFormatter
	 */
	private $dataValueFormatter;

	/**
	 * @param EntityIdFormatter $entityIdFormatter
	 * @param ValueFormatter $dataValueFormatter
	 */
	public function __construct( EntityIdFormatter $entityIdFormatter,
								 ValueFormatter $dataValueFormatter ) {

		$this->entityIdLabelFormatter = $entityIdFormatter;
		$this->dataValueFormatter = $dataValueFormatter;

	}

	/**
	 * Formats parameter values of constraints.
	 *
	 * @param string|ItemId|PropertyId|DataValue $value
	 *
	 * @return string HTML
	 */
	public function formatValue( $value ) {
		if ( is_string( $value ) ) {
			// Cases like 'Format' 'pattern' or 'minimum'/'maximum' values, which we have stored as
			// strings
			return (htmlspecialchars( $value ));
		} elseif ( $value instanceof EntityId ) {
			// Cases like 'Conflicts with' 'property', to which we can link
			return $this->entityIdLabelFormatter->formatEntityId( $value );
		} else {
			// Cases where we format a DataValue
			return $this->dataValueFormatter->format( $value );
		}
	}

	/**
	 * Formats constraint parameters.
	 *
	 * @param (string|ItemId|PropertyId|DataValue)[]|null $parameters
	 *
	 * @return string HTML
	 */
	public function formatParameters( $parameters ) {
		if ( $parameters === null || count( $parameters ) == 0 ) {
			return null;
		}

		$valueFormatter = function ( $value ) {
			return $this->formatValue( $value );
		};

		$formattedParameters = [];
		foreach ( $parameters as $parameterName => $parameterValue ) {
			$formattedParameterValues = implode( ', ',
				$this->limitArrayLength( array_map( $valueFormatter, $parameterValue ) ) );
			$formattedParameters[] = sprintf( '%s: %s', $parameterName, $formattedParameterValues );
		}

		return implode( '; ', $formattedParameters );
	}

	/**
	 * Cuts an array after n values and appends dots if needed.
	 *
	 * @param array $array
	 *
	 * @return array
	 */
	private function limitArrayLength( array $array ) {
		if ( count( $array ) > self::MAX_PARAMETER_ARRAY_LENGTH ) {
			$array = array_slice( $array, 0, self::MAX_PARAMETER_ARRAY_LENGTH );
			array_push( $array, '...' );
		}

		return $array;
	}

}
