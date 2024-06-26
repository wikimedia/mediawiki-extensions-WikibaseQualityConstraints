describe( 'wikibase.quality.constraints.suggestions.EntitySelectorHookHandler', () => {
	const expect = require( 'unexpected' ).clone(),
		sinon = require( 'sinon' ),
		makeHookHandler = require( 'wikibase.quality.constraints.suggestions.EntitySelectorHookHandlerFactory' ),
		defaultMockConfig = {
			WBQualityConstraintsPropertyId: 'P1',
			WBQualityConstraintsQualifierOfPropertyConstraintId: 'P2',
			WBQualityConstraintsPropertyConstraintId: 'Q1',
			WBQualityConstraintsOneOfConstraintId: 'Q2',
			WBQualityConstraintsAllowedQualifiersConstraintId: 'Q3'
		},
		defaultMockPayload = {
			options: {
				type: 'property'
			},
			element: {},
			term: ''
		};

	function getHookHandler( isQualifierContext, config ) {
		const mockContextChecker = function () {
				return isQualifierContext !== undefined ? isQualifierContext : true;
			},
			mockMainSnakPropertyIdGetter = sinon.stub().returns( 'P42' ),
			ensuredConfig = config || {},
			mockConfig = {},
			mockJquery = sinon.stub(),
			mockMediaWiki = sinon.stub();

		// TODO: Find a better way to mock jQuery and mw
		mockJquery.getJSON = sinon.stub().resolves( {} );
		mockJquery.when = sinon.stub();
		mockJquery.when.apply = sinon.stub().resolves( {} );
		mockMediaWiki.config = sinon.stub();
		mockMediaWiki.config.get = sinon.stub().returns( {
			url: 'test'
		} );

		for ( const key in defaultMockConfig ) {
			mockConfig[ key ] = ensuredConfig[ key ] || defaultMockConfig[ key ];
		}

		return makeHookHandler(
			mockJquery,
			mockMediaWiki,
			mockConfig,
			mockContextChecker,
			mockMainSnakPropertyIdGetter
		);
	}

	it( 'exports an invokable module', () => {
		expect( makeHookHandler, 'to be a', 'function' );
	} );

	describe( 'getSearchHandler', () => {
		it( 'Returns a handler function', () => {
			const handler = getHookHandler();
			expect( handler, 'to be a', 'function' );
		} );
	} );

	describe( 'returned search handler', () => {
		it( 'calls passed callback', () => {
			const handler = getHookHandler(),
				mockCB = sinon.spy();
			handler( defaultMockPayload, mockCB );
			expect( mockCB.called, 'to be true' );
		} );

		it( 'doesn\'t call passed callback for items with qualifier context', () => {
			const handler = getHookHandler(),
				mockCB = sinon.spy();
			handler( { options: { type: 'item' } }, mockCB );
			expect( mockCB.called, 'to be false' );
		} );

		it( 'doesn\'t call passed callback for properties without qualifier context', () => {
			const handler = getHookHandler( false ),
				mockCB = sinon.spy();
			handler( { options: { type: 'property' } }, mockCB );
			expect( mockCB.called, 'to be false' );
		} );
	} );
} );
