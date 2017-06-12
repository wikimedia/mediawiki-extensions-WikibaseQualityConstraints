<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Helper;

use Config;
use Wikibase\DataModel\DeserializerFactory;
use Wikibase\DataModel\Deserializers\SnakDeserializer;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Snak\Snak;
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

}
