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
	} );
} );
