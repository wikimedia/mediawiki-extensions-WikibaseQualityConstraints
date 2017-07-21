<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Helper;

use Config;
use DataValues\DataValue;
use DataValues\StringValue;
use DataValues\UnboundedQuantityValue;
use InvalidArgumentException;
use Wikibase\DataModel\DeserializerFactory;
use Wikibase\DataModel\Deserializers\SnakDeserializer;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Snak\PropertySomeValueSnak;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Snak\Snak;
use Wikibase\Repo\Parsers\TimeParserFactory;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ItemIdSnakValue;
use WikibaseQuality\ConstraintReport\ConstraintParameterRenderer;
use WikibaseQuality\ConstraintReport\Role;

/**
 * Helper for parsing constraint parameters
 * that were imported from constraint statements.
 *
 * All public methods of this class expect constraint parameters
 * (see {@link \WikibaseQuality\Constraint::getConstraintParameters()})
 * and return parameter objects or throw {@link ConstraintParameterException}s.
 * The results are used by the checkers,
 * which may include rendering them into violation messages.
 *
 * @package WikibaseQuality\ConstraintReport\ConstraintCheck\Helper
 * @author Lucas Werkmeister
 * @license GNU GPL v2+
 */
class ConstraintParameterParser {

	/**
	 * @var Config
	 */
	private $config;

	/**
	 * @var SnakDeserializer
	 */
	private $snakDeserializer;

	/**
	 * @var ConstraintParameterRenderer
	 */
	private $constraintParameterRenderer;

	/**
	 * @param Config $config
	 *   contains entity IDs used in constraint parameters (constraint statement qualifiers)
	 * @param DeserializerFactory $factory
	 *   used to parse constraint statement qualifiers into constraint parameters
	 * @param ConstraintParameterRenderer $constraintParameterRenderer
	 *   used to render incorrect parameters for error messages
	 */
	public function __construct(
		Config $config,
		DeserializerFactory $factory,
		ConstraintParameterRenderer $constraintParameterRenderer
	) {
		$this->config = $config;
		$this->snakDeserializer = $factory->newSnakDeserializer();
		$this->constraintParameterRenderer = $constraintParameterRenderer;
	}

	/**
	 * Require that $parameters contains exactly one $parameterId parameter.
	 * @param array $parameters
	 * @param string $parameterId
	 * @throws ConstraintParameterException
	 */
	private function requireSingleParameter( array $parameters, $parameterId ) {
		if ( count( $parameters[$parameterId] ) !== 1 ) {
			throw new ConstraintParameterException(
				wfMessage( 'wbqc-violation-message-parameter-single' )
					->rawParams( $this->constraintParameterRenderer->formatPropertyId( $parameterId, Role::CONSTRAINT_PARAMETER_PROPERTY ) )
					->escaped()
			);
		}
	}

	/**
	 * Require that $snak is a {@link PropertyValueSnak}.
	 * @param Snak $snak
	 * @param string $parameterId
	 * @return void
	 * @throws ConstraintParameterException
	 */
	private function requireValueParameter( Snak $snak, $parameterId ) {
		if ( !( $snak instanceof PropertyValueSnak ) ) {
			throw new ConstraintParameterException(
				wfMessage( 'wbqc-violation-message-parameter-value' )
					->rawParams( $this->constraintParameterRenderer->formatPropertyId( $parameterId, Role::CONSTRAINT_PARAMETER_PROPERTY ) )
					->escaped()
			);
		}
	}

