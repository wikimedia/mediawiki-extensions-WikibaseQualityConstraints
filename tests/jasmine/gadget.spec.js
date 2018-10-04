describe( 'wikibase.quality.constraints.gadget', function () {
	var expect = require( 'unexpected' ).clone(),
		sinon = require( 'sinon' ),
		Gadget,
		loadedEntity;

	beforeEach( function () {
		// ensure the module, containing immediately invoked code, is loaded repeatedly
		delete require.cache[ require.resolve( 'wikibase.quality.constraints.gadget' ) ];

		global.mediaWiki = sinon.stub();
		global.wikibase = sinon.stub();
		global.jQuery = sinon.stub();
		global.OO = sinon.stub();

		// poor man's Promise.all()
		global.jQuery.when = function () {
			var promises = arguments,
				length = promises.length,
				results = [],
				i;
			for ( i = 0; i < length; i++ ) {
				promises[ i ].then( function ( data ) {
					results.push( data );
				} );
			}
			return {
				then: function ( func ) {
					var thenResult = func.apply( {}, results );
					return {
						then: function ( func ) {
							func( thenResult );
							return {
								promise: sinon.spy()
							};
						}
					};
				}
			};
		};

		loadedEntity = {};

		global.wikibase.EntityInitializer = {
			newFromEntityLoadedHook: function () {
				return {
					getEntity: function () {
						return {
							done: sinon.stub().yields( loadedEntity )
						};
					}
				};
			}
		};

		Gadget = require( 'wikibase.quality.constraints.gadget' );
	} );

	it( 'exports an invokable module', function () {
		expect( typeof Gadget, 'to equal', 'function' );
	} );

	describe( 'config', function () {
		it( 'has default values', function () {
			var gadget = new Gadget();
			expect( gadget.config.CACHED_STATUSES, 'to equal', 'violation|warning|bad-parameters' );
			expect( gadget.config.WBCHECKCONSTRAINTS_MAX_ID_COUNT, 'to equal', 50 );
		} );

		it( 'can be overwritten by constructor parameter', function () {
			var gadget = new Gadget( { WBCHECKCONSTRAINTS_MAX_ID_COUNT: 3 } );
			expect( gadget.config.WBCHECKCONSTRAINTS_MAX_ID_COUNT, 'to equal', 3 );
		} );
	} );

	describe( 'default behavior', function () {
		it( 'gets entity id from wbEntityId', function () {
			var gadget = new Gadget();

			global.mediaWiki.config = sinon.stub();
			global.mediaWiki.config.get = sinon.stub();

			gadget.defaultBehavior();

			expect( global.mediaWiki.config.get.withArgs( 'wbEntityId' ).calledOnce, 'to be true' );
		} );

		it( 'checks if wbIsEditView true', function () {
			var gadget = new Gadget();

			global.mediaWiki.config = sinon.stub();
			global.mediaWiki.config.get = sinon.stub();
			global.mediaWiki.config.get.withArgs( 'wbEntityId' ).returns( 'Q42' );

			gadget.defaultBehavior();

			expect( global.mediaWiki.config.get.withArgs( 'wbIsEditView' ).calledOnce, 'to be true' );
		} );

		it( 'invokes mw loader and resumes once it is ready', function () {
			var gadget = new Gadget(),
				done = sinon.stub();

			global.mediaWiki.config = sinon.stub();
			global.mediaWiki.config.get = sinon.stub();
			global.mediaWiki.config.get.withArgs( 'wbEntityId' ).returns( 'Q42' );
			global.mediaWiki.config.get.withArgs( 'wbIsEditView' ).returns( true );
			global.mediaWiki.loader = sinon.stub();
			global.mediaWiki.loader.using = sinon.stub();
			global.mediaWiki.loader.using.returns( { done: done } );

			gadget.defaultBehavior();

			expect( global.mediaWiki.loader.using.calledOnce, 'to be true' );
			expect( done.calledOnce, 'to be true' );
		} );

		it( 'runs a fullCheck once mw loader is done', function () {
			var gadget = new Gadget(),
				statementSavedSpy = sinon.spy(),
				wgUserLanguage = 'de',
				wbEntityId = 'Q42';

			global.mediaWiki.config = sinon.stub();
			global.mediaWiki.config.get = sinon.stub();
			global.mediaWiki.config.get.withArgs( 'wbEntityId' ).returns( wbEntityId );
			global.mediaWiki.config.get.withArgs( 'wbIsEditView' ).returns( true );
			global.mediaWiki.loader = sinon.stub();
			global.mediaWiki.loader.using = sinon.stub();
			global.mediaWiki.loader.using.returns( { done: sinon.stub().yields() } );

			global.mediaWiki.loader.getState = sinon.stub();
			global.mediaWiki.loader.getState.withArgs( 'wikibase.quality.constraints.gadget' ).returns( 'executing' );
			global.mediaWiki.Api = sinon.stub();
			global.mediaWiki.Api.prototype.get = sinon.stub().returns( {
				then: sinon.stub().returns( {
					then: sinon.stub()
				} )
			} );
			global.mediaWiki.config.get.withArgs( 'wgUserLanguage' ).returns( wgUserLanguage );

			global.mediaWiki.hook = sinon.stub();
			loadedEntity = {
				getId: sinon.stub().returns( wbEntityId )
			};
			global.mediaWiki.hook.withArgs( 'wikibase.statement.saved' ).returns( { add: statementSavedSpy } );

			global.mediaWiki.track = sinon.spy();

			gadget._renderWbcheckconstraintsResult = sinon.stub();

			gadget.defaultBehavior();

			sinon.assert.calledWith(
				global.mediaWiki.track,
				'counter.MediaWiki.wikibase.quality.constraints.gadget.loadEntity'
			);
			sinon.assert.calledWith( global.mediaWiki.Api.prototype.get, {
				action: 'wbcheckconstraints',
				format: 'json',
				formatversion: 2,
				uselang: wgUserLanguage,
				id: [ wbEntityId ], // in a real request the mw.Api would format this with pipes
				status: gadget.config.CACHED_STATUSES
			} );
			sinon.assert.callCount( gadget._renderWbcheckconstraintsResult, 1 );
			sinon.assert.calledWith( global.mediaWiki.hook, 'wikibase.statement.saved' );
			expect( statementSavedSpy.calledOnce, 'to be true' );
		} );
	} );

	describe( 'fullCheck', function () {
		it( 'tracks usage', function () {
			var gadget = new Gadget(),
				lang = 'fr',
				entityId = 'Q42',
				api = {
					get: sinon.stub().returns( {
						then: sinon.stub().returns( {
							then: sinon.stub()
						} )
					} )
				};

			global.mediaWiki.track = sinon.spy();

			gadget._renderWbcheckconstraintsResult = sinon.spy();

			gadget.fullCheck( api, lang, entityId );

			sinon.assert.calledWith( global.mediaWiki.track, 'counter.MediaWiki.wikibase.quality.constraints.gadget.loadEntity' );
		} );

		it( 'calls api with correct parameters', function () {
			var gadget = new Gadget(),
				lang = 'fr',
				entityId = 'Q42',
				api = {
					get: sinon.stub().returns( {
						then: sinon.stub().returns( {
							then: sinon.stub()
						} )
					} )
				};

			global.mediaWiki.track = sinon.spy();

			gadget._renderWbcheckconstraintsResult = sinon.spy();

			gadget.fullCheck( api, lang, entityId );

			sinon.assert.calledWith( api.get, {
				action: 'wbcheckconstraints',
				format: 'json',
				formatversion: 2,
				uselang: 'fr',
				id: [ entityId ],
				status: gadget.config.CACHED_STATUSES
			} );
		} );

		it( 'uses api response to update DOM statements', function () {
			var gadget = new Gadget(),
				lang = 'fr',
				entityId = 'Q42',
				api = {
					get: sinon.stub()
				},
				responseData = {
					wbcheckconstraints: {
						Q42: {
							claims: {
								P9: [ {
									id: 'Q42$2c9c5e39-4d4c-23f0-5bcb-b92615bf7aa2',
									mainsnak: {
										hash: '261be448fb9ca79fd5e3fb45e7b810c5d33c2e4d',
										results: [ {
											status: 'warning',
											property: 'P9',
											constraint: {
												id: 'P9$197313ce-4014-c893-e2c4-5eb1f9347945',
												type: 'Q1283',
												typeLabel: 'MultiValueConstraintItem',
												link: 'https://test.wikidata.org/wiki/Property:P9#P9$197313ce-4014-c893-e2c4-5eb1f9347945',
												discussLink: 'https://test.wikidata.org/wiki/Property_talk:P9'
											},
											'message-html': 'This property should contain multiple values.',
											claim: 'Q42$2c9c5e39-4d4c-23f0-5bcb-b92615bf7aa2'
										} ]
									}
								} ]
							}
						}
					}, success: 1
				},
				preExistingReportButtonsRemovedSpy = sinon.spy();

			global.mediaWiki.track = sinon.spy();
			api.get.withArgs( {
				action: 'wbcheckconstraints',
				format: 'json',
				formatversion: 2,
				uselang: 'fr',
				id: [ entityId ],
				status: gadget.config.CACHED_STATUSES
			} ).returns( {
				then: sinon.stub().returns( {
					then: sinon.stub().yields( responseData )
				} )
			} );
			global.jQuery.withArgs( '.wbqc-reports-button' ).returns( {
				remove: preExistingReportButtonsRemovedSpy
			} );
			global.jQuery.withArgs( '.wikibase-statementgroupview .wikibase-statementview' ).returns( {
				each: sinon.stub().yields()
			} );
			gadget._addReportsToStatement = sinon.spy();

			gadget.fullCheck( api, lang, entityId );

			sinon.assert.called( preExistingReportButtonsRemovedSpy );
			sinon.assert.calledWith( gadget._addReportsToStatement, responseData.wbcheckconstraints.Q42 );
		} );
	} );

	describe( '_fullCheckAllIds', function () {
		it( 'chunks requests', function () {
			var gadget = new Gadget( { WBCHECKCONSTRAINTS_MAX_ID_COUNT: 2 } ),
				api = sinon.stub(),
				lang = 'fr';

			gadget._wbcheckconstraints = sinon.stub();
			gadget._wbcheckconstraints.returns( { then: sinon.stub() } );
			gadget._aggregateMultipleWbcheckconstraintsResponses = sinon.spy();
			gadget._renderWbcheckconstraintsResult = sinon.spy();

			gadget._fullCheckAllIds( api, lang, [ 'L2', 'L2-F1', 'L2-F2', 'L2-F3', 'L2-S1', 'L2-S2', 'L2-F3' ] );

			sinon.assert.callCount( gadget._wbcheckconstraints, 4 );
			sinon.assert.calledWith( gadget._wbcheckconstraints, api, lang, [ 'L2', 'L2-F1' ] );
			sinon.assert.calledWith( gadget._wbcheckconstraints, api, lang, [ 'L2-F2', 'L2-F3' ] );
			sinon.assert.calledWith( gadget._wbcheckconstraints, api, lang, [ 'L2-S1', 'L2-S2' ] );
			sinon.assert.calledWith( gadget._wbcheckconstraints, api, lang, [ 'L2-F3' ] );

			sinon.assert.callCount( gadget._aggregateMultipleWbcheckconstraintsResponses, 1 );
			sinon.assert.callCount( gadget._renderWbcheckconstraintsResult, 1 );
		} );
	} );

	describe( '_aggregateMultipleWbcheckconstraintsResponses', function () {
		it( 'can combine multiple responses\' entity information', function () {
			var gadget = new Gadget(),
				responseOne = {
					wbcheckconstraints: {
						L2: {
							data: 'here'
						}
					}
				},
				responseTwo = {
					wbcheckconstraints: {
						'L2-F1': {
							data: 'more of it'
						}
					}
				},
				combinedResponse;

			combinedResponse = gadget._aggregateMultipleWbcheckconstraintsResponses( responseOne, responseTwo );

			expect( combinedResponse.L2, 'to equal', responseOne.wbcheckconstraints.L2 );
			expect( combinedResponse[ 'L2-F1' ], 'to equal', responseTwo.wbcheckconstraints[ 'L2-F1' ] );
		} );
	} );

	describe( '_addReportsToStatement', function () {
		var entityData = {
				claims: {
					P9: [ {
						id: 'Q42$2c9c5e39-4d4c-23f0-5bcb-b92615bf7aa2',
						mainsnak: {
							hash: '261be448fb9ca79fd5e3fb45e7b810c5d33c2e4d',
							results: [ {
								status: 'warning',
								property: 'P9',
								constraint: {
									id: 'P9$197313ce-4014-c893-e2c4-5eb1f9347945',
									type: 'Q1283',
									typeLabel: 'MultiValueConstraintItem',
									link: 'https://test.wikidata.org/wiki/Property:P9#P9$197313ce-4014-c893-e2c4-5eb1f9347945',
									discussLink: 'https://test.wikidata.org/wiki/Property_talk:P9'
								},
								'message-html': 'This property should contain multiple values.',
								claim: 'Q42$2c9c5e39-4d4c-23f0-5bcb-b92615bf7aa2'
							} ]
						},
						qualifiers: {
							P9: [ {
								hash: 'ab294fd4a345d913a4c96a91b184e563f9431847',
								results: [ {
									status: 'warning',
									property: 'P9',
									constraint: {
										id: 'P9$197313ce-4014-c893-e2c4-5eb1f9347945',
										type: 'Q1283',
										typeLabel: 'MultiValueConstraintItem',
										link: 'https://test.wikidata.org/wiki/Property:P9#P9$197313ce-4014-c893-e2c4-5eb1f9347945',
										discussLink: 'https://test.wikidata.org/wiki/Property_talk:P9'
									},
									'message-html': 'This property should contain multiple values.'
								} ]
							} ]
						},
						references: [
							{
								hash: 'ec294fd4a345d913a4c96a91b184e563f9431832',
								snaks: {
									P9: [ {
										hash: '261be448fb9ca79fd5e3fb45e7b810c5d33c2e4d',
										results: [ {
											status: 'warning',
											property: 'P9',
											constraint: {
												id: 'P9$197313ce-4014-c893-e2c4-5eb1f9347945',
												type: 'Q1283',
												typeLabel: 'MultiValueConstraintItem',
												link: 'https://test.wikidata.org/wiki/Property:P9#P9$197313ce-4014-c893-e2c4-5eb1f9347945',
												discussLink: 'https://test.wikidata.org/wiki/Property_talk:P9'
											},
											'message-html': 'This property should contain multiple values.'
										} ]
									} ]
								}
							}
						]
					} ]
				}
			},
			dataStub = sinon.stub().withArgs( 'property-id' ).returns( 'P9' ),
			$statement = {
				0: {
					className: 'wikibase-statement-Q42$2c9c5e39-4d4c-23f0-5bcb-b92615bf7aa2'
				},
				parents: sinon.stub().returns( {
					data: dataStub
				} )
			};

		it( 'extracts result for statement with property id and statement id', function () {
			var gadget = new Gadget();

			gadget._extractResultsForStatement = sinon.stub();
			gadget._extractResultsForStatement.returns( null );

			gadget._addReportsToStatement( entityData, $statement );

			sinon.assert.calledWith(
				gadget._extractResultsForStatement,
				entityData,
				'P9',
				'Q42$2c9c5e39-4d4c-23f0-5bcb-b92615bf7aa2'
			);
		} );

		it( 'adds results to main snak', function () {
			var gadget = new Gadget(),
				$mainsnak = {};

			gadget._extractResultsForStatement = sinon.stub()
				.withArgs( entityData, 'P9', 'Q42$2c9c5e39-4d4c-23f0-5bcb-b92615bf7aa2' )
				.returns( entityData.claims.P9[ 0 ] );
			$statement.find = sinon.stub().withArgs( '.wikibase-statementview-mainsnak' ).returns( $mainsnak );
			gadget._addResultsToSnak = sinon.spy();

			gadget._addReportsToStatement( entityData, $statement );

			sinon.assert.calledWith(
				gadget._addResultsToSnak,
				entityData.claims.P9[ 0 ].mainsnak,
				$mainsnak
			);
		} );

		it( 'adds results to qualifiers', function () {
			var gadget = new Gadget(),
				$qualifierSnak = {};

			gadget._extractResultsForStatement = sinon.stub()
				.withArgs( entityData, 'P9', 'Q42$2c9c5e39-4d4c-23f0-5bcb-b92615bf7aa2' )
				.returns( { // gets reformatted by _extractResultsForStatement
					qualifiers: entityData.claims.P9[ 0 ].qualifiers.P9
				} );
			$statement.find = sinon.stub()
				.withArgs( '.wikibase-statementview-qualifiers .wikibase-snakview-ab294fd4a345d913a4c96a91b184e563f9431847' )
				.returns( $qualifierSnak );
			gadget._addResultsToSnak = sinon.spy();

			gadget._addReportsToStatement( entityData, $statement );

			sinon.assert.calledWith(
				gadget._addResultsToSnak,
				entityData.claims.P9[ 0 ].qualifiers.P9[ 0 ].results,
				$qualifierSnak
			);
		} );

		it( 'adds results to references', function () {
			var gadget = new Gadget(),
				$referenceSnak = {},
				entityViewrenderedHook = sinon.stub().yields(),
				togglerToggledSpy = sinon.spy();

			gadget._extractResultsForStatement = sinon.stub()
				.withArgs( entityData, 'P9', 'Q42$2c9c5e39-4d4c-23f0-5bcb-b92615bf7aa2' )
				.returns( { // gets reformatted by _extractResultsForStatement
					references: {
						ec294fd4a345d913a4c96a91b184e563f9431832: [
							entityData.claims.P9[ 0 ].references[ 0 ].snaks.P9[ 0 ]
						]
					}
				} );
			$statement.find = sinon.stub();
			$statement.find.withArgs(
					'.wikibase-statementview-references ' +
					'.wikibase-referenceview-ec294fd4a345d913a4c96a91b184e563f9431832 ' +
					'.wikibase-snakview-261be448fb9ca79fd5e3fb45e7b810c5d33c2e4d'
				)
				.returns( $referenceSnak );
			gadget._addResultsToSnak = sinon.stub().returns( true );
			global.mediaWiki.hook = sinon.stub();
			global.mediaWiki.hook.withArgs( 'wikibase.entityPage.entityView.rendered' ).returns( {
				add: entityViewrenderedHook
			} );
			$statement.find.withArgs( '.wikibase-statementview-references-heading .ui-toggler-toggle-collapsed' )
				.returns( {
					length: 1,
					data: sinon.stub().withArgs( 'toggler' ).returns( {
						toggle: togglerToggledSpy
					} )
				} );

			gadget._addReportsToStatement( entityData, $statement );

			sinon.assert.calledWith(
				gadget._addResultsToSnak,
				entityData.claims.P9[ 0 ].references[ 0 ].snaks.P9[ 0 ].results,
				$referenceSnak
			);
			sinon.assert.calledOnce( togglerToggledSpy );
		} );
	} );

	describe( '_extractResultsForStatement', function () {
		it( 'finds constraint violation result in entity data', function () {
			var gadget = new Gadget(),
				entityData = {
					claims: {
						P9: [ {
							id: 'Q42$2c9c5e39-4d4c-23f0-5bcb-b92615bf7aa2',
							mainsnak: {
								hash: '261be448fb9ca79fd5e3fb45e7b810c5d33c2e4d',
								results: [ {
									status: 'warning',
									property: 'P9',
									constraint: {
										id: 'P9$197313ce-4014-c893-e2c4-5eb1f9347945',
										type: 'Q1283',
										typeLabel: 'MultiValueConstraintItem',
										link: 'https://test.wikidata.org/wiki/Property:P9#P9$197313ce-4014-c893-e2c4-5eb1f9347945',
										discussLink: 'https://test.wikidata.org/wiki/Property_talk:P9'
									},
									'message-html': 'This property should contain multiple values.',
									claim: 'Q42$2c9c5e39-4d4c-23f0-5bcb-b92615bf7aa2'
								} ]
							}
						} ]
					}
				},
				result;

			result = gadget._extractResultsForStatement( entityData, 'P9', 'Q42$2c9c5e39-4d4c-23f0-5bcb-b92615bf7aa2' );

			expect( result.qualifiers, 'to equal', [] );
			expect( result.references, 'to equal', [] );
			expect( result.mainsnak, 'to equal', entityData.claims.P9[ 0 ].mainsnak.results );
		} );
	} );

	describe( 'fullCheckEntityAndSubentities', function () {
		it( 'gets and passes entity id from entity', function () {
			var gadget = new Gadget(),
				api = {},
				lang = 'hu',
				entity = {
					getId: sinon.stub().returns( 'L42' )
				};
			gadget.fullCheck = sinon.spy();

			gadget.fullCheckEntityAndSubentities( api, lang, entity );

			sinon.assert.calledWith( gadget.fullCheck, api, lang, [ 'L42' ] );
		} );

		it( 'gets and passes sub entity ids from entity too if available', function () {
			var gadget = new Gadget(),
				api = {},
				lang = 'hu',
				entity = {
					getId: sinon.stub().returns( 'L42' ),
					getSubEntityIds: sinon.stub().returns( [ 'L42-F1', 'L42-S3' ] )
				};
			gadget.fullCheck = sinon.spy();

			gadget.fullCheckEntityAndSubentities( api, lang, entity );

			sinon.assert.calledWith( gadget.fullCheck, api, lang, [ 'L42', 'L42-F1', 'L42-S3' ] );
		} );
	} );
} );
