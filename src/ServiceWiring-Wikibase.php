<?php

namespace WikibaseQuality\ConstraintReport;

use MediaWiki\MediaWikiServices;
use Wikibase\Repo\WikibaseRepo;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ExceptionIgnoringEntityLookup;

return [
	WikibaseServices::ENTITY_LOOKUP => function( MediaWikiServices $services ) {
		return new ExceptionIgnoringEntityLookup(
			WikibaseRepo::getDefaultInstance()->getEntityLookup()
		);
	},

	WikibaseServices::PROPERTY_DATA_TYPE_LOOKUP => function( MediaWikiServices $services ) {
		return WikibaseRepo::getDefaultInstance()->getPropertyDataTypeLookup();
	},
];