	/**
	 * Parse a single entity ID parameter.
	 * @param array $snakSerialization
	 * @param string $parameterId
	 * @throws ConstraintParameterException
	 * @return EntityId
	 */
	private function parseEntityIdParameter( array $snakSerialization, $parameterId ) {
		$snak = $this->snakDeserializer->deserialize( $snakSerialization );
		$this->requireValueParameter( $snak, $parameterId );
		$value = $snak->getDataValue();
		if ( $value instanceof EntityIdValue ) {
			return $value->getEntityId();
		} else {
			throw new ConstraintParameterException(
				wfMessage( 'wbqc-violation-message-parameter-entity' )
					->rawParams(
						$this->constraintParameterRenderer->formatPropertyId( $parameterId, Role::CONSTRAINT_PARAMETER_PROPERTY ),
						$this->constraintParameterRenderer->formatDataValue( $value, Role::CONSTRAINT_PARAMETER_VALUE )
					)
					->escaped()
			);
		}
	}

	private function parseClassParameterFromStatement( array $constraintParameters ) {
		$classId = $this->config->get( 'WBQualityConstraintsClassId' );
		$classes = [];
		foreach ( $constraintParameters[$classId] as $class ) {
			$classes[] = $this->parseEntityIdParameter( $class, $classId )->getSerialization();
		}
		return $classes;
	}

	private function parseClassParameterFromTemplate( array $constraintParameters ) {
		return explode( ',', $constraintParameters['class'] );
	}

	/**
	 * @param array $constraintParameters see {@link \WikibaseQuality\Constraint::getConstraintParameters()}
	 * @param string $constraintTypeItemId used in error messages
	 * @throws ConstraintParameterException if the parameter is invalid or missing
	 * @return string[] class entity ID serializations
	 */
	public function parseClassParameter( array $constraintParameters, $constraintTypeItemId ) {
		$classId = $this->config->get( 'WBQualityConstraintsClassId' );
		if ( array_key_exists( $classId, $constraintParameters ) ) {
			return $this->parseClassParameterFromStatement( $constraintParameters );
		} elseif ( array_key_exists( 'class', $constraintParameters ) ) {
			return $this->parseClassParameterFromTemplate( $constraintParameters );
		} else {
			throw new ConstraintParameterException(
				wfMessage( 'wbqc-violation-message-parameter-needed' )
					->rawParams( $this->constraintParameterRenderer->formatItemId( $constraintTypeItemId, Role::CONSTRAINT_TYPE_ITEM ) )
					->rawParams( $this->constraintParameterRenderer->formatPropertyId( $classId, Role::CONSTRAINT_PARAMETER_PROPERTY ) )
					->escaped()
			);
		}
	}

	private function parseRelationParameterFromStatement( array $constraintParameters ) {
		$relationId = $this->config->get( 'WBQualityConstraintsRelationId' );
		$this->requireSingleParameter( $constraintParameters, $relationId );
		$relationEntityId = $this->parseEntityIdParameter( $constraintParameters[$relationId][0], $relationId );
		$instanceId = $this->config->get( 'WBQualityConstraintsInstanceOfRelationId' );
		$subclassId = $this->config->get( 'WBQualityConstraintsSubclassOfRelationId' );
		switch ( $relationEntityId ) {
			case $instanceId:
				return 'instance';
			case $subclassId:
				return 'subclass';
			default:
				throw new ConstraintParameterException(
					wfMessage( 'wbqc-violation-message-parameter-oneof' )
						->rawParams( $this->constraintParameterRenderer->formatPropertyId( $relationId, Role::CONSTRAINT_PARAMETER_PROPERTY ) )
						->numParams( 2 )
						->rawParams( $this->constraintParameterRenderer->formatItemIdList( [ $instanceId, $subclassId ], Role::CONSTRAINT_PARAMETER_VALUE ) )
						->escaped()
				);
		}
	}

	private function parseRelationParameterFromTemplate( array $constraintParameters ) {
		$relation = $constraintParameters['relation'];
		if ( $relation === 'instance' || $relation === 'subclass' ) {
			return $relation;
		} else {
			throw new ConstraintParameterException(
				wfMessage( 'wbqc-violation-message-type-relation-instance-or-subclass' )
					->escaped()
			);
		}
	}

