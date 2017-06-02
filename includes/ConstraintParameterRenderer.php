<?php
namespace WikibaseQuality\ConstraintReport;

use DataValues\DataValue;
use InvalidArgumentException;
use ValueFormatters\ValueFormatter;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\EntityId\EntityIdFormatter;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ItemIdSnakValue;

/**
 * Class ConstraintParameterRenderer
 *
 * Used to format the constraint values for output.
 *
 * @package WikibaseQuality\ConstraintReport
 * @author BP2014N1
 * @license GNU GPL v2+
 */

class ConstraintParameterRenderer {

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
	 * @param EntityIdFormatter $entityIdFormatter should return HTML
	 * @param ValueFormatter $dataValueFormatter should return HTML
	 */
	public function __construct(
		EntityIdFormatter $entityIdFormatter,
		ValueFormatter $dataValueFormatter
	) {
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
			return htmlspecialchars( $value );
		} elseif ( $value instanceof EntityId ) {
			// Cases like 'Conflicts with' 'property', to which we can link
			return $this->formatEntityId( $value );
		} elseif ( $value instanceof ItemIdSnakValue ) {
			// Cases like EntityId but can also be somevalue or novalue
			return $this->formatItemIdSnakValue( $value );
		} else {
			// Cases where we format a DataValue
			return $this->formatDataValue( $value );
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

	/**
	 * @param DataValue $value
	 * @return string HTML
	 */
	public function formatDataValue( DataValue $value ) {
		return $this->dataValueFormatter->format( $value );
	}

	/**
	 * @param EntityId $entityId
	 * @return string HTML
	 */
	public function formatEntityId( EntityId $entityId ) {
		return $this->entityIdLabelFormatter->formatEntityId( $entityId );
	}

	/**
	 * Format a property ID parameter, potentially unparsed (string).
	 *
	 * If you know that your property ID is already parsed, use {@see formatEntityId}.
	 *
	 * @param PropertyId|string $propertyId
	 * @return string HTML
	 */
	public function formatPropertyId( $propertyId ) {
		if ( $propertyId instanceof PropertyId ) {
			return $this->formatEntityId( $propertyId );
		} elseif ( is_string( $propertyId ) ) {
			try {
				return $this->formatEntityId( new PropertyId( $propertyId ) );
			} catch ( InvalidArgumentException $e ) {
				return htmlspecialchars( $propertyId );
			}
		} else {
			throw new InvalidArgumentException( '$propertyId must be either PropertyId or string' );
		}
	}

	/**
	 * Format an item ID parameter, potentially unparsed (string).
	 *
	 * If you know that your item ID is already parsed, use {@see formatEntityId}.
	 *
	 * @param ItemId|string $itemId
	 * @return string HTML
	 */
	public function formatItemId( $itemId ) {
		if ( $itemId instanceof ItemId ) {
			return $this->formatEntityId( $itemId );
		} elseif ( is_string( $itemId ) ) {
			try {
				return $this->formatEntityId( new ItemId( $itemId ) );
			} catch ( InvalidArgumentException $e ) {
				return htmlspecialchars( $itemId );
			}
		} else {
			throw new InvalidArgumentException( '$itemId must be either ItemId or string' );
		}
	}

	/**
	 * Format an {@link ItemIdSnakValue} (known value, unknown value, or no value).
	 *
	 * @return string HTML
	 */
	public function formatItemIdSnakValue( ItemIdSnakValue $value ) {
		switch ( true ) {
			case $value->isValue():
				return $this->formatEntityId( $value->getItemId() );
			case $value->isSomeValue():
				return '<span class="wikibase-snakview-variation-somevaluesnak">'
					. wfMessage( 'wikibase-snakview-snaktypeselector-somevalue' )->escaped()
					. '</span>';
			case $value->isNoValue():
				return '<span class="wikibase-snakview-variation-novaluesnak">'
					. wfMessage( 'wikibase-snakview-snaktypeselector-novalue' )->escaped()
					. '</span>';
		}
	}

	/**
	 * Format a list of (potentially unparsed) property IDs.
	 *
	 * The returned array begins with an HTML list of the formatted property IDs
	 * and then contains all the individual formatted property IDs.
	 *
	 * @param PropertyId[]|string[] $propertyIds
	 * @return string[] HTML
	 */
	public function formatPropertyIdList( array $propertyIds ) {
		if ( empty( $propertyIds ) ) {
			return [ '<ul></ul>' ];
		}
		$propertyIds = $this->limitArrayLength( $propertyIds );
		$formattedPropertyIds = array_map( [ $this, "formatPropertyId" ], $propertyIds );
		array_unshift(
			$formattedPropertyIds,
			'<ul><li>' . implode( '</li><li>', $formattedPropertyIds ) . '</li></ul>'
		);
		return $formattedPropertyIds;
	}

	/**
	 * Format a list of (potentially unparsed) item IDs.
	 *
	 * The returned array begins with an HTML list of the formatted item IDs
	 * and then contains all the individual formatted item IDs.
	 *
	 * @param ItemId[]|string[] $itemIds
	 * @return string[] HTML
	 */
	public function formatItemIdList( array $itemIds ) {
		if ( empty( $itemIds ) ) {
			return [ '<ul></ul>' ];
		}
		$itemIds = $this->limitArrayLength( $itemIds );
		$formattedItemIds = array_map( [ $this, "formatItemId" ], $itemIds );
		array_unshift(
			$formattedItemIds,
			'<ul><li>' . implode( '</li><li>', $formattedItemIds ) . '</li></ul>'
		);
		return $formattedItemIds;
	}

	/**
	 * Format a list of {@link ItemIdSnakValue}s (containing known values, unknown values, and/or no values).
	 *
	 * The returned array begins with an HTML list of the formatted values
	 * and then contains all the individual formatted values.
	 *
	 * @param ItemIdSnakValue[] $values
	 * @return string[] HTML
	 */
	public function formatItemIdSnakValueList( array $values ) {
		if ( empty( $values ) ) {
			return [ '<ul></ul>' ];
		}
		$values = $this->limitArrayLength( $values );
		$formattedValues = array_map(
			function( $value ) {
				if ( $value === '...' ) {
					return '...';
				} else {
					return $this->formatItemIdSnakValue( $value );
				}
			},
			$values
		);
		array_unshift(
			$formattedValues,
			'<ul><li>' . implode( '</li><li>', $formattedValues ) . '</li></ul>'
		);
		return $formattedValues;
	}

}
