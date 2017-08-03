<?php

namespace WikibaseQuality\ConstraintReport\Tests;

use DataValues\DataValue;
use DataValues\StringValue;
use DataValues\UnboundedQuantityValue;
use Serializers\Serializer;
use ValueFormatters\ValueFormatter;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\EntityId\PlainEntityIdFormatter;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Snak\PropertySomeValueSnak;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Snak\Snak;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;
use WikibaseQuality\ConstraintReport\ConstraintParameterRenderer;
use Wikibase\Repo\Parsers\TimeParserFactory;
use Wikibase\Repo\WikibaseRepo;

/**
 * @author Lucas Werkmeister
 * @license GNU GPL v2+
 */
trait ConstraintParameters {

	use DefaultConfig;

	/**
	 * @var ConstraintParameterParser
	 */
	private $parser;

	/**
	 * @var ConstraintParameterRenderer
	 */
	private $renderer;

	/**
	 * @var Serializer
	 */
	private $snakSerializer;

	/**
	 * @return ConstraintParameterParser
	 */
	public function getConstraintParameterParser() {
		if ( $this->parser === null ) {
			$this->parser = new ConstraintParameterParser(
				$this->getDefaultConfig(),
				WikibaseRepo::getDefaultInstance()->getBaseDataModelDeserializerFactory(),
				$this->getConstraintParameterRenderer()
			);
		}

		return $this->parser;
	}

	public function getConstraintParameterRenderer() {
		if ( $this->renderer === null ) {
			$valueFormatter = $this->getMock( ValueFormatter::class );
			$valueFormatter->method( 'format' )->willReturn( '?' );
			$entityIdFormatter = new PlainEntityIdFormatter();
			$this->renderer = new ConstraintParameterRenderer(
				$entityIdFormatter,
				$valueFormatter
			);
		}

		return $this->renderer;
	}

	/**
	 * @return Serializer
	 */
	private function getSnakSerializer() {
		if ( $this->snakSerializer == null ) {
			$this->snakSerializer = WikibaseRepo::getDefaultInstance()->getBaseDataModelSerializerFactory()->newSnakSerializer();
		}

		return $this->snakSerializer;
	}

	/**
	 * @param string $propertyId property ID serialization
	 * @return array
	 */
	public function propertyParameter( $propertyId ) {
		$propertyParameterId = $this->getDefaultConfig()->get( 'WBQualityConstraintsPropertyId' );
		return [
			$propertyParameterId => [ $this->getSnakSerializer()->serialize(
				new PropertyValueSnak(
					new PropertyId( $propertyParameterId ),
					new EntityIdValue( new PropertyId( $propertyId ) )
				)
			) ]
		];
	}

	/**
	 * @param string[] $properties property ID serializations
	 * @return array
	 */
	public function propertiesParameter( array $properties ) {
		$propertyParameterId = $this->getDefaultConfig()->get( 'WBQualityConstraintsPropertyId' );
		return [
			$propertyParameterId => array_map(
				function( $property ) use ( $propertyParameterId ) {
					$value = new EntityIdValue( new PropertyId( $property ) );
					$snak = new PropertyValueSnak( new PropertyId( $propertyParameterId ), $value );
					return $this->getSnakSerializer()->serialize( $snak );
				},
				$properties
			)
		];
	}

	/**
	 * Convert an abbreviated value for a range endpoint
	 * to a full snak for range constraint parameters.
	 * A numeric argument means a numeric endpoint,
	 * 'now' corresponds to a somevalue snak,
	 * any other string is parsed as a time value,
	 * a DataValue is used directly,
	 * and null corresponds to a novalue snak (open-ended range).
	 *
	 * @param DataValue|int|float|string|null $value
	 * @param string $property property ID serialization
	 * @return Snak
	 */
	private function rangeEndpoint( $value, $property ) {
		$propertyId = new PropertyId( $property );
		if ( $value === null ) {
			return new PropertyNoValueSnak( $propertyId );
		} else {
			if ( is_string( $value ) ) {
				if ( $value === 'now' ) {
					return new PropertySomeValueSnak( $propertyId );
				}
				$timeParser = ( new TimeParserFactory() )->getTimeParser();
				$value = $timeParser->parse( $value );
			} elseif ( is_numeric( $value ) ) {
				$value = UnboundedQuantityValue::newFromNumber( $value );
			}
			return new PropertyValueSnak( $propertyId, $value );
		}
	}

	/**
	 * @param string $type 'quantity' or 'time'
	 * @param DataValue|int|float|string|null $min lower boundary, see rangeEndpoint() for details
	 * @param DataValue|int|float|string|null $max upper boundary, see rangeEndpoint() for details
	 * @return array
	 */
	public function rangeParameter( $type, $min, $max ) {
		$configKey = $type === 'quantity' ? 'Quantity' : 'Date';
		$config = $this->getDefaultConfig();
		$minimumId = $config->get( 'WBQualityConstraintsMinimum' . $configKey . 'Id' );
		$maximumId = $config->get( 'WBQualityConstraintsMaximum' . $configKey . 'Id' );
		$minimumSnak = $this->rangeEndpoint( $min, $minimumId );
		$maximumSnak = $this->rangeEndpoint( $max, $maximumId );
		$snakSerializer = $this->getSnakSerializer();
		return [
			$minimumId => [ $snakSerializer->serialize( $minimumSnak ) ],
			$maximumId => [ $snakSerializer->serialize( $maximumSnak ) ]
		];
	}

	/**
	 * @param string $namespace
	 * @return array
	 */
	public function namespaceParameter( $namespace ) {
		$namespaceId = $this->getDefaultConfig()->get( 'WBQualityConstraintsNamespaceId' );
		$value = new StringValue( $namespace );
		$snak = new PropertyValueSnak( new PropertyId( $namespaceId ), $value );
		return [ $namespaceId => [ $this->getSnakSerializer()->serialize( $snak ) ] ];
	}

	/**
	 * @param string $format
	 * @return array
	 */
	public function formatParameter( $format ) {
		$formatId = $this->getDefaultConfig()->get( 'WBQualityConstraintsFormatAsARegularExpressionId' );
		$value = new StringValue( $format );
		$snak = new PropertyValueSnak( new PropertyId( $formatId ), $value );
		return [ $formatId => [ $this->getSnakSerializer()->serialize( $snak ) ] ];
	}

}