	/**
	 * @param array $constraintParameters see {@link \WikibaseQuality\Constraint::getConstraintParameters()}
	 * @param string $constraintTypeItemId used in error messages
	 * @throws ConstraintParameterException if the parameter is invalid or missing
	 * @return string 'instance' or 'subclass'
	 */
	public function parseRelationParameter( array $constraintParameters, $constraintTypeItemId ) {
		$relationId = $this->config->get( 'WBQualityConstraintsRelationId' );
		if ( array_key_exists( $relationId, $constraintParameters ) ) {
			return $this->parseRelationParameterFromStatement( $constraintParameters );
		} elseif ( array_key_exists( 'relation', $constraintParameters ) ) {
			return $this->parseRelationParameterFromTemplate( $constraintParameters );
		} else {
			throw new ConstraintParameterException(
				wfMessage( 'wbqc-violation-message-parameter-needed' )
					->rawParams( $this->constraintParameterRenderer->formatItemId( $constraintTypeItemId, Role::CONSTRAINT_TYPE_ITEM ) )
					->rawParams( $this->constraintParameterRenderer->formatPropertyId( $relationId, Role::CONSTRAINT_PARAMETER_PROPERTY ) )
					->escaped()
			);
		}
	}

	/**
	 * Parse a single property ID parameter.
	 * @param array $snakSerialization
	 * @param string $parameterId used in error messages
	 * @throws ConstraintParameterException
	 * @return PropertyId
	 */
	private function parsePropertyIdParameter( array $snakSerialization, $parameterId ) {
		$snak = $this->snakDeserializer->deserialize( $snakSerialization );
		$this->requireValueParameter( $snak, $parameterId );
		$value = $snak->getDataValue();
		if ( $value instanceof EntityIdValue ) {
			$id = $value->getEntityId();
			if ( $id instanceof PropertyId ) {
				return $id;
			}
		}
		throw new ConstraintParameterException(
			wfMessage( 'wbqc-violation-message-parameter-property' )
				->rawParams(
					$this->constraintParameterRenderer->formatPropertyId( $parameterId, Role::CONSTRAINT_PARAMETER_PROPERTY ),
					$this->constraintParameterRenderer->formatDataValue( $value, Role::CONSTRAINT_PARAMETER_VALUE )
				)
				->escaped()
		);
	}

	private function parsePropertyParameterFromStatement( array $constraintParameters ) {
		$propertyIdString = $this->config->get( 'WBQualityConstraintsPropertyId' );
		$this->requireSingleParameter( $constraintParameters, $propertyIdString );
		return $this->parsePropertyIdParameter( $constraintParameters[$propertyIdString][0], $propertyIdString );
	}

	private function parsePropertyParameterFromTemplate( array $constraintParameters ) {
		try {
			$properties = explode( ',', $constraintParameters['property'] );
			return new PropertyId( $properties[0] ); // silently ignore extra properties (Mandatory Qualifiers used to allow several properties)
		} catch ( InvalidArgumentException $e ) {
			throw new ConstraintParameterException(
				wfMessage( 'wbqc-violation-message-parameter-property' )
					->rawParams(
						$this->constraintParameterRenderer->formatPropertyId( 'property', Role::CONSTRAINT_PARAMETER_PROPERTY ),
						$this->constraintParameterRenderer->formatDataValue( new StringValue( $constraintParameters['property'] ), Role::CONSTRAINT_PARAMETER_VALUE )
					)
					->escaped()
			);
		}
	}

