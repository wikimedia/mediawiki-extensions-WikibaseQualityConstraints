<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Helper;

use Config;
use DataValues\DataValue;
use DataValues\MonolingualTextValue;
use DataValues\StringValue;
use Language;
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
	 * Check if any errors are recorded in the constraint parameters.
	 * @param array $parameters
	 * @throws ConstraintParameterException
	 */
	public function checkError( array $parameters ) {
		if ( array_key_exists( '@error', $parameters ) ) {
			$error = $parameters['@error'];
			if ( array_key_exists( 'toolong', $error ) && $error['toolong'] ) {
				$msg = 'wbqc-violation-message-parameters-error-toolong';
			} else {
				$msg = 'wbqc-violation-message-parameters-error-unknown';
			}
			throw new ConstraintParameterException( wfMessage( $msg )->escaped() );
		}
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

	/**
	 * @param array $constraintParameters see {@link \WikibaseQuality\Constraint::getConstraintParameters()}
	 * @param string $constraintTypeItemId used in error messages
	 * @throws ConstraintParameterException if the parameter is invalid or missing
	 * @return string[] class entity ID serializations
	 */
	public function parseClassParameter( array $constraintParameters, $constraintTypeItemId ) {
		$this->checkError( $constraintParameters );
		$classId = $this->config->get( 'WBQualityConstraintsClassId' );
		if ( !array_key_exists( $classId, $constraintParameters ) ) {
			throw new ConstraintParameterException(
				wfMessage( 'wbqc-violation-message-parameter-needed' )
					->rawParams( $this->constraintParameterRenderer->formatItemId( $constraintTypeItemId, Role::CONSTRAINT_TYPE_ITEM ) )
					->rawParams( $this->constraintParameterRenderer->formatPropertyId( $classId, Role::CONSTRAINT_PARAMETER_PROPERTY ) )
					->escaped()
			);
		}

		$classes = [];
		foreach ( $constraintParameters[$classId] as $class ) {
			$classes[] = $this->parseEntityIdParameter( $class, $classId )->getSerialization();
		}
		return $classes;
	}

	/**
	 * @param array $constraintParameters see {@link \WikibaseQuality\Constraint::getConstraintParameters()}
	 * @param string $constraintTypeItemId used in error messages
	 * @throws ConstraintParameterException if the parameter is invalid or missing
	 * @return string 'instance' or 'subclass'
	 */
	public function parseRelationParameter( array $constraintParameters, $constraintTypeItemId ) {
		$this->checkError( $constraintParameters );
		$relationId = $this->config->get( 'WBQualityConstraintsRelationId' );
		if ( !array_key_exists( $relationId, $constraintParameters ) ) {
			throw new ConstraintParameterException(
				wfMessage( 'wbqc-violation-message-parameter-needed' )
					->rawParams( $this->constraintParameterRenderer->formatItemId( $constraintTypeItemId, Role::CONSTRAINT_TYPE_ITEM ) )
					->rawParams( $this->constraintParameterRenderer->formatPropertyId( $relationId, Role::CONSTRAINT_PARAMETER_PROPERTY ) )
					->escaped()
			);
		}

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

	/**
	 * @param array $constraintParameters see {@link \WikibaseQuality\Constraint::getConstraintParameters()}
	 * @param string $constraintTypeItemId used in error messages
	 * @throws ConstraintParameterException if the parameter is invalid or missing
	 * @return PropertyId
	 */
	public function parsePropertyParameter( array $constraintParameters, $constraintTypeItemId ) {
		$this->checkError( $constraintParameters );
		$propertyId = $this->config->get( 'WBQualityConstraintsPropertyId' );
		if ( !array_key_exists( $propertyId, $constraintParameters ) ) {
			throw new ConstraintParameterException(
				wfMessage( 'wbqc-violation-message-parameter-needed' )
					->rawParams( $this->constraintParameterRenderer->formatItemId( $constraintTypeItemId, Role::CONSTRAINT_TYPE_ITEM ) )
					->rawParams( $this->constraintParameterRenderer->formatPropertyId( $propertyId, Role::CONSTRAINT_PARAMETER_PROPERTY ) )
					->escaped()
			);
		}

		$this->requireSingleParameter( $constraintParameters, $propertyId );
		return $this->parsePropertyIdParameter( $constraintParameters[$propertyId][0], $propertyId );
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

	/**
	 * @param array $constraintParameters see {@link \WikibaseQuality\Constraint::getConstraintParameters()}
	 * @param string $constraintTypeItemId used in error messages
	 * @param bool $required whether the parameter is required (error if absent) or not ([] if absent)
	 * @throws ConstraintParameterException if the parameter is invalid or missing
	 * @return ItemIdSnakValue[] array of values
	 */
	public function parseItemsParameter( array $constraintParameters, $constraintTypeItemId, $required ) {
		$this->checkError( $constraintParameters );
		$qualifierId = $this->config->get( 'WBQualityConstraintsQualifierOfPropertyConstraintId' );
		if ( !array_key_exists( $qualifierId, $constraintParameters ) ) {
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

	/**
	 * @param array $constraintParameters see {@link \WikibaseQuality\Constraint::getConstraintParameters()}
	 * @param string $constraintTypeItemId used in error messages
	 * @throws ConstraintParameterException if the parameter is invalid or missing
	 * @return PropertyId[]
	 */
	public function parsePropertiesParameter( array $constraintParameters, $constraintTypeItemId ) {
		$this->checkError( $constraintParameters );
		$propertyId = $this->config->get( 'WBQualityConstraintsPropertyId' );
		if ( !array_key_exists( $propertyId, $constraintParameters ) ) {
			throw new ConstraintParameterException(
				wfMessage( 'wbqc-violation-message-parameter-needed' )
					->rawParams( $this->constraintParameterRenderer->formatItemId( $constraintTypeItemId, Role::CONSTRAINT_TYPE_ITEM ) )
					->rawParams( $this->constraintParameterRenderer->formatPropertyId( $propertyId, Role::CONSTRAINT_PARAMETER_PROPERTY ) )
					->escaped()
			);
		}

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

	/**
	 * Checks whether there is exactly one non-null quantity with the given unit.
	 * @param DataValue|null $min
	 * @param DataValue|null $max
	 * @param string $unit
	 * @return bool
	 */
	private function exactlyOneQuantityWithUnit( $min, $max, $unit ) {
		if ( $min === null || $max === null ) {
			return false;
		}
		if ( $min->getType() !== 'quantity' || $max->getType() !== 'quantity' ) {
			return false;
		}
		return ( $min->getUnit() === $unit ) !== ( $max->getUnit() === $unit );
	}

	/**
	 * @param array $constraintParameters see {@link \WikibaseQuality\Constraint::getConstraintParameters()}
	 * @param string $constraintTypeItemId used in error messages
	 * @param string $type 'quantity' or 'time' (can be data type or data value type)
	 * @throws ConstraintParameterException if the parameter is invalid or missing
	 * @return DataValue[] a pair of two quantity-type data values, either of which may be null to signify an open range
	 */
	public function parseRangeParameter( array $constraintParameters, $constraintTypeItemId, $type ) {
		$this->checkError( $constraintParameters );
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
		if ( !array_key_exists( $minimumId, $constraintParameters ) ||
			!array_key_exists( $maximumId, $constraintParameters )
		) {
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

		$this->requireSingleParameter( $constraintParameters, $minimumId );
		$this->requireSingleParameter( $constraintParameters, $maximumId );
		$parseFunction = $configKey === 'Date' ? 'parseValueOrNoValueOrNowParameter' : 'parseValueOrNoValueParameter';
		$min = $this->$parseFunction( $constraintParameters[$minimumId][0], $minimumId );
		$max = $this->$parseFunction( $constraintParameters[$maximumId][0], $maximumId );

		$yearUnit = $this->config->get( 'WBQualityConstraintsYearUnit' );
		if ( $this->exactlyOneQuantityWithUnit( $min, $max, $yearUnit ) ) {
			throw new ConstraintParameterException(
				wfMessage( 'wbqc-violation-message-range-parameters-one-year' )
					->escaped()
			);
		}

		return [ $min, $max ];
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

	/**
	 * @param array $constraintParameters see {@link \WikibaseQuality\Constraint::getConstraintParameters()}
	 * @param string $constraintTypeItemId used in error messages
	 * @throws ConstraintParameterException if the parameter is invalid or missing
	 * @return string
	 */
	public function parseNamespaceParameter( array $constraintParameters, $constraintTypeItemId ) {
		$this->checkError( $constraintParameters );
		$namespaceId = $this->config->get( 'WBQualityConstraintsNamespaceId' );
		if ( !array_key_exists( $namespaceId, $constraintParameters ) ) {
			return '';
		}

		$this->requireSingleParameter( $constraintParameters, $namespaceId );
		return $this->parseStringParameter( $constraintParameters[$namespaceId][0], $namespaceId );
	}

	/**
	 * @param array $constraintParameters see {@link \WikibaseQuality\Constraint::getConstraintParameters()}
	 * @param string $constraintTypeItemId used in error messages
	 * @throws ConstraintParameterException if the parameter is invalid or missing
	 * @return string
	 */
	public function parseFormatParameter( array $constraintParameters, $constraintTypeItemId ) {
		$this->checkError( $constraintParameters );
		$formatId = $this->config->get( 'WBQualityConstraintsFormatAsARegularExpressionId' );
		if ( !array_key_exists( $formatId, $constraintParameters ) ) {
			throw new ConstraintParameterException(
				wfMessage( 'wbqc-violation-message-parameter-needed' )
					->rawParams( $this->constraintParameterRenderer->formatItemId( $constraintTypeItemId, Role::CONSTRAINT_TYPE_ITEM ) )
					->rawParams( $this->constraintParameterRenderer->formatPropertyId( $formatId, Role::CONSTRAINT_PARAMETER_PROPERTY ) )
					->escaped()
			);
		}

		$this->requireSingleParameter( $constraintParameters, $formatId );
		return $this->parseStringParameter( $constraintParameters[$formatId][0], $formatId );
	}

	/**
	 * @param array $constraintParameters see {@link \WikibaseQuality\Constraint::getConstraintParameters()}
	 * @throws ConstraintParameterException if the parameter is invalid
	 * @return EntityId[]
	 */
	public function parseExceptionParameter( array $constraintParameters ) {
		$this->checkError( $constraintParameters );
		$exceptionId = $this->config->get( 'WBQualityConstraintsExceptionToConstraintId' );
		if ( !array_key_exists( $exceptionId, $constraintParameters ) ) {
			return [];
		}

		return array_map(
			function( $snakSerialization ) use ( $exceptionId ) {
				return $this->parseEntityIdParameter( $snakSerialization, $exceptionId );
			},
			$constraintParameters[$exceptionId]
		);
	}

	/**
	 * @param array $constraintParameters see {@link \WikibaseQuality\Constraint::getConstraintParameters()}
	 * @throws ConstraintParameterException if the parameter is invalid
	 * @return string|null 'mandatory' or null
	 */
	public function parseConstraintStatusParameter( array $constraintParameters ) {
		$this->checkError( $constraintParameters );
		$constraintStatusId = $this->config->get( 'WBQualityConstraintsConstraintStatusId' );
		if ( !array_key_exists( $constraintStatusId, $constraintParameters ) ) {
			return null;
		}

		$mandatoryId = $this->config->get( 'WBQualityConstraintsMandatoryConstraintId' );
		$this->requireSingleParameter( $constraintParameters, $constraintStatusId );
		$snak = $this->snakDeserializer->deserialize( $constraintParameters[$constraintStatusId][0] );
		$this->requireValueParameter( $snak, $constraintStatusId );
		$statusId = $snak->getDataValue()->getEntityId()->getSerialization();

		if ( $statusId === $mandatoryId ) {
			return 'mandatory';
		} else {
			throw new ConstraintParameterException(
				wfMessage( 'wbqc-violation-message-parameter-oneof' )
					->rawParams( $this->constraintParameterRenderer->formatPropertyId( $constraintStatusId, Role::CONSTRAINT_PARAMETER_PROPERTY ) )
					->numParams( 1 )
					->rawParams( $this->constraintParameterRenderer->formatItemIdList( [ $mandatoryId ], Role::CONSTRAINT_PARAMETER_VALUE ) )
					->escaped()
			);
		}
	}

	/**
	 * Require that $dataValue is a {@link MonolingualTextValue}.
	 * @param DataValue $dataValue
	 * @param string $parameterId
	 * @return void
	 * @throws ConstraintParameterException
	 */
	private function requireMonolingualTextParameter( DataValue $dataValue, $parameterId ) {
		if ( !( $dataValue instanceof MonolingualTextValue ) ) {
			throw new ConstraintParameterException(
				wfMessage( 'wbqc-violation-message-parameter-monolingualtext' )
					->rawParams(
						$this->constraintParameterRenderer->formatPropertyId( $parameterId, Role::CONSTRAINT_PARAMETER_PROPERTY ),
						$this->constraintParameterRenderer->formatDataValue( $dataValue, Role::CONSTRAINT_PARAMETER_VALUE )
					)
					->escaped()
			);
		}
	}

	/**
	 * Parse a series of monolingual text snaks (serialized) into a map from language code to string.
	 *
	 * @param array $snakSerializations
	 * @param string $parameterId
	 * @throws ConstraintParameterException if invalid snaks are found or a language has multiple texts
	 * @return string[]
	 */
	private function parseMultilingualTextParameter( array $snakSerializations, $parameterId ) {
		$result = [];

		foreach ( $snakSerializations as $snakSerialization ) {
			$snak = $this->snakDeserializer->deserialize( $snakSerialization );
			$this->requireValueParameter( $snak, $parameterId );

			$value = $snak->getDataValue();
			$this->requireMonolingualTextParameter( $value, $parameterId );

			$code = $value->getLanguageCode();
			if ( array_key_exists( $code, $result ) ) {
				throw new ConstraintParameterException(
					wfMessage( 'wbqc-violation-message-parameter-single-per-language' )
						->rawParams(
							$this->constraintParameterRenderer->formatPropertyId( $parameterId, Role::CONSTRAINT_PARAMETER_PROPERTY )
						)
						->params(
							Language::fetchLanguageName( $code ),
							$code
						)
						->escaped()
				);
			}

			$result[$code] = $value->getText();
		}

		return $result;
	}

	/**
	 * @param array $constraintParameters see {@link \WikibaseQuality\Constraint::getConstraintParameters()}
	 * @param Language $language
	 * @throws ConstraintParameterException if the parameter is invalid
	 * @return string|null
	 */
	public function parseSyntaxClarificationParameter( array $constraintParameters, Language $language ) {
		$syntaxClarificationId = $this->config->get( 'WBQualityConstraintsSyntaxClarificationId' );

		if ( !array_key_exists( $syntaxClarificationId, $constraintParameters ) ) {
			return null;
		}

		$languageCodes = $language->getFallbackLanguages();
		array_unshift( $languageCodes, $language->getCode() );

		$syntaxClarifications = $this->parseMultilingualTextParameter(
			$constraintParameters[$syntaxClarificationId],
			$syntaxClarificationId
		);

		foreach ( $languageCodes as $languageCode ) {
			if ( array_key_exists( $languageCode, $syntaxClarifications ) ) {
				return $syntaxClarifications[$languageCode];
			}
		}

		return null;
	}

}
