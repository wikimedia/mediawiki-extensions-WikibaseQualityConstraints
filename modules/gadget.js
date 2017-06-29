( function( mw, $, OO ) {
	'use strict';

	var entityId;

	function buildWidget( reports ) {
		var widget = new OO.ui.PopupButtonWidget( {
			icon: 'alert',
			iconTitle: mw.message( 'wbqc-potentialissues-long' ).text(),
			framed: false,
			classes: [ 'wbqc-reports-button' ],
			popup: {
				$content: new OO.ui.StackLayout( {
					items: reports,
					continuous: true,
					expanded: false, // expanded: true does not work within a popup
					classes: [ 'wbqc-reports' ]
				} ).$element,
				width: 400,
				padded: true,
				head: true,
				label: $( '<strong>' ).text( mw.message( 'wbqc-potentialissues-short' ).text() )
			}
		} );
		widget.popup.$element.css( 'z-index', 2 ); // prevent collision with rank selector and property grey field

		return widget;
	}

	function buildReport( result ) {
		var $report, $heading, $helpButton;

		if ( result.status === 'violation' ) {
			$report = $( '<div>' ).addClass( 'wbqc-report' );
			$heading = $( '<h4>' ).append(
				$( '<a>' )
					.text( result.constraint.typeLabel )
					.attr( 'href', result.constraint.link )
					.attr( 'target', '_blank' )
			);
			$helpButton = new OO.ui.ButtonWidget( {
				icon: 'help',
				framed: false,
				classes: [ 'wbqc-constraint-type-help' ],
				href: 'https://www.wikidata.org/wiki/Help:Property_constraints_portal/' + result.constraint.type,
				target: '_blank'
			} ).$element;
			$heading.append( $helpButton );
			$report.append( $heading );
			if ( result[ 'message-html' ] ) {
				$report.append(
					$( '<p>' ).html( result[ 'message-html' ] )
				);
			}

			return new OO.ui.PanelLayout( {
				expanded: false,
				$content: $report
			} );
		} else {
			return null;
		}
	}

	function addReportsToStatement( entityData, $statement ) {
		var match = $statement.parents( '.wikibase-statementview' )[ 0 ].className.match(
				/\bwikibase-statement-([^\s$]+\$[\dA-F-]+)\b/i
			),
			statementId = match && match[ 1 ],
			propertyId = $statement.parents( '.wikibase-statementgroupview' )[ 0 ].id,
			results,
			reports,
			i,
			report,
			$target;

		if ( !( propertyId in entityData && statementId in entityData[ propertyId ] ) ) {
			return;
		}

		results = entityData[ propertyId ][ statementId ];
		reports = [];

		for( i = 0; i < results.length; i++ ) {
			report = buildReport( results[ i ] );
			if ( report !== null ) {
				reports.push( report );
			}
		}

		if ( reports.length > 0 ) {
			$target = $statement.find( '.valueview-instaticmode' );
			if ( $target.length === 0 ) {
				$target = $statement;
			}
			$target.append( buildWidget( reports ).$element );
		}
	}

	entityId = mw.config.get( 'wbEntityId' );

	if ( entityId === null || mw.config.get( 'wgMFMode' ) ) {
		// no entity or mobile frontend, skip
		return;
	}

	mw.loader.using( [
		'mediawiki.api',
		'oojs-ui-core',
		'oojs-ui-widgets',
		'oojs-ui.styles.icons-alerts',
		'oojs-ui.styles.icons-interactions',
		'wikibase.quality.constraints.ui'
	] ).done( function () {
		var api = new mw.Api(),
			lang = mw.config.get( 'wgUserLanguage' );
		api.get( {
			action: 'wbcheckconstraints',
			format: 'json',
			uselang: lang,
			id: entityId
		} ).then( function( data ) {
			$( '.wikibase-statementgroupview .wikibase-statementview-mainsnak .wikibase-snakview-value' )
				.each( function () { addReportsToStatement( data.wbcheckconstraints[ entityId ], $( this ) ); } );
		} );

		mw.hook( 'wikibase.statement.saved' ).add( function( entityId, statementId ) {
			api.get( {
				action: 'wbcheckconstraints',
				format: 'json',
				uselang: lang,
				claimid: statementId
			} ).then( function( data ) {
				var statementClass = 'wikibase-statement-' + statementId.replace( /\$/, '\\$$' );
				$( '.wikibase-statementgroupview .' + statementClass + ' .wikibase-statementview-mainsnak .wikibase-snakview-value' )
					.each( function () { addReportsToStatement( data.wbcheckconstraints[ entityId ], $( this ) ); } );
			} );
		} );
	} );
} )( mediaWiki, jQuery, OO );