	/**
	 * @param array $constraintParameters see {@link \WikibaseQuality\Constraint::getConstraintParameters()}
	 * @param string $constraintTypeItemId used in error messages
	 * @throws ConstraintParameterException if the parameter is invalid or missing
	 * @return PropertyId
	 */
	public function parsePropertyParameter( array $constraintParameters, $constraintTypeItemId ) {
		$propertyId = $this->config->get( 'WBQualityConstraintsPropertyId' );
		if ( array_key_exists( $propertyId, $constraintParameters ) ) {
			return $this->parsePropertyParameterFromStatement( $constraintParameters );
		} elseif ( array_key_exists( 'property', $constraintParameters ) ) {
			return $this->parsePropertyParameterFromTemplate( $constraintParameters );
		} else {
			throw new ConstraintParameterException(
				wfMessage( 'wbqc-violation-message-parameter-needed' )
					->rawParams( $this->constraintParameterRenderer->formatItemId( $constraintTypeItemId, Role::CONSTRAINT_TYPE_ITEM ) )
					->rawParams( $this->constraintParameterRenderer->formatPropertyId( $propertyId, Role::CONSTRAINT_PARAMETER_PROPERTY ) )
					->escaped()
			);
		}
	}

	private function parseItemIdParameter( PropertyValueSnak $snak, $parameterId ) {
		if ( $snak->getDataValue() instanceof EntityIdValue &&
			$snak->getDataValue()->getEntityId() instanceof ItemId
		) {
			return ItemIdSnakValue::fromItemId( $snak->getDataValue()->getEntityId() );
		} else {
			throw new ConstraintParameterException(
				wfMessage( 'wbqc-violation-message-parameter-item' )
					->rawParams(
						$this->constraintParameterRenderer->formatPropertyId( $parameterId, Role::CONSTRAINT_PARAMETER_PROPERTY ),
						$this->constraintParameterRenderer->formatDataValue( $snak->getDataValue(), Role::CONSTRAINT_PARAMETER_VALUE )
					)
					->escaped()
			);
		}
	}

	private function parseItemsParameterFromStatement( array $constraintParameters ) {
		$qualifierId = $this->config->get( 'WBQualityConstraintsQualifierOfPropertyConstraintId' );
		$values = [];
		foreach ( $constraintParameters[$qualifierId] as $parameter ) {
			$snak = $this->snakDeserializer->deserialize( $parameter );
			switch ( true ) {
				case $snak instanceof PropertyValueSnak:
					$values[] = $this->parseItemIdParameter( $snak, $qualifierId );
					break;
				case $snak instanceof PropertySomeValueSnak:
					$values[] = ItemIdSnakValue::someValue();
					break;
				case $snak instanceof PropertyNoValueSnak:
					$values[] = ItemIdSnakValue::noValue();
					break;
			}
		}
		return $values;
	}

	private function parseItemsParameterFromTemplate( array $constraintParameters ) {
		$values = [];
		foreach ( explode( ',', $constraintParameters['item'] ) as $value ) {
			switch ( $value ) {
				case 'somevalue':
					$values[] = ItemIdSnakValue::someValue();
					break;
				case 'novalue':
					$values[] = ItemIdSnakValue::noValue();
					break;
				default:
					try {
						$values[] = ItemIdSnakValue::fromItemId( new ItemId( $value ) );
						break;
					} catch ( InvalidArgumentException $e ) {
						throw new ConstraintParameterException(
							wfMessage( 'wbqc-violation-message-parameter-item' )
								->params( 'item', $value )
								->escaped()
						);
					}
			}
		}
		return $values;
	}

	/**
	 * @param array $constraintParameters see {@link \WikibaseQuality\Constraint::getConstraintParameters()}
	 * @param string $constraintTypeItemId used in error messages
	 * @param bool $required whether the parameter is required (error if absent) or not ([] if absent)
	 * @throws ConstraintParameterException if the parameter is invalid or missing
	 * @return ItemIdSnakValue[] array of values
	 */
	public function parseItemsParameter( array $constraintParameters, $constraintTypeItemId, $required ) {
		$qualifierId = $this->config->get( 'WBQualityConstraintsQualifierOfPropertyConstraintId' );
		if ( array_key_exists( $qualifierId, $constraintParameters ) ) {
			return $this->parseItemsParameterFromStatement( $constraintParameters );
		} elseif ( array_key_exists( 'item', $constraintParameters ) ) {
			return $this->parseItemsParameterFromTemplate( $constraintParameters );
		} else {
			if ( $required ) {
				throw new ConstraintParameterException(
					wfMessage( 'wbqc-violation-message-parameter-needed' )
						->rawParams( $this->constraintParameterRenderer->formatItemId( $constraintTypeItemId, Role::CONSTRAINT_TYPE_ITEM ) )
						->rawParams( $this->constraintParameterRenderer->formatPropertyId( $qualifierId, Role::CONSTRAINT_PARAMETER_PROPERTY ) )
						->escaped()
				);
			} else {
				return [];
			}
		}
	}

