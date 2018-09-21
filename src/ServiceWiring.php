<?php

namespace WikibaseQuality\ConstraintReport;

use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use Wikibase\Repo\WikibaseRepo;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\ContextCursorDeserializer;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\ContextCursorSerializer;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConnectionCheckerHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\DummySparqlHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\LoggingHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\RangeCheckerHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\SparqlHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\TypeCheckerHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageDeserializer;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageSerializer;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResultDeserializer;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResultSerializer;

return [
	ConstraintsServices::LOGGING_HELPER => function( MediaWikiServices $services ) {
		return new LoggingHelper(
			$services->getStatsdDataFactory(),
			LoggerFactory::getInstance( 'WikibaseQualityConstraints' ),
			$services->getMainConfig()
		);
	},

	ConstraintsServices::CONSTRAINT_REPOSITORY => function( MediaWikiServices $services ) {
		return new ConstraintRepository();
	},

	ConstraintsServices::CONSTRAINT_LOOKUP => function( MediaWikiServices $services ) {
		$constraintRepository = ConstraintsServices::getConstraintRepository( $services );
		return new CachingConstraintLookup( $constraintRepository );
	},

	ConstraintsServices::CHECK_RESULT_SERIALIZER => function( MediaWikiServices $services ) {
		return new CheckResultSerializer(
			new ConstraintSerializer(
				false // constraint parameters are not exposed
			),
			new ContextCursorSerializer(),
			new ViolationMessageSerializer(),
			false // unnecessary to serialize individual result dependencies
		);
	},

	ConstraintsServices::CHECK_RESULT_DESERIALIZER => function( MediaWikiServices $services ) {
		// TODO in the future, get EntityIdParser and DataValueFactory from $services?
		$repo = WikibaseRepo::getDefaultInstance();
		$entityIdParser = $repo->getEntityIdParser();
		$dataValueFactory = $repo->getDataValueFactory();

		return new CheckResultDeserializer(
			new ConstraintDeserializer(),
			new ContextCursorDeserializer(),
			new ViolationMessageDeserializer(
				$entityIdParser,
				$dataValueFactory
			),
			$entityIdParser
		);
	},

	ConstraintsServices::VIOLATION_MESSAGE_SERIALIZER => function( MediaWikiServices $services ) {
		return new ViolationMessageSerializer();
	},

	ConstraintsServices::VIOLATION_MESSAGE_DESERIALIZER => function( MediaWikiServices $services ) {
		// TODO in the future, get EntityIdParser and DataValueFactory from $services?
		$repo = WikibaseRepo::getDefaultInstance();
		$entityIdParser = $repo->getEntityIdParser();
		$dataValueFactory = $repo->getDataValueFactory();

		return new ViolationMessageDeserializer(
			$entityIdParser,
			$dataValueFactory
		);
	},

	ConstraintsServices::CONSTRAINT_PARAMETER_PARSER => function( MediaWikiServices $services ) {
		// TODO in the future, get DeserializerFactory and concept base URIs from $services?
		$repo = WikibaseRepo::getDefaultInstance();
		$deserializerFactory = $repo->getBaseDataModelDeserializerFactory();
		$conceptBaseUris = $repo->getConceptBaseUris();

		return new ConstraintParameterParser(
			$services->getMainConfig(),
			$deserializerFactory,
			$conceptBaseUris
		);
	},

	ConstraintsServices::CONNECTION_CHECKER_HELPER => function( MediaWikiServices $services ) {
		return new ConnectionCheckerHelper();
	},

	ConstraintsServices::RANGE_CHECKER_HELPER => function( MediaWikiServices $services ) {
		// TODO in the future, get UnitConverter from $services?
		$repo = WikibaseRepo::getDefaultInstance();
		$unitConverter = $repo->getUnitConverter();

		return new RangeCheckerHelper(
			$services->getMainConfig(),
			$unitConverter
		);
	},

	ConstraintsServices::SPARQL_HELPER => function( MediaWikiServices $services ) {
		$endpoint = $services->getMainConfig()->get( 'WBQualityConstraintsSparqlEndpoint' );
		if ( $endpoint === '' ) {
			return new DummySparqlHelper();
		}

		// TODO in the future, get RDFVocabulary, EntityIdParser and PropertyDataTypeLookup from $services?
		$repo = WikibaseRepo::getDefaultInstance();
		$rdfVocabulary = $repo->getRdfVocabulary();
		$entityIdParser = $repo->getEntityIdParser();
		$propertyDataTypeLookup = $repo->getPropertyDataTypeLookup();

		return new SparqlHelper(
			$services->getMainConfig(),
			$rdfVocabulary,
			$entityIdParser,
			$propertyDataTypeLookup,
			$services->getMainWANObjectCache(),
			ConstraintsServices::getViolationMessageSerializer( $services ),
			ConstraintsServices::getViolationMessageDeserializer( $services ),
			$services->getStatsdDataFactory()
		);
	},

	ConstraintsServices::TYPE_CHECKER_HELPER => function( MediaWikiServices $services ) {
		return new TypeCheckerHelper(
			WikibaseServices::getEntityLookup( $services ),
			$services->getMainConfig(),
			ConstraintsServices::getSparqlHelper( $services ),
			$services->getStatsdDataFactory()
		);
	},
];
