<?php

namespace WikibaseQuality\ConstraintReport\Tests;

use DataValues\DataValue;
use Serializers\Serializer;
use ValueFormatters\ValueFormatter;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\EntityId\PlainEntityIdFormatter;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintStatementParameterParser;
use WikibaseQuality\ConstraintReport\ConstraintParameterRenderer;
use Wikibase\Repo\WikibaseRepo;

/**
 * @author Lucas Werkmeister
 * @license GNU GPL v2+
 */
trait ConstraintParameters {

	use DefaultConfig;

	/**
	 * @var ConstraintStatementParameterParser
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
	 * @return ConstraintStatementParameterParser
	 */
	public function getConstraintParameterParser() {
		if ( $this->parser === null ) {
			$this->parser = new ConstraintStatementParameterParser(
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
			$this->snakSerializer = WikibaseRepo::getDefaultInstance()->getSerializerFactory()->newSnakSerializer();
		}

		return $this->snakSerializer;
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
	 * @param string $type 'quantity' or 'time'
	 * @param DataValue|null $min lower boundary, or null to signify no lower boundary
	 * @param DataValue|null $max upper boundary, or null to signfiy no upper boundary
	 * @return array
	 */
	public function rangeParameter( $type, DataValue $min = null, DataValue $max = null ) {
		$configKey = $type === 'quantity' ? 'Quantity' : 'Date';
		$config = $this->getDefaultConfig();
		$minimumId = $config->get( 'WBQualityConstraintsMinimum' . $configKey . 'Id' );
		$maximumId = $config->get( 'WBQualityConstraintsMaximum' . $configKey . 'Id' );
		if ( $min === null ) {
			$minimumSnak = new PropertyNoValueSnak( new PropertyId( $minimumId ) );
		} else {
			$minimumSnak = new PropertyValueSnak( new PropertyId( $minimumId ), $min );
		}
		if ( $max === null ) {
			$maximumSnak = new PropertyNoValueSnak( new PropertyId( $maximumId ) );
		} else {
			$maximumSnak = new PropertyValueSnak( new PropertyId( $maximumId ), $max );
		}
		$snakSerializer = $this->getSnakSerializer();
		return [
			$minimumId => [ $snakSerializer->serialize( $minimumSnak ) ],
			$maximumId => [ $snakSerializer->serialize( $maximumSnak ) ]
		];
	}

}