	private function parsePropertiesParameterFromStatement( array $constraintParameters ) {
		$propertyId = $this->config->get( 'WBQualityConstraintsPropertyId' );
		$parameters = $constraintParameters[$propertyId];
		if ( count( $parameters ) === 1 &&
			$this->snakDeserializer->deserialize( $parameters[0] ) instanceof PropertyNoValueSnak
		) {
			return [];
		}

		$properties = [];
		foreach ( $parameters as $parameter ) {
			$properties[] = $this->parsePropertyIdParameter( $parameter, $propertyId );
		}
		return $properties;
	}

	private function parsePropertiesParameterFromTemplate( array $constraintParameters ) {
		if ( $constraintParameters['property'] === '' ) {
			return [];
		}
		return array_map(
			function( $property ) {
				try {
					return new PropertyId( $property );
				} catch ( InvalidArgumentException $e ) {
					throw new ConstraintParameterException(
						wfMessage( 'wbqc-violation-message-parameter-property' )
							->rawParams(
								$this->constraintParameterRenderer->formatPropertyId( 'property', Role::CONSTRAINT_PARAMETER_PROPERTY ),
								$this->constraintParameterRenderer->formatDataValue( new StringValue( $property ), Role::CONSTRAINT_PARAMETER_VALUE )
							)
							->escaped()
					);
				}
			},
			explode( ',', $constraintParameters['property'] )
		);
	}

	/**
	 * @param array $constraintParameters see {@link \WikibaseQuality\Constraint::getConstraintParameters()}
	 * @param string $constraintTypeItemId used in error messages
	 * @throws ConstraintParameterException if the parameter is invalid or missing
	 * @return PropertyId[]
	 */
	public function parsePropertiesParameter( array $constraintParameters, $constraintTypeItemId ) {
		$propertyId = $this->config->get( 'WBQualityConstraintsPropertyId' );
		if ( array_key_exists( $propertyId, $constraintParameters ) ) {
			return $this->parsePropertiesParameterFromStatement( $constraintParameters );
		} elseif ( array_key_exists( 'property', $constraintParameters ) ) {
			return $this->parsePropertiesParameterFromTemplate( $constraintParameters );
		} else {
			throw new ConstraintParameterException(
				wfMessage( 'wbqc-violation-message-parameter-needed' )
					->rawParams( $this->constraintParameterRenderer->formatItemId( $constraintTypeItemId, Role::CONSTRAINT_TYPE_ITEM ) )
					->rawParams( $this->constraintParameterRenderer->formatPropertyId( $propertyId, Role::CONSTRAINT_PARAMETER_PROPERTY ) )
					->escaped()
			);
		}
	}

	/**
	 * @param array $snakSerialization
	 * @param string $parameterId
	 * @throws ConstraintParameterException
	 * @return DataValue|null
	 */
	private function parseValueOrNoValueParameter( array $snakSerialization, $parameterId ) {
		$snak = $this->snakDeserializer->deserialize( $snakSerialization );
		if ( $snak instanceof PropertyValueSnak ) {
			return $snak->getDataValue();
		} elseif ( $snak instanceof PropertyNoValueSnak ) {
			return null;
		} else {
			throw new ConstraintParameterException(
				wfMessage( 'wbqc-violation-message-parameter-value-or-novalue' )
					->rawParams( $this->constraintParameterRenderer->formatPropertyId( $parameterId, Role::CONSTRAINT_PARAMETER_PROPERTY ) )
					->escaped()
			);
		}
	}

