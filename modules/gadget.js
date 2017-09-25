( function( mw, wb, $, OO ) {
	'use strict';

	var entityId;

	function buildPopup( $content, icon, iconTitleMessageKey, flags /* = '' */ ) {
		var widget = new OO.ui.PopupButtonWidget( {
			icon: icon,
			iconTitle: mw.message( iconTitleMessageKey ).text(),
			flags: flags || '',
			framed: false,
			classes: [ 'wbqc-reports-button' ],
			popup: {
				$content: $content,
				width: 400,
				padded: true,
				head: true,
				label: $content.find( '.wbqc-reports:first-child > .oo-ui-labelElement-label *' ).detach()
			}
		} );
		widget.popup.$element.css( 'z-index', 2 ); // prevent collision with rank selector and property grey field

		return widget;
	}

	function buildReport( result ) {
		return new wb.quality.constraints.ui.ConstraintReportPanel( {
			status: result.status,
			constraint: result.constraint,
			message: result[ 'message-html' ]
		} );
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

	function buildReportList( reports ) {
		var list = wikibase.quality.constraints.ui.ConstraintReportList.static.fromPanels(
			reports,
			{
				statuses: [
					{
						status: 'violation',
						label: mw.message( 'wbqc-issues-short' ).text()
					},
					{
						status: 'warning',
						label: mw.message( 'wbqc-potentialissues-short' ).text()
					},
					{
						status: 'bad-parameters',
						label: mw.message( 'wbqc-parameterissues-short' ).text(),
						subheading: mw.message( 'wbqc-parameterissues-long' ).text(),
						collapsed: true
					}
				],
				expanded: false // expanded: true does not work within a popup
			}
		);
		if (
			// list isn't empty...
			list.items.length > 0 &&
			// ...and doesn't only contain collapsed items either
			list.items[ 0 ].status !== 'bad-parameters'
		) {
			return list;
		} else {
			return null;
		}
	}

	function extractResultsForStatement( entityData, propertyId, statementId ) {
		var statements, index, statement, results = {};
		if ( 'claims' in entityData ) {
			// API v2 format
			statements = entityData.claims[ propertyId ];
			if ( statements === undefined ) {
				return null;
			}
			for ( index in statements ) {
				if ( statements[ index ].id === statementId ) {
					statement = statements[ index ];
					break;
				}
			}
			if ( statement === undefined ) {
				return null;
			}

			results.mainsnak = statement.mainsnak.results;

			// TODO also extract qualifier and reference snaks

			return results;
		} else {
			// API v1 format
			if ( propertyId in entityData && statementId in entityData[ propertyId ] ) {
				return {
					mainsnak: entityData[ propertyId ][ statementId ]
				};
			} else {
				return null;
			}
		}
	}

	function addResultsToSnak( results, $snak ) {
		var reports = results.map( buildReport ),
			list = buildReportList( reports ),
			haveMandatoryViolations,
			$target;

		if ( list !== null ) {
			haveMandatoryViolations = list.items[ 0 ].status === 'violation';

			$target = $snak.find( '.wikibase-snakview-value .valueview-instaticmode' );
			if ( $target.length === 0 ) {
				$target = $snak.find( '.wikibase-snakview-value' );
			}
			$target.append( buildPopup(
				list.$element,
				haveMandatoryViolations ? 'alert' : 'info',
				haveMandatoryViolations ? 'wbqc-issues-long' : 'wbqc-potentialissues-long'
			).$element );
		}
	}

	function addReportsToStatement( entityData, $statement ) {
		var match = $statement[ 0 ].className.match(
				/\bwikibase-statement-([^\s$]+\$[\dA-F-]+)\b/i
			),
			statementId = match && match[ 1 ],
			propertyId = $statement.parents( '.wikibase-statementgroupview' )[ 0 ].id,
			results = extractResultsForStatement( entityData, propertyId, statementId );

		if ( results === null ) {
			return;
		}

		addResultsToSnak(
			results.mainsnak,
			$statement.find( '.wikibase-statementview-mainsnak' )
		);

		// TODO also add qualifier and reference results to snak
	}

	function addParameterReports( parameterReports ) {
		var constraintId,
			status,
			problems,
			reports,
			list,
			$statement,
			$target;

		for ( constraintId in parameterReports ) {
			status = parameterReports[ constraintId ].status;
			if ( status === 'okay' ) {
				continue;
			}

			problems = parameterReports[ constraintId ].problems;
			reports = problems.map( buildParameterReport );

			list = new wikibase.quality.constraints.ui.ConstraintReportList( {
				items: [
					new wikibase.quality.constraints.ui.ConstraintReportGroup( {
						items: reports,
						label: mw.message( 'wbqc-badparameters-short' ).text()
					} )
				],
				expanded: false // expanded: true does not work within a popup
			} );

			$statement = $( '.wikibase-statement-' + constraintId.replace( /\$/g, '\\$' ) +
								' .wikibase-statementview-mainsnak .wikibase-snakview-value' );
			$target = $statement.find( '.valueview-instaticmode' );
			if ( $target.length === 0 ) {
				$target = $statement;
			}
			$target.append( buildPopup( list.$element, 'alert', 'wbqc-badparameters-long', 'warning' ).$element );
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
			$( '.wikibase-statementgroupview .wikibase-statementview' )
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
				$( '.wikibase-statementgroupview .wikibase-statementview.' + statementClass )
					.each( function () { addReportsToStatement( data.wbcheckconstraints[ entityId ], $( this ) ); } );
			} );
		} );
	} );
} )( mediaWiki, wikibase, jQuery, OO );
