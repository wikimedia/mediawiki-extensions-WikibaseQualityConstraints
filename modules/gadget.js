module.exports = ( function ( mw, wb, $, OO ) {
	'use strict';

	var CACHED_STATUSES = 'violation|warning|bad-parameters';

	/**
	 * Create a popup button according to the parameters and append it to the container.
	 *
	 * @param {jQuery} $content Content to be shown in the popup.
	 * @param {jQuery} $container The container to which the button is appended.
	 * @param {string} icon Identifier for an icon as provided by OOUI.
	 * @param {string} iconTitleMessageKey The message key of the title attribute of the icon.
	 * @param {string} [flags] Optional custom flags the {@link OO.ui.PopupButtonWidget} can understand.
	 */
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

	/**
	 * Get a message indicating the given cached status.
	 *
	 * @param {*} cached A value indicating the cached status.
	 * Typically an object with a `maximumAgeInSeconds` member.
	 * @returns {string} HTML
	 */
	function getCachedMessage( cached ) {
		var maximumAgeInMinutes,
			maximumAgeInHours,
			maximumAgeInDays;
		if ( typeof cached === 'object' && cached.maximumAgeInSeconds ) {
			if ( cached.maximumAgeInSeconds < 60 * 90 ) {
				maximumAgeInMinutes = Math.ceil( cached.maximumAgeInSeconds / 60 );
				return mw.message( 'wbqc-cached-minutes' )
					.params( [ maximumAgeInMinutes ] )
					.escaped();
			}
			if ( cached.maximumAgeInSeconds < 60 * 60 * 36 ) {
				maximumAgeInHours = Math.ceil( cached.maximumAgeInSeconds / ( 60 * 60 ) );
				return mw.message( 'wbqc-cached-hours' )
					.params( [ maximumAgeInHours ] )
					.escaped();
			}
			maximumAgeInDays = Math.ceil( cached.maximumAgeInSeconds / ( 60 * 60 * 24 ) );
			return mw.message( 'wbqc-cached-days' )
				.params( [ maximumAgeInDays ] )
				.escaped();
		} else {
			return mw.message( 'wbqc-cached-generic' )
				.escaped();
		}
	}

	/**
	 * Build a panel for a single constraint check result.
	 *
	 * @param {Object} result The constraint check result.
	 * @returns {wikibase.quality.constraints.ui.ConstraintReportPanel}
	 */
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

	/**
	 * Build a panel for a single constraint parameter check result.
	 * This is to `wbcheckconstraintparameters` what {@link buildReport} is to `wbcheckconstraints`.
	 *
	 * @param {Object} problem The constraint parameter check result.
	 * @returns {OO.ui.PanelLayout}
	 */
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

	/**
	 * Build a list of constraint reports from a list of panels,
	 * but return it only if the list is nonempty and contains uncollapsed items.
	 *
	 * @param {wikibase.quality.constraints.ui.ConstraintReportPanel[]} reports
	 * A list of individual report panels as returned by {@link buildReport}.
	 * @returns {wikibase.quality.constraints.ui.ConstraintReportList|null}
	 * The list if it contains at least one uncollapsed panel,
	 * otherwise null.
	 */
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

	/**
	 * Extract the constraint check results for a certain statement from the full API response.
	 *
	 * @param {Object} entityData The constraint check results for a single entity.
	 * @param {string} propertyId The serialization of the property ID of the statement.
	 * @param {string} statementId The ID of the statement.
	 * @returns {Object|null} An object containing lists of constraint check results,
	 * or null if the results could not be extracted.
	 */
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

	/**
	 * Add a popup for the given results to the given snak,
	 * if there are any results to display.
	 *
	 * @param {Array} results A list of constraint check results.
	 * @param {jQuery} $snak The snak to which the results apply.
	 * @returns {boolean} Whether any results were added to the snak or not.
	 */
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
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Add constraint check results to a certain statement.
	 *
	 * @param {Object} entityData The constraint check results for a single entity.
	 * @param {jQuery} $statement The statement.
	 */
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
			reference,
			hasResults;

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
				hasResults = addResultsToSnak(
					reference.results,
					$statement.find(
						'.wikibase-statementview-references ' +
						'.wikibase-referenceview-' + hash + ' ' +
						'.wikibase-snakview-' + reference.hash
					)
				);
				if ( hasResults ) {
					mw.hook( 'wikibase.entityPage.entityView.rendered' ).add( function () {
						var $referenceToggler = $statement
							.find( '.wikibase-statementview-references-heading .ui-toggler-toggle-collapsed' );
						if ( $referenceToggler.length > 0 ) {
							$referenceToggler.data( 'toggler' ).toggle();
						}
					} );
				}
			}
		}
	}

	/**
	 * Add constraint parameter check results for all constraint statements listed in the reports.
	 *
	 * @param {Object} parameterReports A map from constraint ID (i.e., constraint statement ID)
	 * to results for that parameter (overall status and list of problems).
	 */
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

	/**
	 * Get options for the {@link mediaWiki.Api()} constructor.
	 *
	 * @returns {Object} The options.
	 */
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

	function fullCheck( api, lang, entityId ) {
		mw.track( 'counter.MediaWiki.wikibase.quality.constraints.gadget.loadEntity' );
		return api.get( {
			action: 'wbcheckconstraints',
			format: 'json',
			formatversion: 2,
			uselang: lang,
			id: entityId,
			status: CACHED_STATUSES
		} ).then( function ( data ) {
			$( '.wbqc-reports-button' ).remove();
			$( '.wikibase-statementgroupview .wikibase-statementview' )
				.each( function () { addReportsToStatement( data.wbcheckconstraints[ entityId ], $( this ) ); } );
		} );
	}

	function snakCheck( api, lang, entityId, statementId ) {
		var isUpdated = false,
			statementClass = 'wikibase-statement-' + statementId.replace( /\$/, '\\$$' );

		api.get( {
			action: 'wbcheckconstraints',
			format: 'json',
			formatversion: 2,
			uselang: lang,
			claimid: statementId,
			status: CACHED_STATUSES
		} ).then( function ( data ) {
			if ( isUpdated ) {
				return;
			}

			$( '.wikibase-statementgroupview .wikibase-statementview.' + statementClass )
				.each( function () { addReportsToStatement( data.wbcheckconstraints[ entityId ], $( this ) ); } );
		} );
		fullCheck( api, lang, entityId ).then( function () {
			isUpdated = true;
		} );
	}

	function propertyParameterCheck( api, lang, entityId ) {
		mw.track( 'counter.MediaWiki.wikibase.quality.constraints.gadget.loadProperty' );
		return api.get( {
			action: 'wbcheckconstraintparameters',
			format: 'json',
			formatversion: 2,
			uselang: lang,
			propertyid: entityId
		} ).then( function ( data ) {
			addParameterReports( data.wbcheckconstraintparameters[ entityId ] );
		} );
	}

	function SELF() {
		this.defaultBehavior = function () {
			var entityId = mw.config.get( 'wbEntityId' );

			if ( entityId === null || mw.config.get( 'wgMFMode' ) || !mw.config.get( 'wbIsEditView' ) ) {
				// no entity, mobile frontend, or not editing (diff, oldid, …) – skip
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

				fullCheck( api, lang, entityId );

				if ( mw.config.get( 'wgPageContentModel' ) === 'wikibase-property' ) {
					propertyParameterCheck( api, lang, entityId );
				}

				mw.hook( 'wikibase.statement.saved' ).add( function ( entityId, statementId ) {
					mw.track( 'counter.MediaWiki.wikibase.quality.constraints.gadget.saveStatement' );
					snakCheck( api, lang, entityId, statementId );
				} );
			} );
		};
	}

	if ( typeof exports === 'undefined' ) {
		( new SELF() ).defaultBehavior();
	}

	return SELF;

}( mediaWiki, wikibase, jQuery, OO ) );
