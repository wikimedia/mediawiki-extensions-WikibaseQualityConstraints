<?php

namespace WikibaseQuality\ConstraintReport\Tests;

use DataValues\DataValue;
use DataValues\MonolingualTextValue;
use DataValues\StringValue;
use DataValues\UnboundedQuantityValue;
use InvalidArgumentException;
use MockMessageLocalizer;
use Serializers\Serializer;
use ValueFormatters\ValueFormatter;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\EntityId\PlainEntityIdFormatter;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Snak\PropertySomeValueSnak;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Snak\Snak;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\Context;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;
use WikibaseQuality\ConstraintReport\ConstraintParameterRenderer;
use Wikibase\Repo\Parsers\TimeParserFactory;
use Wikibase\Repo\WikibaseRepo;

/**
 * @author Lucas Werkmeister
 * @license GPL-2.0-or-later
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
				$this->getConstraintParameterRenderer(),
				[ '' => 'http://wikibase.example/entity/' ]
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
				$valueFormatter,
				new MockMessageLocalizer(),
				$this->getDefaultConfig()
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
	 * @param string[] $classIds item ID serializations
	 * @return array[]
	 */
	public function classParameter( array $classIds ) {
		$classParameterId = $this->getDefaultConfig()->get( 'WBQualityConstraintsClassId' );
		return [
			$classParameterId => array_map(
				function( $classId ) use ( $classParameterId ) {
					return $this->getSnakSerializer()->serialize(
						new PropertyValueSnak(
							new PropertyId( $classParameterId ),
							new EntityIdValue( new ItemId( $classId ) )
						)
					);
				},
				$classIds
			)
		];
	}

	/**
	 * @param string $relation 'instance', 'subclass', or 'instanceOrSubclass'
	 * @return array[]
	 */
	public function relationParameter( $relation ) {
		$relationParameterId = $this->getDefaultConfig()->get( 'WBQualityConstraintsRelationId' );
		switch ( $relation ) {
			case 'instance':
				$configKey = 'WBQualityConstraintsInstanceOfRelationId';
				break;
			case 'subclass':
				$configKey = 'WBQualityConstraintsSubclassOfRelationId';
				break;
			case 'instanceOrSubclass':
				$configKey = 'WBQualityConstraintsInstanceOrSubclassOfRelationId';
				break;
			default:
				throw new InvalidArgumentException( '$relation must be instance or subclass' );
		}
		return [
			$relationParameterId => [ $this->getSnakSerializer()->serialize(
				new PropertyValueSnak(
					new PropertyId( $relationParameterId ),
					new EntityIdValue( new ItemId( $this->getDefaultConfig()->get( $configKey ) ) )
				)
			) ]
		];
	}

	/**
	 * @param string $propertyId
	 * @return array[]
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
	 * @return array[]
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
	 * @param (string|Snak)[] $items item ID serializations or snaks
	 * @return array[]
	 */
	public function itemsParameter( array $items ) {
		$qualifierParameterId = $this->getDefaultConfig()->get( 'WBQualityConstraintsQualifierOfPropertyConstraintId' );
		return [
			$qualifierParameterId => array_map(
				function( $item ) use ( $qualifierParameterId ) {
					if ( $item instanceof Snak ) {
						$snak = $item;
					} else {
						$value = new EntityIdValue( new ItemId( $item ) );
						$snak = new PropertyValueSnak( new PropertyId( $qualifierParameterId ), $value );
					}
					return $this->getSnakSerializer()->serialize( $snak );
				},
				$items
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
	 * @return array[]
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
	 * @return array[]
	 */
	public function namespaceParameter( $namespace ) {
		$namespaceId = $this->getDefaultConfig()->get( 'WBQualityConstraintsNamespaceId' );
		$value = new StringValue( $namespace );
		$snak = new PropertyValueSnak( new PropertyId( $namespaceId ), $value );
		return [ $namespaceId => [ $this->getSnakSerializer()->serialize( $snak ) ] ];
	}

	/**
	 * @param string $format
	 * @return array[]
	 */
	public function formatParameter( $format ) {
		$formatId = $this->getDefaultConfig()->get( 'WBQualityConstraintsFormatAsARegularExpressionId' );
		$value = new StringValue( $format );
		$snak = new PropertyValueSnak( new PropertyId( $formatId ), $value );
		return [ $formatId => [ $this->getSnakSerializer()->serialize( $snak ) ] ];
	}

	/**
	 * @param string $languageCode
	 * @param string $syntaxClarification
	 * @return array[]
	 */
	public function syntaxClarificationParameter( $languageCode, $syntaxClarification ) {
		$syntaxClarificationId = $this->getDefaultConfig()->get( 'WBQualityConstraintsSyntaxClarificationId' );
		$value = new MonolingualTextValue( $languageCode, $syntaxClarificationId );
		$snak = new PropertyValueSnak( new PropertyId( $syntaxClarificationId ), $value );
		return [ $syntaxClarificationId => [ $this->getSnakSerializer()->serialize( $snak ) ] ];
	}

	/**
	 * @param string[] $exceptions item ID serializations (other entity types currently not supported)
	 * @return array[]
	 */
	public function exceptionsParameter( $exceptions ) {
		$exceptionId = $this->getDefaultConfig()->get( 'WBQualityConstraintsExceptionToConstraintId' );
		return [ $exceptionId => array_map(
			function ( $exception ) use ( $exceptionId ) {
				$value = new EntityIdValue( new ItemId( $exception ) );
				$snak = new PropertyValueSnak( new PropertyId( $exceptionId ), $value );
				return $this->getSnakSerializer()->serialize( $snak );
			},
			$exceptions
		) ];
	}

	/**
	 * @param string $status (the only currently supported status is 'mandatory')
	 * @return array[]
	 */
	public function statusParameter( $status ) {
		$statusParameterId = $this->getDefaultConfig()->get( 'WBQualityConstraintsConstraintStatusId' );
		switch ( $status ) {
			case 'mandatory':
				$configKey = 'WBQualityConstraintsMandatoryConstraintId';
				break;
			default:
				throw new InvalidArgumentException( '$status must be mandatory' );
		}
		return [
			$statusParameterId => [ $this->getSnakSerializer()->serialize(
				new PropertyValueSnak(
					new PropertyId( $statusParameterId ),
					new EntityIdValue( new ItemId( $this->getDefaultConfig()->get( $configKey ) ) )
				)
			) ]
		];
	}

	/**
	 * @param string[] $scopes Context::TYPE_* constants
	 * @return array
	 */
	public function scopeParameter( array $scopes ) {
		$config = $this->getDefaultConfig();
		$constraintScopeParameterId = $config->get( 'WBQualityConstraintsConstraintScopeId' );
		$itemIds = [];
		foreach ( $scopes as $scope ) {
			switch ( $scope ) {
				case Context::TYPE_STATEMENT:
					$itemIds[] = $config->get( 'WBQualityConstraintsConstraintCheckedOnMainValueId' );
					break;
				case Context::TYPE_QUALIFIER:
					$itemIds[] = $config->get( 'WBQualityConstraintsConstraintCheckedOnQualifiersId' );
					break;
				case Context::TYPE_REFERENCE:
					$itemIds[] = $config->get( 'WBQualityConstraintsConstraintCheckedOnReferencesId' );
					break;
				default:
					$this->assertTrue( false, 'unknown context type ' . $scope );
			}
		}
		return [ $constraintScopeParameterId => array_map(
			function ( $itemId ) use ( $constraintScopeParameterId ) {
				return $this->getSnakSerializer()->serialize(
					new PropertyValueSnak(
						new PropertyId( $constraintScopeParameterId ),
						new EntityIdValue( new ItemId( $itemId ) )
					)
				);
			},
			$itemIds
		) ];
	}

}
