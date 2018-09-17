describe( 'wikibase.quality.constraints.gadget', function () {
	var expect = require( 'unexpected' ).clone(),
		sinon = require( 'sinon' ),
		Gadget;

	beforeEach( function () {
		// ensure the module, containing immediately invoked code, is loaded repeatedly
		delete require.cache[ require.resolve( 'wikibase.quality.constraints.gadget' ) ];

		global.mediaWiki = sinon.stub();
		global.wikibase = sinon.stub();
		global.jQuery = sinon.stub();
		global.OO = sinon.stub();

		Gadget = require( 'wikibase.quality.constraints.gadget' );
	} );

	it( 'exports an invokable module', function () {
		expect( typeof Gadget, 'to equal', 'function' );
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
				loaderDone = sinon.stub(),
				apiDone = sinon.stub(),
				hookAdded = sinon.spy(),
				wgUserLanguage = 'de',
				wbEntityId = 'Q42';

			global.mediaWiki.config = sinon.stub();
			global.mediaWiki.config.get = sinon.stub();
			global.mediaWiki.config.get.withArgs( 'wbEntityId' ).returns( wbEntityId );
			global.mediaWiki.config.get.withArgs( 'wbIsEditView' ).returns( true );
			global.mediaWiki.loader = sinon.stub();
			global.mediaWiki.loader.using = sinon.stub();
			global.mediaWiki.loader.using.returns( { done: loaderDone } );

			gadget.defaultBehavior();

			global.mediaWiki.loader.getState = sinon.stub();
			global.mediaWiki.loader.getState.withArgs( 'wikibase.quality.constraints.gadget' ).returns( 'executing' );
			global.mediaWiki.Api = sinon.stub();
			global.mediaWiki.Api.prototype.get = sinon.stub();
			global.mediaWiki.Api.prototype.get.returns( { then: apiDone } );
			global.mediaWiki.config.get.withArgs( 'wgUserLanguage' ).returns( wgUserLanguage );

			global.mediaWiki.track = sinon.spy();
			global.mediaWiki.hook = sinon.stub();
			global.mediaWiki.hook.returns( { add: hookAdded } );

			loaderDone.yield();

			sinon.assert.calledWith(
				global.mediaWiki.track,
				'counter.MediaWiki.wikibase.quality.constraints.gadget.loadEntity'
			);
			sinon.assert.calledWith( global.mediaWiki.Api.prototype.get, {
				action: 'wbcheckconstraints',
				format: 'json',
				formatversion: 2,
				uselang: wgUserLanguage,
				id: wbEntityId,
				status: 'violation|warning|bad-parameters' // "cachedStatuses"
			} );
			sinon.assert.calledWith( global.mediaWiki.hook, 'wikibase.statement.saved' );
			expect( hookAdded.calledOnce, 'to be true' );
		} );
	} );

	describe( 'fullCheck', function () {
		it( 'tracks usage', function () {
			var gadget = new Gadget(),
				lang = 'fr',
				entityId = 'Q42',
				api = {
					get: sinon.stub()
				},
				apiPromise = {
					then: sinon.stub()
				};

			global.mediaWiki.track = sinon.spy();
			api.get.returns( apiPromise );

			gadget.fullCheck( api, lang, entityId );

			sinon.assert.calledWith( global.mediaWiki.track, 'counter.MediaWiki.wikibase.quality.constraints.gadget.loadEntity' );
		} );

		it( 'calls api with correct parameters', function () {
			var gadget = new Gadget(),
				lang = 'fr',
				entityId = 'Q42',
				api = {
					get: sinon.stub()
				},
				apiPromise = {
					then: sinon.stub()
				};

			global.mediaWiki.track = sinon.spy();
			api.get.returns( apiPromise );

			gadget.fullCheck( api, lang, entityId );

			sinon.assert.calledWith( api.get, {
				action: 'wbcheckconstraints',
				format: 'json',
				formatversion: 2,
				uselang: 'fr',
				id: 'Q42',
				status: 'violation|warning|bad-parameters' // "cachedStatuses"
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
				id: 'Q42',
				status: 'violation|warning|bad-parameters' // "cachedStatuses"
			} ).returns( {
				then: sinon.stub().yields( responseData )
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
} );