	/**
	 * @param array $snakSerialization
	 * @param string $parameterId
	 * @return DataValue|null
	 */
	private function parseValueOrNoValueOrNowParameter( array $snakSerialization, $parameterId ) {
		try {
			return $this->parseValueOrNoValueParameter( $snakSerialization, $parameterId );
		} catch ( ConstraintParameterException $e ) {
			// unknown value means “now”
			$timeParser = ( new TimeParserFactory() )->getTimeParser();
			return $timeParser->parse( gmdate( '+Y-m-d\T00:00:00\Z' ) );
		}
	}

	private function parseRangeParameterFromStatement( array $constraintParameters, $configKey ) {
		$minimumId = $this->config->get( 'WBQualityConstraintsMinimum' . $configKey . 'Id' );
		$maximumId = $this->config->get( 'WBQualityConstraintsMaximum' . $configKey . 'Id' );
		$this->requireSingleParameter( $constraintParameters, $minimumId );
		$this->requireSingleParameter( $constraintParameters, $maximumId );
		$parseFunction = $configKey === 'Date' ? 'parseValueOrNoValueOrNowParameter' : 'parseValueOrNoValueParameter';
		return [
			$this->$parseFunction( $constraintParameters[$minimumId][0], $minimumId ),
			$this->$parseFunction( $constraintParameters[$maximumId][0], $maximumId )
		];
	}

	private function parseRangeParameterFromTemplate( array $constraintParameters, $type ) {
		// the template parameters are always …_quantity, see T164087
		$this->requireSingleParameter( $constraintParameters, 'minimum_quantity' );
		$this->requireSingleParameter( $constraintParameters, 'maximum_quantity' );
		if ( $type === 'quantity' ) {
			$min = UnboundedQuantityValue::newFromNumber( $constraintParameters['minimum_quantity'] );
			$max = UnboundedQuantityValue::newFromNumber( $constraintParameters['maximum_quantity'] );
		} else {
			$timeParser = ( new TimeParserFactory() )->getTimeParser();
			$minStr = $constraintParameters['minimum_quantity'];
			$maxStr = $constraintParameters['maximum_quantity'];
			$now = gmdate( '+Y-m-d\T00:00:00\Z' );
			if ( $minStr === 'now' ) {
				$minStr = $now;
			}
			if ( $maxStr === 'now' ) {
				$maxStr = $now;
			}
			$min = $timeParser->parse( $minStr );
			$max = $timeParser->parse( $maxStr );
		}
		return [ $min, $max ];
	}

