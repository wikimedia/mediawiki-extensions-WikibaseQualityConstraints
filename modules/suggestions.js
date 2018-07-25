( function ( mw, wb, $ ) {
	'use strict';
	var MAX_LABELS_API_LIMIT = 50,
		jsonCache = {};

	function getJsonCached( url ) {
		var promise = null;

		if ( jsonCache[ url ] ) {
			return jsonCache[ url ];
		}

		promise = $.getJSON( url );
		jsonCache[ url ] = promise;
		return promise;
	}

	function getConstraintDefinition( repoApiUrl, propertyId, constraintsPropertyId, oneOfConstraintId, constraintQualifierOfPropertyId ) {
		return getJsonCached( repoApiUrl + '?action=wbgetclaims&format=json&entity=' + propertyId + '&property=' + constraintsPropertyId ).then(
				function ( d ) {
					var oneOfIds = [];
					if ( !d.claims || !d.claims[ constraintsPropertyId ] ) {
						return oneOfIds;
					}

					d.claims[ constraintsPropertyId ].forEach( function ( c ) {
						if ( c.mainsnak.datavalue.value.id === oneOfConstraintId && c.qualifiers && c.qualifiers[ constraintQualifierOfPropertyId ] ) {
							oneOfIds = oneOfIds.concat( c.qualifiers[ constraintQualifierOfPropertyId ]
								.filter( function ( d ) { return d.datavalue; } )
								.map( function ( d ) { return d.datavalue.value.id; } ) );
						}
					} );

					return oneOfIds;
				} );
	}

	function createItemsFromIdsFetchLabels( repoApiUrl, language, ids, filter ) {
		return getJsonCached( repoApiUrl + '?action=wbgetentities&props=labels|descriptions&format=json&languages=' + language + '&ids=' + ids.join( '|' ) ).then( function ( ld ) {
			var data = [],
				item = null;
			ids.forEach( function ( id ) {
				item = {
					id: id,
					label: ld.entities[ id ] && ld.entities[ id ].labels[ language ] && ld.entities[ id ].labels[ language ].value || '',
					description: ld.entities[ id ] && ld.entities[ id ].descriptions[ language ] && ld.entities[ id ].descriptions[ language ].value || ''
				};

				if ( !filter( item.label + item.description ) && ( item.label !== '' || item.description !== '' ) ) {
					data.push( item );
				}

			} );
			return data;
		} );
	}

	function createItemsFromIds( repoApiUrl, language, ids, filter ) {
		var $deferred = $.Deferred(),
			promises = [],
			itemList = [],
			addItems = function ( items ) {
				itemList = itemList.concat( items );
			};

		while ( ids.length > 0 ) {
			promises.push( createItemsFromIdsFetchLabels( repoApiUrl, language, ids.splice( 0, MAX_LABELS_API_LIMIT ), filter ).then( addItems ) );
		}

		$.when.apply( $, promises ).then( function () {
			$deferred.resolve( itemList );
		} );

		return $deferred.promise();
	}

	mw.hook( 'wikibase.entityselector.search' ).add( function ( data, addPromise ) {
		var constraintsPropertyId = mw.config.get( 'wbQualityConstraintsPropertyConstraintId' ),
			oneOfConstraintId = mw.config.get( 'wbQualityConstraintsOneOfConstraintId' ),
			constraintQualifierOrPropertyId = mw.config.get( 'wbQualityConstraintsQualifierOfPropertyConstraintId' ),
			snakview = data.element.closest( '.wikibase-snakview' ).data( 'snakview' ),
			propertyId = snakview ? snakview.propertyId() : null,
			filter = function ( term ) {
				var filter = false;
				data.term.split( ' ' ).forEach( function ( t ) {
					if ( term.toLowerCase().indexOf( t.toLowerCase() ) === -1 ) {
						filter = true;
					}
				} );
				return filter;
			};

		if ( propertyId !== constraintsPropertyId || data.options.type !== 'item' ) {
			return;
		}

		addPromise( getConstraintDefinition(
				data.options.url,
				propertyId,
				constraintsPropertyId,
				oneOfConstraintId,
				constraintQualifierOrPropertyId
		).then( function ( oneOfIds ) {
			return createItemsFromIds( data.options.url, data.options.language, oneOfIds, filter );
		} ) );

	} );

}( mediaWiki, wikibase, jQuery ) );
