<?php

namespace WikibaseQuality\ConstraintReport;

use MediaWiki\MediaWikiServices;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\Store;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ExceptionIgnoringEntityLookup;

return [
	WikibaseServices::ENTITY_LOOKUP => function( MediaWikiServices $services ) {
		return new ExceptionIgnoringEntityLookup(
			WikibaseRepo::getDefaultInstance()->getEntityLookup()
		);
	},

	WikibaseServices::ENTITY_LOOKUP_WIHTOUT_CACHE => function( MediaWikiServices $services ) {
		return new ExceptionIgnoringEntityLookup(
			WikibaseRepo::getDefaultInstance()->getEntityLookup( Store::LOOKUP_CACHING_RETRIEVE_ONLY )
		);
	},

	WikibaseServices::PROPERTY_DATA_TYPE_LOOKUP => function( MediaWikiServices $services ) {
		return WikibaseRepo::getDefaultInstance()->getPropertyDataTypeLookup();
	},
];
