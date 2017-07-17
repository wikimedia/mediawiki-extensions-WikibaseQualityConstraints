( function( mw, wb, $, OO ) {
	'use strict';

	var entityId;

	function buildWidget( reports, messageKey, flags /* = '' */ ) {
		var widget = new OO.ui.PopupButtonWidget( {
			icon: 'alert',
			iconTitle: mw.message( 'wbqc-' + messageKey + '-long' ).text(),
			flags: flags || '',
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
				label: $( '<strong>' ).text( mw.message( 'wbqc-' + messageKey + '-short' ).text() )
			}
		} );
		widget.popup.$element.css( 'z-index', 2 ); // prevent collision with rank selector and property grey field

		return widget;
	}

	function buildReport( result ) {
		if ( result.status === 'violation' || result.status === 'bad-parameters' ) {
			return new wb.quality.constraints.ui.ConstraintReportPanel( {
				status: result.status,
				constraint: result.constraint,
				message: result[ 'message-html' ]
			} );
		} else {
			return null;
		}
	}

	function buildParameterReport( problem ) {
		var $report, $heading, $body;

		$report = $( '<div>' )
			.addClass( 'wbqc-parameter-report' );
		$heading = $( '<h4>' ); // empty for now
		$report.append( $heading );
		$body = $( '<p>' )
			.html( problem[ 'message-html' ] );
		$report.append( $body );

		return new OO.ui.PanelLayout( {
			expanded: false,
			$content: $report
		} );
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
			$target.append( buildWidget( reports, 'potentialissues' ).$element );
		}
	}

	function addParameterReports( parameterReports ) {
		var constraintId,
			status,
			problems,
			reports,
			i,
			report,
			$statement,
			$target;

		for ( constraintId in parameterReports ) {
			status = parameterReports[ constraintId ].status;
			if ( status === 'okay' ) {
				continue;
			}

			problems = parameterReports[ constraintId ].problems;
			reports = [];
			for ( i = 0; i < problems.length; i++ ) {
				report = buildParameterReport( problems[ i ] );
				if ( report !== null ) {
					reports.push( report );
				}
			}

			$statement = $( '.wikibase-statement-' + constraintId.replace( /\$/g, '\\$' ) +
								' .wikibase-statementview-mainsnak .wikibase-snakview-value' );
			$target = $statement.find( '.valueview-instaticmode' );
			if ( $target.length === 0 ) {
				$target = $statement;
			}
			$target.append( buildWidget( reports, 'badparameters', 'warning' ).$element );
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

		if ( mw.config.get( 'wgPageContentModel' ) === 'wikibase-property' ) {
			api.get( {
				action: 'wbcheckconstraintparameters',
				format: 'json',
				uselang: lang,
				propertyid: entityId
			} ).then( function( data ) {
				addParameterReports( data.wbcheckconstraintparameters[ entityId ] );
			} );
		}

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
} )( mediaWiki, wikibase, jQuery, OO );
