<?php

namespace WikibaseQuality\ConstraintReport;

use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use Wikibase\Repo\WikibaseRepo;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\ContextCursorDeserializer;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\ContextCursorSerializer;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\LoggingHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageDeserializer;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageSerializer;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResultDeserializer;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResultSerializer;

return [
	'WBQC_LoggingHelper' => function( MediaWikiServices $services ) {
		return new LoggingHelper(
			$services->getStatsdDataFactory(),
			LoggerFactory::getInstance( 'WikibaseQualityConstraints' ),
			$services->getMainConfig()
		);
	},

	'WBQC_ConstraintRepository' => function( MediaWikiServices $services ) {
		return new ConstraintRepository();
	},

	'WBQC_ConstraintLookup' => function( MediaWikiServices $services ) {
		$constraintRepository = ConstraintsServices::getConstraintRepository( $services );
		return new CachingConstraintLookup( $constraintRepository );
	},

	'WBQC_CheckResultSerializer' => function( MediaWikiServices $services ) {
		return new CheckResultSerializer(
			new ConstraintSerializer(
				false // constraint parameters are not exposed
			),
			new ContextCursorSerializer(),
			new ViolationMessageSerializer(),
			false // unnecessary to serialize individual result dependencies
		);
	},

	'WBQC_CheckResultDeserializer' => function( MediaWikiServices $services ) {
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
];
