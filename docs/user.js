( function( mw, $, OO ) {
	'use strict';

	var entityId;

	function buildWidget( reports ) {
		var widget = new OO.ui.PopupButtonWidget( {
			icon: 'alert',
			iconTitle: mw.message( 'wbqc-potentialissues-long' ).text(),
			framed: false,
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

		widget.$element.css( {
			'margin-inline-start': '0.5em',
			'margin-left': '0.5em' // margin-inline-start is not supported by all browsers, margin-left is equivalent in ltr languages
		} );
		widget.popup.$element.css( 'z-index', 2 ); // prevent collision with rank selector and property grey field; TODO better way to do this?

		return widget;
	}

	function buildReport( result ) {
		var $report, $heading, $helpButton;

		if ( result.status === 'violation' ) {
			$report = $( '<div>' ).addClass( 'wbqc-report' );
			$report.css( 'border-top', '1px solid #eaecf0' ); // TODO move to CSS on .wbqc-report class
			$heading = $( '<h4>' ).text( result.constraint.type );
			$helpButton = new OO.ui.ButtonWidget( {
				icon: 'help',
				framed: false,
				classes: [ 'wbqc-constraint-type-help' ],
				href: 'https://www.wikidata.org/wiki/Help:Property_constraints_portal/' + result.constraint.type,
				target: '_blank'
			} ).$element;
			$helpButton.css( 'transform', 'scale(0.75)' ); // TODO move to CSS on .wbqc-constraint-type-help class
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
			report;

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
			$statement.append( buildWidget( reports ).$element );
		}
	}

	if ( mw.config.get( 'wgMFMode' ) ) {
		// mobile frontend, skip
		return;
	}

	entityId = mw.config.get( 'wbEntityId' );

	if ( entityId !== null ) {
		mw.loader.using( [
			'mediawiki.api',
			'oojs-ui-core',
			'oojs-ui-widgets',
			'oojs-ui.styles.icons-alerts',
			'oojs-ui.styles.icons-interactions',
			'wikibase.quality.constraints.ui'
		] ).done( function () {
			var api = new mw.Api();
			api.get( {
				action: 'wbcheckconstraints',
				format: 'json',
				uselang: mw.config.get( 'wgUserLanguage' ),
				id: entityId
			} ).done( function( data ) {
				$( '.wikibase-statementgroupview .wikibase-statementview-mainsnak .wikibase-snakview-value' )
					.each( function () { addReportsToStatement( data.wbcheckconstraints[ entityId ], $( this ) ); } );
			} );
		} );
	}
} )( mediaWiki, jQuery, OO );
