describe( 'wikibase.quality.constraints.gadget', function () {
	var expect = require( 'unexpected' ).clone(),
		sinon = require( 'sinon' ),
		sandbox = sinon.createSandbox();

	beforeEach( function () {
		// ensure the module, containing an immediately invoked function, is loaded repeatedly
		delete require.cache[ require.resolve( 'wikibase.quality.constraints.gadget' ) ];

		global.mediaWiki = sandbox.stub();
		global.wikibase = sandbox.stub();
		global.jQuery = sandbox.stub();
		global.OO = sandbox.stub();
	} );

	afterEach( function () {
		sandbox.restore();
	} );

	it( 'gets entity id from wbEntityId', function () {
		global.mediaWiki.config = sinon.stub();
		global.mediaWiki.config.get = sinon.stub();

		require( 'wikibase.quality.constraints.gadget' );

		expect( global.mediaWiki.config.get.withArgs( 'wbEntityId' ).calledOnce, 'to be true' );
	} );

	it( 'checks if wbIsEditView true', function () {
		global.mediaWiki.config = sinon.stub();
		global.mediaWiki.config.get = sinon.stub();
		global.mediaWiki.config.get.withArgs( 'wbEntityId' ).returns( 'Q42' );

		require( 'wikibase.quality.constraints.gadget' );

		expect( global.mediaWiki.config.get.withArgs( 'wbIsEditView' ).calledOnce, 'to be true' );
	} );

	it( 'invokes mw loader and resumes once it is ready', function () {
		var done = sinon.stub();

		global.mediaWiki.config = sinon.stub();
		global.mediaWiki.config.get = sinon.stub();
		global.mediaWiki.config.get.withArgs( 'wbEntityId' ).returns( 'Q42' );
		global.mediaWiki.config.get.withArgs( 'wbIsEditView' ).returns( true );
		global.mediaWiki.loader = sinon.stub();
		global.mediaWiki.loader.using = sinon.stub();
		global.mediaWiki.loader.using.returns( { done: done } );

		require( 'wikibase.quality.constraints.gadget' );

		expect( global.mediaWiki.loader.using.calledOnce, 'to be true' );
		expect( done.calledOnce, 'to be true' );
	} );

} );
