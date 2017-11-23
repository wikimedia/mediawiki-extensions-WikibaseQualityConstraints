( function( mw, wb, $, OO ) {
	'use strict';

	var entityId;

	function buildPopup( $content, $container, icon, iconTitleMessageKey, flags /* = '' */ ) {
		var widget = new OO.ui.PopupButtonWidget( {
			icon: icon,
			iconTitle: mw.message( iconTitleMessageKey ).text(),
			flags: flags || '',
			framed: false,
			classes: [ 'wbqc-reports-button', 'wikibase-snakview-indicator' ],
			$overlay: $container.parents( '.wikibase-statementview' ).first(),
			popup: {
				$content: $content,
				width: 400,
				padded: true,
				head: true,
				label: $content.find( '.wbqc-reports:first-child > .oo-ui-labelElement-label *' ).detach()
			}
		} );
		widget.popup.$element.css( 'z-index', 2 ); // prevent collision with rank selector and property grey field

		$container.append( widget.$element );
	}

	function getCachedMessage( cached ) {
		var maximumAgeInMinutes;
		if ( typeof cached === 'object' && cached.maximumAgeInSeconds ) {
			maximumAgeInMinutes = Math.ceil( cached.maximumAgeInSeconds / 60 );
			return mw.message( 'wbqc-cached-minutes' )
				.params( [ maximumAgeInMinutes ] )
				.escaped();
		} else {
			return mw.message( 'wbqc-cached-generic' )
				.escaped();
		}
	}

	function buildReport( result ) {
		var config = {
			status: result.status,
			constraint: result.constraint,
			message: result[ 'message-html' ]
		};
		if ( result.cached ) {
			config.ancillaryMessages = [ getCachedMessage( result.cached ) ];
		}
		return new wb.quality.constraints.ui.ConstraintReportPanel( config );
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
		var statements, index, statement, results = {}, qualifierPID, referenceIndex, referenceHash, referencePID;
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

			results.qualifiers = [];
			for ( qualifierPID in statement.qualifiers ) {
				for ( index in statement.qualifiers[ qualifierPID ] ) {
					results.qualifiers.push(
						statement.qualifiers[ qualifierPID ][ index ]
					);
				}
			}

			results.references = [];
			for ( referenceIndex in statement.references ) {
				referenceHash = statement.references[ referenceIndex ].hash;
				if ( !( referenceHash in results.references ) ) {
					results.references[ referenceHash ] = [];
				}
				for ( referencePID in statement.references[ referenceIndex ].snaks ) {
					for ( index in statement.references[ referenceIndex ].snaks[ referencePID ] ) {
						results.references[ referenceHash ].push(
							statement.references[ referenceIndex ].snaks[ referencePID ][ index ]
						);
					}
				}
			}

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
			haveMandatoryViolations;

		if ( list !== null ) {
			haveMandatoryViolations = list.items[ 0 ].status === 'violation';

			buildPopup(
				list.$element,
				$snak.find( '.wikibase-snakview-indicators' ),
				( haveMandatoryViolations ? '' : 'non-' ) + 'mandatory-constraint-violation',
				haveMandatoryViolations ? 'wbqc-issues-long' : 'wbqc-potentialissues-long'
			);
		}
	}

	function addReportsToStatement( entityData, $statement ) {
		var match = $statement[ 0 ].className.match(
				/\bwikibase-statement-([^\s$]+\$[\dA-F-]+)\b/i
			),
			statementId = match && match[ 1 ],
			propertyId = $statement.parents( '.wikibase-statementgroupview' )[ 0 ].id,
			results = extractResultsForStatement( entityData, propertyId, statementId ),
			index,
			qualifier,
			hash,
			reference;

		if ( results === null ) {
			return;
		}

		addResultsToSnak(
			results.mainsnak,
			$statement.find( '.wikibase-statementview-mainsnak' )
		);

		for ( index in results.qualifiers ) {
			qualifier = results.qualifiers[ index ];
			addResultsToSnak(
				qualifier.results,
				$statement.find(
					'.wikibase-statementview-qualifiers ' +
					'.wikibase-snakview-' + qualifier.hash
				)
			);
		}

		for ( hash in results.references ) {
			for ( index in results.references[ hash ] ) {
				reference = results.references[ hash ][ index ];
				addResultsToSnak(
					reference.results,
					$statement.find(
						'.wikibase-statementview-references ' +
						'.wikibase-referenceview-' + hash + ' ' +
						'.wikibase-snakview-' + reference.hash
					)
				);
			}
		}
	}

	function addParameterReports( parameterReports ) {
		var constraintId,
			status,
			problems,
			reports,
			list,
			$snak;

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

			$snak = $( '.wikibase-statement-' + constraintId.replace( /\$/g, '\\$' ) +
								' .wikibase-statementview-mainsnak .wikibase-snakview' );
			buildPopup(
				list.$element,
				$snak.find( '.wikibase-snakview-indicators' ),
				'alert',
				'wbqc-badparameters-long',
				'warning'
			);
		}
	}

	function mwApiOptions() {
		var gadgetState = mw.loader.getState( 'wikibase.quality.constraints.gadget' );
		return {
			ajax: {
				headers: {
					'X-MediaWiki-Gadget': gadgetState === 'executing' ?
						'checkConstraints' :
						'checkConstraints-custom'
				}
			}
		};
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
		'wikibase.quality.constraints.icon',
		'wikibase.quality.constraints.ui'
	] ).done( function () {
		var api = new mw.Api( mwApiOptions() ),
			lang = mw.config.get( 'wgUserLanguage' );
		mw.track( 'counter.wikibase.quality.constraints.gadget.loadEntity' );
		api.get( {
			action: 'wbcheckconstraints',
			format: 'json',
			formatversion: 2,
			uselang: lang,
			id: entityId
		} ).then( function( data ) {
			$( '.wikibase-statementgroupview .wikibase-statementview' )
				.each( function () { addReportsToStatement( data.wbcheckconstraints[ entityId ], $( this ) ); } );
		} );

		if ( mw.config.get( 'wgPageContentModel' ) === 'wikibase-property' ) {
			mw.track( 'counter.wikibase.quality.constraints.gadget.loadProperty' );
			api.get( {
				action: 'wbcheckconstraintparameters',
				format: 'json',
				formatversion: 2,
				uselang: lang,
				propertyid: entityId
			} ).then( function( data ) {
				addParameterReports( data.wbcheckconstraintparameters[ entityId ] );
			} );
		}

		mw.hook( 'wikibase.statement.saved' ).add( function( entityId, statementId ) {
			mw.track( 'counter.wikibase.quality.constraints.gadget.saveStatement' );
			api.get( {
				action: 'wbcheckconstraints',
				format: 'json',
				formatversion: 2,
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