	/**
	 * @param array $constraintParameters see {@link \WikibaseQuality\Constraint::getConstraintParameters()}
	 * @param string $constraintTypeItemId used in error messages
	 * @param string $type 'quantity' or 'time' (can be data type or data value type)
	 * @throws ConstraintParameterException if the parameter is invalid or missing
	 * @return DataValue[] a pair of two quantity-type data values, either of which may be null to signify an open range
	 */
	public function parseRangeParameter( array $constraintParameters, $constraintTypeItemId, $type ) {
		switch ( $type ) {
			case 'quantity':
				$configKey = 'Quantity';
				break;
			case 'time':
				$configKey = 'Date';
				break;
			default:
				throw new ConstraintParameterException(
					wfMessage( 'wbqc-violation-message-value-needed-of-types-2' )
						->rawParams(
							wfMessage( 'datatypes-type-quantity' )->escaped(),
							wfMessage( 'datatypes-type-time' )->escaped()
						)
						->escaped()
				);
		}
		$minimumId = $this->config->get( 'WBQualityConstraintsMinimum' . $configKey . 'Id' );
		$maximumId = $this->config->get( 'WBQualityConstraintsMaximum' . $configKey . 'Id' );
		if ( array_key_exists( $minimumId, $constraintParameters ) &&
			array_key_exists( $maximumId, $constraintParameters )
		) {
			return $this->parseRangeParameterFromStatement( $constraintParameters, $configKey );
		} elseif ( array_key_exists( 'minimum_quantity', $constraintParameters ) &&
			array_key_exists( 'maximum_quantity', $constraintParameters )
		) {
			// the template parameters are always …_quantity, see T164087
			return $this->parseRangeParameterFromTemplate( $constraintParameters, $type );
		} else {
			throw new ConstraintParameterException(
				wfMessage( 'wbqc-violation-message-range-parameters-needed' )
					->rawParams(
						wfMessage( 'datatypes-type-' . $type )->escaped(),
						$this->constraintParameterRenderer->formatPropertyId( $minimumId, Role::CONSTRAINT_PARAMETER_PROPERTY ),
						$this->constraintParameterRenderer->formatPropertyId( $maximumId, Role::CONSTRAINT_PARAMETER_PROPERTY )
					)
					->rawParams( $this->constraintParameterRenderer->formatItemId( $constraintTypeItemId, Role::CONSTRAINT_TYPE_ITEM ) )
					->escaped()
			);
		}
	}

	/**
	 * Parse a single string parameter.
	 * @param array $snakSerialization
	 * @param string $parameterId
	 * @throws ConstraintParameterException
	 * @return string
	 */
	private function parseStringParameter( array $snakSerialization, $parameterId ) {
		$snak = $this->snakDeserializer->deserialize( $snakSerialization );
		$this->requireValueParameter( $snak, $parameterId );
		$value = $snak->getDataValue();
		if ( $value instanceof StringValue ) {
			return $value->getValue();
		} else {
			throw new ConstraintParameterException(
				wfMessage( 'wbqc-violation-message-parameter-string' )
					->rawParams(
						$this->constraintParameterRenderer->formatPropertyId( $parameterId, Role::CONSTRAINT_PARAMETER_PROPERTY ),
						$this->constraintParameterRenderer->formatDataValue( $value, Role::CONSTRAINT_PARAMETER_VALUE )
					)
					->escaped()
			);
		}
	}

	private function parseNamespaceParameterFromStatement( array $constraintParameters ) {
		$namespaceId = $this->config->get( 'WBQualityConstraintsNamespaceId' );
		$this->requireSingleParameter( $constraintParameters, $namespaceId );
		return $this->parseStringParameter( $constraintParameters[$namespaceId][0], $namespaceId );
	}

	private function parseNamespaceParameterFromTemplate( array $constraintParameters ) {
		return $constraintParameters['namespace'];
	}

	/**
	 * @param array $constraintParameters see {@link \WikibaseQuality\Constraint::getConstraintParameters()}
	 * @param string $constraintTypeItemId used in error messages
	 * @throws ConstraintParameterException if the parameter is invalid or missing
	 * @return string
	 */
	public function parseNamespaceParameter( array $constraintParameters, $constraintTypeItemId ) {
		$namespaceId = $this->config->get( 'WBQualityConstraintsNamespaceId' );
		if ( array_key_exists( $namespaceId, $constraintParameters ) ) {
			return $this->parseNamespaceParameterFromStatement( $constraintParameters );
		} elseif ( array_key_exists( 'namespace', $constraintParameters ) ) {
			return $this->parseNamespaceParameterFromTemplate( $constraintParameters );
		} else {
			return '';
		}
	}

	private function parseFormatParameterFromStatement( array $constraintParameters ) {
		$formatId = $this->config->get( 'WBQualityConstraintsFormatAsARegularExpressionId' );
		$this->requireSingleParameter( $constraintParameters, $formatId );
		return $this->parseStringParameter( $constraintParameters[$formatId][0], $formatId );
	}

