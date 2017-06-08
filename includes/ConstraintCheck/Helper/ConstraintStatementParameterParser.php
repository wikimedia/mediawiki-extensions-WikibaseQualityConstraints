<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Helper;

use Config;
use DataValues\StringValue;
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
use WikibaseQuality\ConstraintReport\ConstraintCheck\ItemIdSnakValue;
use WikibaseQuality\ConstraintReport\ConstraintParameterRenderer;

/**
 * Helper for parsing constraint parameters
 * that were imported from constraint statements.
 *
 * All public methods of this class expect snak array serializations,
 * as stored by {@link \WikibaseQuality\ConstraintReport\UpdateConstraintsTableJob},
 * and return parameter objects or throw {@link ConstraintParameterException}s.
 * The results are used by the checkers,
 * which may include rendering them into violation messages.
 * (For backwards compatibility, the methods currently also support
 * parsing constraint parameters from templates.
 * This will be removed eventually.)
 *
 * Not to be confused with {@link ConstraintParameterParser},
 * which parses constraint parameters from templates.
 *
 * @package WikibaseQuality\ConstraintReport\ConstraintCheck\Helper
 * @author Lucas Werkmeister
 * @license GNU GPL v2+
 */
class ConstraintStatementParameterParser {

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
					->rawParams( $this->constraintParameterRenderer->formatPropertyId( $parameterId ) )
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
					->rawParams( $this->constraintParameterRenderer->formatPropertyId( $parameterId ) )
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
						$this->constraintParameterRenderer->formatPropertyId( $parameterId ),
						$this->constraintParameterRenderer->formatDataValue( $value )
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
	 * @param array $constraintParameters
	 * @param string $constraintTypeName used in error messages
	 * @throws ConstraintParameterException if the parameter is invalid or missing
	 * @return string[] class entity ID serializations
	 */
	public function parseClassParameter( array $constraintParameters, $constraintTypeName ) {
		$classId = $this->config->get( 'WBQualityConstraintsClassId' );
		if ( array_key_exists( $classId, $constraintParameters ) ) {
			return $this->parseClassParameterFromStatement( $constraintParameters );
		} elseif ( array_key_exists( 'class', $constraintParameters ) ) {
			return $this->parseClassParameterFromTemplate( $constraintParameters );
		} else {
			throw new ConstraintParameterException(
				wfMessage( 'wbqc-violation-message-parameter-needed' )
					->params( $constraintTypeName )
					->rawParams( $this->constraintParameterRenderer->formatPropertyId( $classId ) )
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
						->rawParams( $this->constraintParameterRenderer->formatPropertyId( $relationId ) )
						->numParams( 2 )
						->rawParams( $this->constraintParameterRenderer->formatItemIdList( [ $instanceId, $subclassId ] ) )
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
	 * @param array $constraintParameters
	 * @param string $constraintTypeName used in error messages
	 * @throws ConstraintParameterException if the parameter is invalid or missing
	 * @return string 'instance' or 'subclass'
	 */
	public function parseRelationParameter( array $constraintParameters, $constraintTypeName ) {
		$relationId = $this->config->get( 'WBQualityConstraintsRelationId' );
		if ( array_key_exists( $relationId, $constraintParameters ) ) {
			return $this->parseRelationParameterFromStatement( $constraintParameters );
		} elseif ( array_key_exists( 'relation', $constraintParameters ) ) {
			return $this->parseRelationParameterFromTemplate( $constraintParameters );
		} else {
			throw new ConstraintParameterException(
				wfMessage( 'wbqc-violation-message-parameter-needed' )
					->params( $constraintTypeName )
					->rawParams( $this->constraintParameterRenderer->formatPropertyId( $relationId ) )
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
					$this->constraintParameterRenderer->formatPropertyId( $parameterId ),
					$this->constraintParameterRenderer->formatDataValue( $value )
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
			array_map( 'strtoupper', $properties );
			return new PropertyId( $properties[0] ); // silently ignore extra properties (Mandatory Qualifiers used to allow several properties)
		} catch ( InvalidArgumentException $e ) {
			throw new ConstraintParameterException(
				wfMessage( 'wbqc-violation-message-parameter-property' )
					->rawParams(
						$this->constraintParameterRenderer->formatPropertyId( 'property' ),
						$this->constraintParameterRenderer->formatDataValue( new StringValue( $constraintParameters['property'] ) )
					)
					->escaped()
			);
		}
	}

	/**
	 * @param array $constraintParameters
	 * @param string $constraintTypeName used in error messages
	 * @throws ConstraintParameterException if the parameter is invalid or missing
	 * @return PropertyId
	 */
	public function parsePropertyParameter( array $constraintParameters, $constraintTypeName ) {
		$propertyId = $this->config->get( 'WBQualityConstraintsPropertyId' );
		if ( array_key_exists( $propertyId, $constraintParameters ) ) {
			return $this->parsePropertyParameterFromStatement( $constraintParameters );
		} elseif ( array_key_exists( 'property', $constraintParameters ) ) {
			return $this->parsePropertyParameterFromTemplate( $constraintParameters );
		} else {
			throw new ConstraintParameterException(
				wfMessage( 'wbqc-violation-message-parameter-needed' )
					->params( $constraintTypeName )
					->rawParams( $this->constraintParameterRenderer->formatPropertyId( $propertyId ) )
					->escaped()
			);
		}
	}

	private function parseItemIdParameter( PropertyValueSnak $snak, $parameterId ) {
		if ( $snak->getDataValue() instanceof EntityIdValue &&
			$snak->getDataValue()->getEntityId() instanceof ItemId ) {
			return ItemIdSnakValue::fromItemId( $snak->getDataValue()->getEntityId() );
		} else {
			throw new ConstraintParameterException(
				wfMessage( 'wbqc-violation-message-parameter-item' )
					->rawParams(
						$this->constraintParameterRenderer->formatPropertyId( $parameterId ),
						$this->constraintParameterRenderer->formatDataValue( $snak->getDataValue() )
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
						$values[] = ItemIdSnakValue::fromItemId( new ItemId( strtoupper( $value ) ) );
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
	 * @param array $constraintParameters
	 * @param string $constraintTypeName used in error messages
	 * @param bool $required whether the parameter is required (error if absent) or not ([] if absent)
	 * @throws ConstraintParameterException if the parameter is invalid or missing
	 * @return ItemIdSnakValue[] array of values
	 */
	public function parseItemsParameter( array $constraintParameters, $constraintTypeName, $required ) {
		$qualifierId = $this->config->get( 'WBQualityConstraintsQualifierOfPropertyConstraintId' );
		if ( array_key_exists( $qualifierId, $constraintParameters ) ) {
			return $this->parseItemsParameterFromStatement( $constraintParameters );
		} elseif ( array_key_exists( 'item', $constraintParameters ) ) {
			return $this->parseItemsParameterFromTemplate( $constraintParameters );
		} else {
			if ( $required ) {
				throw new ConstraintParameterException(
					wfMessage( 'wbqc-violation-message-parameter-needed' )
						->params( $constraintTypeName )
						->rawParams( $this->constraintParameterRenderer->formatPropertyId( $qualifierId ) )
						->escaped()
				);
			} else {
				return [];
			}
		}
	}

}
