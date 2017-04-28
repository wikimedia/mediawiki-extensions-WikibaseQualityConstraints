/* globals mw, $, OO */

( function( mw, $, OO ) {
	'use strict';
	var entityJson, entityId;

	function buildWidget( $reports ) {
		var widget = new OO.ui.PopupButtonWidget( {
			icon: 'alert',
			iconTitle: 'This statement violates some constraints.',
			label: $reports.children().length.toString(),
			framed: false,
			popup: {
				$content: $reports,
				width: 400,
				padded: true,
				head: true,
				label: $( '<h4>' ).text( 'Constraint report' )
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
		var report;
		if ( result.status === 'violation' ) {
			report = 'Status: ' + result.status;
			if ( result[ 'message-html' ] ) {
				report += '<br>Message: ' + result[ 'message-html' ];
			}
			report += '<br>Constraint: ' + result.constraint.type;
			if ( result.constraint.detailMessage ) {
				report += '<br>' + result.constraint.detailMessage;
			}
			return new OO.ui.PanelLayout( {
				expanded: false,
				framed: true,
				padded: true,
				$content: $( '<div>' )
					.addClass( 'wbqc-report' )
					.html( report )
			} ).$element;
		} else {
			return null;
		}
	}

	function addReportsToStatement( entityData, $statement ) {
		var statementId = $statement.parents( '.wikibase-statementview' )[ 0 ].className.replace( /^.*wikibase-statement-([A-Za-z][0-9]*\$[0-9A-Fa-f-]*).*$/, '$1' ),
			propertyId = $statement.parents( '.wikibase-statementgroupview' )[ 0 ].id,
			results, $reports,
			i, $report;
		if ( !( propertyId in entityData && statementId in entityData[ propertyId ] ) ) {
			return;
		}
		results = entityData[ propertyId ][ statementId ];
		$reports = $( '<div>' ).addClass( 'wbqc-reports' );

		for( i = 0; i < results.length; i++ ) {
			$report = buildReport( results[ i ] );
			if ( $report !== null ) {
				$reports.append( $report );
			}
		}

		if ( $reports.children().length > 0 ) {
			$statement.append( buildWidget( $reports ).$element );
		}
	}

	entityJson = mw.config.get( 'wbEntity' );
	if ( entityJson !== null ) {
		entityId = JSON.parse( entityJson ).id;
		mw.loader.using( [ 'oojs-ui-core', 'oojs-ui-widgets' ] ).done( function () {
			$.getJSON( '../api.php?action=wbcheckconstraints&format=json&id=' + entityId, function( data ) {
				$( '.wikibase-statementgroupview .wikibase-statementview-mainsnak .wikibase-snakview-value' )
					.each( function () { addReportsToStatement( data.wbcheckconstraints[ entityId ], $( this ) ); } );
			} );
		} );
	}
} )( mw, $, OO );
