module.exports = ( function ( mw, wb, $ ) {
	'use strict';

	const defaultConfig = {
		CACHED_STATUSES: 'violation|warning|suggestion|bad-parameters',
		WBCHECKCONSTRAINTS_MAX_ID_COUNT: 50
	};

	function SELF( config ) {
		this.config = $.extend( {}, defaultConfig, config );
	}

	SELF.prototype.setEntity = function ( entity ) {
		this._entity = entity;
	};

	SELF.prototype.getEntity = function () {
		return this._entity;
	};

	SELF.prototype.fullCheck = function ( api, lang ) {
		const entity = this.getEntity();
		let entityIds = [ entity.getId() ];

		if ( typeof entity.getSubEntityIds === 'function' ) {
			entityIds = entityIds.concat( entity.getSubEntityIds() );
		}

		mw.track( 'counter.MediaWiki.wikibase.quality.constraints.gadget.loadEntity' );
		mw.track( 'stats.mediawiki_wikibase_quality_constraints_gadget_loadentity_total' );
		return this._fullCheckAllIds( api, lang, entityIds );
	};

	SELF.prototype._wbcheckconstraints = function ( api, lang, ids ) {
		return api.get( {
			action: 'wbcheckconstraints',
			format: 'json',
			formatversion: 2,
			uselang: lang,
			id: ids,
			status: this.config.CACHED_STATUSES
		} );
	};

	SELF.prototype._fullCheckAllIds = async function ( api, lang, ids ) {
		const j = ids.length,
			chunk = this.config.WBCHECKCONSTRAINTS_MAX_ID_COUNT,
			entityConstraints = {};

		for ( let i = 0; i < j; i += chunk ) {
			const { wbcheckconstraints } = await this._wbcheckconstraints( api, lang, ids.slice( i, i + chunk ) );
			$.extend( entityConstraints, wbcheckconstraints );
		}

		return this._renderWbcheckconstraintsResult( entityConstraints );
	};

	/**
	 * A placeholder for _renderWbcheckconstraintsResult, actual implementation depends on
	 * being at desktop or mobile UI.
	 */
	SELF.prototype._renderWbcheckconstraintsResult = function () {
	};

	SELF.prototype._getEntityDataByStatementId = function ( response, statementId ) {
		const entities = response.wbcheckconstraints;

		for ( const entity in entities ) {

			if ( Object.prototype.hasOwnProperty.call( entities, entity ) ) {
				const properties = entities[ entity ].claims;
				for ( const property in properties ) {

					if ( Object.prototype.hasOwnProperty.call( properties, property ) ) {
						for ( let index = 0; index < entities[ entity ].claims[ property ].length; index++ ) {

							if ( entities[ entity ].claims[ property ][ index ].id === statementId ) {
								return entities[ entity ];
							}

						}
					}

				}
			}

		}

		return null;
	};

	// It's on mobile and WBUI2025 is loaded
	if ( mw.config.get( 'wgMFMode' ) && mw.loader.getModuleNames().includes( 'wikibase.wbui2025.lib' ) ) {

		SELF.prototype.defaultBehavior = function () {
			const entityId = mw.config.get( 'wbEntityId' );
			if ( entityId === null ) {
				// no entity or not editing (diff, oldid, …) – skip
				return;
			}

			mw.loader.using( [
				'mediawiki.api',
				'pinia',
				'wikibase.quality.constraints.icon',
				'wikibase.quality.constraints.ui',
				'wikibase.EntityInitializer',
				'wikibase.wbui2025.lib'
			] ).done( () => {
				const Pinia = require( 'pinia' );
				const wbui2025 = require( 'wikibase.wbui2025.lib' );
				const api = new mw.Api(),
					lang = mw.config.get( 'wgUserLanguage' );

				mw.hook( 'wikibase.entityPage.entityLoaded' ).add( ( data ) => {
					const entityPromise = $.Deferred( ( deferred ) => {
						deferred.resolve( data );
					} ).promise();
					new wb.EntityInitializer( entityPromise ).getEntity().done( ( entity ) => {
						this.setEntity( entity );
						this.fullCheck( api, lang );
					} );
				} );

				const pinia = Pinia.getActivePinia();
				const editStatementStore = wbui2025.store.useEditStatementsStore( pinia );
				editStatementStore.$onAction(
					( { name, after } ) => {
						after( () => {
							if ( name === 'saveChangedStatements' ) {
								this.fullCheck( api, lang );
							}
						} );
					}
				);
			} );
		};

		SELF.prototype._buildPopoverItemList = function ( results ) {
			// this determines the order that the items appear in the popover
			const statuses = [
				{
					status: 'violation',
					icon: 'error',
					title: mw.message( 'wbqc-issue-heading' ).text()
				},
				{
					status: 'warning',
					icon: 'notice',
					title: mw.message( 'wbqc-potentialissue-heading' ).text()
				},
				{
					status: 'suggestion',
					icon: 'flag',
					title: mw.message( 'wbqc-suggestion-heading' ).text()
				},
				{
					status: 'bad-parameters',
					icon: 'flask',
					title: mw.message( 'wbqc-parameterissue-heading' ).text(),
					description: mw.message( 'wbqc-parameterissues-long' ).text()
				}
			];
			const resultsByStatus = {};
			for ( const result of results ) {
				resultsByStatus[ result.status ] = resultsByStatus[ result.status ] || [];
				resultsByStatus[ result.status ].push( result );
			}

			const popoverItems = [];
			for ( const statusConfig of statuses ) {
				if ( statusConfig.status === 'bad-parameters' && popoverItems.length === 0 ) {
					continue;
				}
				for ( const result of ( resultsByStatus[ statusConfig.status ] || [] ) ) {
					const statusDescriptionHtml = statusConfig.description ? `
	<div class="wikibase-wbui2025-wbqc-status-description">${ mw.html.escape( statusConfig.description ) }</div>` : '';
					const bodyHtml = statusDescriptionHtml + `
	<div class="wikibase-wbui2025-wbqc-constraint">
		<div class="wikibase-wbui2025-wbqc-constraint-header">
			<a target="_blank" href="${ mw.html.escape( result.constraint.link ) }">${ mw.html.escape( result.constraint.typeLabel ) }</a>
		</div>
		<div class="wikibase-wbui2025-wbqc-constraint-content">${ result[ 'message-html' ] }</div>
	</div>`;
					const footerHtml = `
	<div class="wikibase-wbui2025-wbqc-constraint-links">
		<a href="https://www.wikidata.org/wiki/Special:MyLanguage/Help:Property_constraints_portal/${ mw.html.escape( result.constraint.type ) }" title="${ mw.html.escape( mw.message( 'wbqc-constrainttypehelp-long' ).text() ) }">${ mw.message( 'wbqc-constrainttypehelp-short' ).parse() }</a>
		 | <a href="${ mw.html.escape( result.constraint.discussLink ) }" title="${ mw.html.escape( mw.message( 'wbqc-constraintdiscuss-long' ).text() ) }">${ mw.message( 'wbqc-constraintdiscuss-short' ).parse() }</a>
	</div>`;

					popoverItems.push( {
						title: statusConfig.title,
						iconClass: `wikibase-wbui2025-wbqc-icon--${ statusConfig.icon }`,
						bodyHtml,
						footerHtml
					} );
				}
			}
			return popoverItems;
		};

		SELF.prototype._renderWbcheckconstraintsResult = async function ( data ) {
			const require = await mw.loader.using( [
				'wikibase.wbui2025.lib'
			] );
			const wbui2025 = require( 'wikibase.wbui2025.lib' );

			for ( const statementList of Object.values( data ) ) {
				for ( const propertyList of Object.values( statementList.claims ) ) {
					for ( const propertyData of propertyList ) {
						if ( propertyData.mainsnak.results.length > 0 ) {
							const popoverItems = this._buildPopoverItemList( propertyData.mainsnak.results );
							if ( popoverItems.length > 0 ) {
								wbui2025.store.setIndicatorsHtmlForSnakHash(
									propertyData.mainsnak.hash,
									`<span class="${ popoverItems[ 0 ].iconClass }"></span>`
								);
								wbui2025.store.setPopoverContentForSnakHash( propertyData.mainsnak.hash, popoverItems );
							}
						}
					}
				}

			}
		};
	} else {
		SELF.prototype.defaultBehavior = function () {
			const entityId = mw.config.get( 'wbEntityId' );

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
				'wikibase.quality.constraints.ui',
				'wikibase.EntityInitializer'
			] ).done( () => {
				const api = new mw.Api(),
					lang = mw.config.get( 'wgUserLanguage' );

				wb.EntityInitializer.newFromEntityLoadedHook().getEntity().done( ( entity ) => {
					this.setEntity( entity );
					mw.hook( 'wikibase.entityPage.entityView.rendered' ).add( () => {
						this.fullCheck( api, lang );
						if ( mw.config.get( 'wgPageContentModel' ) === 'wikibase-property' ) {
							this.propertyParameterCheck( api, lang, entityId );
						}
					} );
				} );

				mw.hook( 'wikibase.statement.saved' ).add( ( savedEntityId, statementId ) => {
					mw.track( 'counter.MediaWiki.wikibase.quality.constraints.gadget.saveStatement' );
					mw.track( 'stats.mediawiki_wikibase_quality_constraints_gadget_savestatement_total' );
					this.snakCheck( api, lang, statementId );
				} );
			} );
		};

		/**
		 * Because of the way this is implemented it can not be done repeatedly for partial results
		 * (e.g. a wbcheckconstraints result for only some of the entities on a page)
		 *
		 * @param {Object} data Map of entity ids and constraint check information
		 */
		SELF.prototype._renderWbcheckconstraintsResult = function ( data ) {
			const self = this;

			$( '.wbqc-constraint-warning' ).remove();
			$( '.wikibase-statementgroupview .wikibase-statementview' )
				.each( function () {
					for ( const entityId in data ) {
						if ( !Object.prototype.hasOwnProperty.call( data, entityId ) ) {
							continue;
						}
						self._addReportsToStatement( data[ entityId ], $( this ) );
					}
				} );
		};

		SELF.prototype.snakCheck = function ( api, lang, statementId ) {
			let isUpdated = false;
			const statementClass = 'wikibase-statement-' + statementId.replace( /\$/, '\\$$' ),
				self = this;

			api.get( {
				action: 'wbcheckconstraints',
				format: 'json',
				formatversion: 2,
				uselang: lang,
				claimid: statementId,
				status: this.config.CACHED_STATUSES
			} ).then( ( data ) => {
				if ( isUpdated ) {
					return;
				}
				const entityData = self._getEntityDataByStatementId( data, statementId );
				if ( entityData !== null ) {
					self._addReportsToStatement( entityData,
						$( '.wikibase-statementgroupview .wikibase-statementview.' + statementClass )
					);
				}
			} );
			this.fullCheck( api, lang ).then( () => {
				isUpdated = true;
			} );
		};

		SELF.prototype.propertyParameterCheck = function ( api, lang, entityId ) {
			mw.track( 'counter.MediaWiki.wikibase.quality.constraints.gadget.loadProperty' );
			mw.track( 'stats.mediawiki_wikibase_quality_constraints_gadget_loadproperty_total' );
			return api.get( {
				action: 'wbcheckconstraintparameters',
				format: 'json',
				formatversion: 2,
				uselang: lang,
				propertyid: entityId
			} ).then( ( data ) => {
				this._addParameterReports( data.wbcheckconstraintparameters[ entityId ] );
			} );
		};

		/**
		 * Create a popup button according to the parameters and append it to the container.
		 *
		 * @param {jQuery} $content Content to be shown in the popup.
		 * @param {jQuery} $container The container to which the button is appended.
		 * @param {string} icon Identifier for an icon as provided by OOUI.
		 * @param {string} titleMessageKey The message key of the title attribute of the button.
		 * @param {string[]} [classes] Optional list of classes added to the button element
		 * @param {string} [flags] Optional custom flags the {@link OO.ui.PopupButtonWidget} can understand.
		 */
		SELF.prototype._buildPopup = function ( $content, $container, icon, titleMessageKey, classes, flags /* = '' */ ) {
			// eslint-disable-next-line mediawiki/class-doc
			const widget = new OO.ui.PopupButtonWidget( {
				icon: icon,
				// eslint-disable-next-line mediawiki/msg-doc
				title: mw.message( titleMessageKey ).text(),
				flags: flags || '',
				framed: false,
				classes: [ 'wbqc-reports-button', 'wikibase-snakview-indicator' ].concat( classes || [] ),
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
		};

		/**
		 * Get a message indicating the given cached status.
		 *
		 * @param {*} cached A value indicating the cached status.
		 * Typically an object with a `maximumAgeInSeconds` member.
		 * @return {string} HTML
		 */
		SELF.prototype._getCachedMessage = function ( cached ) {
			if ( typeof cached === 'object' && cached.maximumAgeInSeconds ) {
				if ( cached.maximumAgeInSeconds < 60 * 90 ) {
					const maximumAgeInMinutes = Math.ceil( cached.maximumAgeInSeconds / 60 );
					return mw.message( 'wbqc-cached-minutes' )
						.params( [ maximumAgeInMinutes ] )
						.escaped();
				}
				if ( cached.maximumAgeInSeconds < 60 * 60 * 36 ) {
					const maximumAgeInHours = Math.ceil( cached.maximumAgeInSeconds / ( 60 * 60 ) );
					return mw.message( 'wbqc-cached-hours' )
						.params( [ maximumAgeInHours ] )
						.escaped();
				}
				const maximumAgeInDays = Math.ceil( cached.maximumAgeInSeconds / ( 60 * 60 * 24 ) );
				return mw.message( 'wbqc-cached-days' )
					.params( [ maximumAgeInDays ] )
					.escaped();
			} else {
				return mw.message( 'wbqc-cached-generic' )
					.escaped();
			}
		};

		/**
		 * Build a panel for a single constraint check result.
		 *
		 * @param {Object} result The constraint check result.
		 * @return {wikibase.quality.constraints.ui.ConstraintReportPanel}
		 */
		SELF.prototype._buildReport = function ( result ) {
			const config = {
				status: result.status,
				constraint: result.constraint,
				message: result[ 'message-html' ],
				constraintClarification: result[ 'constraint-clarification' ]
			};
			if ( result.cached ) {
				config.ancillaryMessages = [ this._getCachedMessage( result.cached ) ];
			}
			return new wb.quality.constraints.ui.ConstraintReportPanel( config );
		};

		/**
		 * Build a panel for a single constraint parameter check result.
		 * This is to `wbcheckconstraintparameters` what {@link this._buildReport} is to `wbcheckconstraints`.
		 *
		 * @param {Object} problem The constraint parameter check result.
		 * @return {OO.ui.PanelLayout}
		 */
		SELF.prototype._buildParameterReport = function ( problem ) {
			const $report = $( '<div>' )
				.addClass( 'wbqc-parameter-report' );
			const $heading = $( '<h4>' ); // empty for now
			$report.append( $heading );
			const $body = $( '<p>' )
				.html( problem[ 'message-html' ] );
			$report.append( $body );

			return new OO.ui.PanelLayout( {
				expanded: false,
				$content: $report
			} );
		};

		/**
		 * Build a list of constraint reports from a list of panels,
		 * but return it only if the list is nonempty and contains uncollapsed items.
		 *
		 * @param {wikibase.quality.constraints.ui.ConstraintReportPanel[]} reports
		 * A list of individual report panels as returned by {@link this._buildReport}.
		 * @return {wikibase.quality.constraints.ui.ConstraintReportList|null}
		 * The list if it contains at least one uncollapsed panel,
		 * otherwise null.
		 */
		SELF.prototype._buildReportList = function ( reports ) {
			const list = wikibase.quality.constraints.ui.ConstraintReportList.static.fromPanels(
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
							status: 'suggestion',
							label: mw.message( 'wbqc-suggestions-short' ).text()
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
		};

		/**
		 * Extract the constraint check results for a certain statement from the full API response.
		 *
		 * @param {Object} entityData The constraint check results for a single entity.
		 * @param {string} propertyId The serialization of the property ID of the statement.
		 * @param {string} statementId The ID of the statement.
		 * @return {Object|null} An object containing lists of constraint check results,
		 * or null if the results could not be extracted.
		 */
		SELF.prototype._extractResultsForStatement = function ( entityData, propertyId, statementId ) {
			const statements = entityData.claims[ propertyId ];
			if ( statements === undefined ) {
				return null;
			}
			let statement;
			for ( const index in statements ) {
				if ( statements[ index ].id === statementId ) {
					statement = statements[ index ];
					break;
				}
			}
			if ( statement === undefined ) {
				return null;
			}

			const results = {};
			results.mainsnak = statement.mainsnak.results;

			results.qualifiers = [];
			for ( const qualifierPID in statement.qualifiers ) {
				for ( const index in statement.qualifiers[ qualifierPID ] ) {
					results.qualifiers.push(
						statement.qualifiers[ qualifierPID ][ index ]
					);
				}
			}

			results.references = [];
			for ( const referenceIndex in statement.references ) {
				const referenceHash = statement.references[ referenceIndex ].hash;
				if ( !( referenceHash in results.references ) ) {
					results.references[ referenceHash ] = [];
				}
				for ( const referencePID in statement.references[ referenceIndex ].snaks ) {
					for ( const index in statement.references[ referenceIndex ].snaks[ referencePID ] ) {
						results.references[ referenceHash ].push(
							statement.references[ referenceIndex ].snaks[ referencePID ][ index ]
						);
					}
				}
			}

			return results;
		};

		/**
		 * Add a popup for the given results to the given snak,
		 * if there are any results to display.
		 *
		 * @param {Array} results A list of constraint check results.
		 * @param {jQuery} $snak The snak to which the results apply.
		 * @return {boolean} Whether any results were added to the snak or not.
		 */
		SELF.prototype._addResultsToSnak = function ( results, $snak ) {
			const reports = results.map( this._buildReport.bind( this ) ),
				list = this._buildReportList( reports );
			let icon, titleMessageKey;

			if ( list !== null ) {
				switch ( list.items[ 0 ].status ) {
					case 'violation':
						icon = 'mandatory-constraint-violation';
						titleMessageKey = 'wbqc-issues-long';
						break;
					case 'warning':
						icon = 'non-mandatory-constraint-violation';
						titleMessageKey = 'wbqc-potentialissues-long';
						break;
					case 'suggestion':
						icon = 'suggestion-constraint-violation';
						titleMessageKey = 'wbqc-suggestions-long';
						break;
					default:
						throw new Error( 'unexpected status ' + list.items[ 0 ].status );
				}

				this._buildPopup(
					list.$element,
					$snak.find( '.wikibase-snakview-indicators' ),
					icon,
					titleMessageKey,
					[ 'wbqc-constraint-warning' ]
				);
				return true;
			} else {
				return false;
			}
		};

		/**
		 * Add constraint check results to a certain statement.
		 *
		 * @param {Object} entityData The constraint check results for a single entity.
		 * @param {jQuery} $statement The statement.
		 */
		SELF.prototype._addReportsToStatement = function ( entityData, $statement ) {
			const match = $statement[ 0 ].className.match(
					/\bwikibase-statement-([^\s$]+\$[\dA-F-]+)\b/i
				),
				statementId = match && match[ 1 ],
				propertyId = $statement.parents( '.wikibase-statementgroupview' ).data( 'property-id' ),
				results = this._extractResultsForStatement( entityData, propertyId, statementId );

			if ( results === null ) {
				return;
			}

			this._addResultsToSnak(
				results.mainsnak,
				$statement.find( '.wikibase-statementview-mainsnak' )
			);

			for ( const index in results.qualifiers ) {
				const qualifier = results.qualifiers[ index ];
				this._addResultsToSnak(
					qualifier.results,
					$statement.find(
						'.wikibase-statementview-qualifiers ' +
						'.wikibase-snakview-' + qualifier.hash
					)
				);
			}

			for ( const hash in results.references ) {
				for ( const index in results.references[ hash ] ) {
					const reference = results.references[ hash ][ index ];
					const hasResults = this._addResultsToSnak(
						reference.results,
						$statement.find(
							'.wikibase-statementview-references ' +
							'.wikibase-referenceview-' + hash + ' ' +
							'.wikibase-snakview-' + reference.hash
						)
					);
					if ( hasResults ) {
						mw.hook( 'wikibase.entityPage.entityView.rendered' ).add( () => {
							const $referenceToggler = $statement
								.find( '.wikibase-statementview-references-heading .ui-toggler-toggle-collapsed' );
							if ( $referenceToggler.length > 0 ) {
								$referenceToggler.data( 'toggler' ).toggle();
							}
						} );
					}
				}
			}
		};

		/**
		 * Add constraint parameter check results for all constraint statements listed in the reports.
		 *
		 * @param {Object} parameterReports A map from constraint ID (i.e., constraint statement ID)
		 * to results for that parameter (overall status and list of problems).
		 */
		SELF.prototype._addParameterReports = function ( parameterReports ) {
			for ( const constraintId in parameterReports ) {
				const status = parameterReports[ constraintId ].status;
				if ( status === 'okay' ) {
					continue;
				}

				const problems = parameterReports[ constraintId ].problems;
				const reports = problems.map( this._buildParameterReport.bind( this ) );

				const list = new wikibase.quality.constraints.ui.ConstraintReportList( {
					items: [
						new wikibase.quality.constraints.ui.ConstraintReportGroup( {
							items: reports,
							label: mw.message( 'wbqc-badparameters-short' ).text()
						} )
					],
					expanded: false // expanded: true does not work within a popup
				} );

				const $snak = $( '.wikibase-statement-' + constraintId.replace( /\$/g, '\\$' ) +
					' .wikibase-statementview-mainsnak .wikibase-snakview' );
				this._buildPopup(
					list.$element,
					$snak.find( '.wikibase-snakview-indicators' ),
					'alert',
					'wbqc-badparameters-long',
					[ 'wbqc-parameter-warning' ],
					'warning'
				);
			}
		};
	}

	if ( typeof exports === 'undefined' ) {
		( new SELF() ).defaultBehavior();
	}

	return SELF;

}( mediaWiki, wikibase, jQuery ) );
