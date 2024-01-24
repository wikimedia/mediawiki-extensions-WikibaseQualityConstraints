( function ( mw, wb, $ ) {
	'use strict';
	const config = require( './config.json' ),
		makeHandler = require( './suggestions/EntitySelectorHookHandlerFactory.js' );

	function isQualifierContext( element ) {
		try {
			const $statementview = element.closest( ':wikibase-statementview' );
			const statementview = $statementview.data( 'statementview' );

			if ( !statementview ) {
				return false;
			}

			return element.closest( statementview.$qualifiers ).length > 0;

		} catch ( e ) {
			return false;
		}
	}

	function getMainSnakPropertyId( element ) {
		try {
			const snakview = element.closest( '.wikibase-statementlistview' )
				.find( '.wikibase-snakview.wb-edit' ).data( 'snakview' );
			const snakPropertyId = snakview ? snakview.propertyId() : null;
			return snakPropertyId;
		} catch ( e ) {
			return null;
		}
	}

	mw.hook( 'wikibase.entityselector.search' ).add( makeHandler( $, mw, config, isQualifierContext, getMainSnakPropertyId ) );

}( mediaWiki, wikibase, jQuery ) );
