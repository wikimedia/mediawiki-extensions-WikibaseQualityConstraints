module.exports = function ( $, mw, config, isQualifierContext, getMainSnakPropertyId ) {
	'use strict';

	const MAX_LABELS_API_LIMIT = 50,
		jsonCache = {};

	function getJsonCached( url ) {
		if ( jsonCache[ url ] ) {
			return jsonCache[ url ];
		}

		const promise = $.getJSON( url );
		jsonCache[ url ] = promise;
		return promise;
	}

	function getConstraintDefinition( repoApiUrl, propertyId, constraintsPropertyId, constraintId, constraintQualifierOfPropertyId ) {
		return getJsonCached( repoApiUrl + '?action=wbgetclaims&format=json&entity=' + propertyId + '&property=' + constraintsPropertyId ).then(
			function ( d ) {
				let oneOfIds = [];
				if ( !d.claims || !d.claims[ constraintsPropertyId ] ) {
					return oneOfIds;
				}

				d.claims[ constraintsPropertyId ].forEach( function ( c ) {
					if ( c.mainsnak.datavalue.value.id === constraintId && c.qualifiers && c.qualifiers[ constraintQualifierOfPropertyId ] ) {
						oneOfIds = oneOfIds.concat( c.qualifiers[ constraintQualifierOfPropertyId ]
							.filter( function ( filterD ) {
								return filterD.datavalue;
							} )
							.map( function ( mapD ) {
								return mapD.datavalue.value.id;
							} ) );
					}
				} );

				return oneOfIds;
			} );
	}

	function createItemsFromIdsFetchLabels( repoApiUrl, language, ids, filter, articlePathPattern ) {
		return getJsonCached( repoApiUrl + '?action=wbgetentities&props=labels|descriptions&format=json&languages=' + language + '&ids=' + ids.join( '|' ) ).then( function ( ld ) {
			const data = [];
			let item = null;
			ids.forEach( function ( id ) {
				let filterTerm = '';
				item = {
					id: id,
					display: {},
					rating: 1,
					url: articlePathPattern.replace( '$1', 'Special:EntityPage/' + id )
				};

				try {
					item.display.label = ld.entities[ id ].labels[ language ];
					filterTerm += item.display.label.value;
				} catch ( _ ) {
					// no label, ignore
				}

				try {
					item.display.description = ld.entities[ id ].descriptions[ language ];
					filterTerm += item.display.description.value;
				} catch ( _ ) {
					// no description, ignore
				}

				if ( !filter( filterTerm ) && ( item.display.label || item.display.description ) ) {
					data.push( item );
				}

			} );
			return data;
		} );
	}

	function createItemsFromIds( repoApiUrl, language, ids, filter, articlePathPattern ) {
		let promise = new Promise( function ( resolve ) {
			resolve( [] );
		} );

		while ( ids.length > 0 ) {
			const currentIds = ids.splice( 0, MAX_LABELS_API_LIMIT );
			promise = promise.then( function ( itemList ) {
				return createItemsFromIdsFetchLabels(
					repoApiUrl,
					language,
					currentIds,
					filter,
					articlePathPattern
				).then( function ( items ) {
					return itemList.concat( items );
				} );
			} );
		}

		return promise;
	}

	return function ( data, addPromise ) {
		const propertyConstraintId = config.WBQualityConstraintsPropertyConstraintId,
			oneOfConstraintId = config.WBQualityConstraintsOneOfConstraintId,
			allowedQualifiersConstraintId = config.WBQualityConstraintsAllowedQualifiersConstraintId,
			constraintsPropertyId = config.WBQualityConstraintsPropertyId,
			constraintQualifierOfPropertyId = config.WBQualityConstraintsQualifierOfPropertyConstraintId,
			mainSnakPropertyId = getMainSnakPropertyId( data.element ),
			wbRepo = mw.config.get( 'wbRepo' ),
			articlePathPattern = wbRepo.url + wbRepo.articlePath,
			filterFunction = function ( term ) {
				let filter = false;
				data.term.split( ' ' ).forEach( function ( t ) {
					if ( term.toLowerCase().indexOf( t.toLowerCase() ) === -1 ) {
						filter = true;
					}
				} );
				return filter;
			};
		let constraintId = null,
			qualifierId = null;

		if ( isQualifierContext( data.element ) && data.options.type === 'property' ) {
			constraintId = allowedQualifiersConstraintId;
			qualifierId = constraintsPropertyId;
		}

		if ( !isQualifierContext( data.element ) && data.options.type === 'item' ) {
			constraintId = oneOfConstraintId;
			qualifierId = constraintQualifierOfPropertyId;
		}

		if ( !constraintId || !qualifierId ) {
			return;
		}

		addPromise( getConstraintDefinition(
			data.options.url,
			mainSnakPropertyId,
			propertyConstraintId,
			constraintId,
			qualifierId
		).then( function ( oneOfIds ) {
			return createItemsFromIds( data.options.url, data.options.language, oneOfIds, filterFunction, articlePathPattern );
		} ) );

	};
};
