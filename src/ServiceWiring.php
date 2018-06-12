<?php

namespace WikibaseQuality\ConstraintReport;

use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use Wikibase\Repo\WikibaseRepo;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\ContextCursorDeserializer;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\ContextCursorSerializer;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\LoggingHelper;
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
];
