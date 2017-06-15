<?php

namespace WikibaseQuality\ConstraintReport\Tests;

use Serializers\Serializer;
use ValueFormatters\ValueFormatter;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\EntityId\PlainEntityIdFormatter;
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

}
