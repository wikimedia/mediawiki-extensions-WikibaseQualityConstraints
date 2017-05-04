/* globals mw, $, OO */

( function( mw, $, OO ) {
	'use strict';

	var entityJson,
		entityId;

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
			report = $( '<div>' );
			report.append(
				$( '<h5>' ).text( result.constraint.type )
			);
			if ( result[ 'message-html' ] ) {
				report.append(
					$( '<p>' ).html( result[ 'message-html' ] )
				);
			}
			if ( result.constraint.detailHTML ) {
				report.append( $( '<p>' ).append(
					$( '<small>' ).html( result.constraint.detailHTML )
				) );
			}

			return new OO.ui.PanelLayout( {
				expanded: false,
				framed: true,
				padded: true,
				$content: $( '<div>' )
					.addClass( 'wbqc-report' )
					.append( report )
			} ).$element;
		} else {
			return null;
		}
	}

	function addReportsToStatement( entityData, $statement ) {
		var match = $statement.parents( '.wikibase-statementview' )[ 0 ].className.match(
				/\bwikibase-statement-[^\s$]+\$[\dA-F-]+\b/i
			),
			statementId = match && match[ 1 ],
			propertyId = $statement.parents( '.wikibase-statementgroupview' )[ 0 ].id,
			results,
			$reports,
			i,
			$report;

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

	if ( 'mobileFrontend' in mw ) {
		// mobile view, skip
		return;
	}

	entityJson = mw.config.get( 'wbEntity' );

	if ( entityJson !== null ) {
		entityId = JSON.parse( entityJson ).id;
		mw.loader.using( [ 'oojs-ui-core', 'oojs-ui-widgets' ] ).done( function () {
			var api = new mw.Api();
			api.get( {
				action: 'wbcheckconstraints',
				format: 'json',
				id: entityId
			} ).done( function( data ) {
				$( '.wikibase-statementgroupview .wikibase-statementview-mainsnak .wikibase-snakview-value' )
					.each( function () { addReportsToStatement( data.wbcheckconstraints[ entityId ], $( this ) ); } );
			} );
		} );
	}
} )( mw, $, OO );
