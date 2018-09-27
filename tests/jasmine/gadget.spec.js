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
} );
