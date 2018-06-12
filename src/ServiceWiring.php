<?php

namespace WikibaseQuality\ConstraintReport;

use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\LoggingHelper;

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
];