	private function parseFormatParameterFromTemplate( array $constraintParameters ) {
		$pattern = $constraintParameters['pattern'];
		$pattern = htmlspecialchars_decode( $pattern );
		$pattern = str_replace( '<code>', '', $pattern );
		$pattern = str_replace( '</code>', '', $pattern );
		return $pattern;
	}

	/**
	 * @param array $constraintParameters see {@link \WikibaseQuality\Constraint::getConstraintParameters()}
	 * @param string $constraintTypeItemId used in error messages
	 * @throws ConstraintParameterException if the parameter is invalid or missing
	 * @return string
	 */
	public function parseFormatParameter( array $constraintParameters, $constraintTypeItemId ) {
		$formatId = $this->config->get( 'WBQualityConstraintsFormatAsARegularExpressionId' );
		if ( array_key_exists( $formatId, $constraintParameters ) ) {
			return $this->parseFormatParameterFromStatement( $constraintParameters );
		} elseif ( array_key_exists( 'pattern', $constraintParameters ) ) {
			return $this->parseFormatParameterFromTemplate( $constraintParameters );
		} else {
			throw new ConstraintParameterException(
				wfMessage( 'wbqc-violation-message-parameter-needed' )
					->rawParams( $this->constraintParameterRenderer->formatItemId( $constraintTypeItemId, Role::CONSTRAINT_TYPE_ITEM ) )
					->rawParams( $this->constraintParameterRenderer->formatPropertyId( $formatId, Role::CONSTRAINT_PARAMETER_PROPERTY ) )
					->escaped()
			);
		}
	}

	private function parseExceptionParameterFromStatement( array $constraintParameters ) {
		$exceptionId = $this->config->get( 'WBQualityConstraintsExceptionToConstraintId' );
		return array_map(
			function( $snakSerialization ) use ( $exceptionId ) {
				return $this->parseEntityIdParameter( $snakSerialization, $exceptionId );
			},
			$constraintParameters[$exceptionId]
		);
	}

	private function parseExceptionParameterFromTemplate( array $constraintParameters ) {
		if ( $constraintParameters['known_exception'] === '' ) {
			return [];
		}

		return array_map(
			function( $entityIdSerialization ) {
				$entityIdSerialization = strtoupper( $entityIdSerialization );
				$firstLetter = substr( $entityIdSerialization, 0, 1 );
				try {
					switch ( $firstLetter ) {
						case 'Q':
							return new ItemId( $entityIdSerialization );
						case 'P':
							return new PropertyId( $entityIdSerialization );
						default:
							throw new InvalidArgumentException(); // caught below
					}
				} catch ( InvalidArgumentException $e ) {
					$value = new StringValue( $entityIdSerialization );
					throw new ConstraintParameterException(
						wfMessage( 'wbqc-violation-message-parameter-entity' )
							->rawParams(
								$this->constraintParameterRenderer->formatPropertyId( 'known_exception', Role::CONSTRAINT_PARAMETER_PROPERTY ),
								$this->constraintParameterRenderer->formatDataValue( $value, Role::CONSTRAINT_PARAMETER_VALUE )
							)
							->escaped()
					);
				}
			},
			explode( ',', $constraintParameters['known_exception'] )
		);
	}

	/**
	 * @param array $constraintParameters see {@link \WikibaseQuality\Constraint::getConstraintParameters()}
	 * @throws ConstraintParameterException if the parameter is invalid
	 * @return EntityId[]
	 */
	public function parseExceptionParameter( array $constraintParameters ) {
		$exceptionId = $this->config->get( 'WBQualityConstraintsExceptionToConstraintId' );
		if ( array_key_exists( $exceptionId, $constraintParameters ) ) {
			return $this->parseExceptionParameterFromStatement( $constraintParameters );
		} elseif ( array_key_exists( 'known_exception', $constraintParameters ) ) {
			return $this->parseExceptionParameterFromTemplate( $constraintParameters );
		} else {
			return [];
		}
	}

}
