( function( $ ) {
	'use strict';

	$( document ).ready( function() {
		$( '.wbqc-expandable-content-indicator' ).on( 'click', function() {
			$( this ).closest( 'td' ).find( '.wbqc-expandable-content' ).slideToggle( 'fast' );
		} );
		$( '.wbqc-indicator' ).hover(
			function() {
				$( this ).parent().find( '.wbqc-tooltip' ).show();
			},
			function() {
				$( this ).parent().find( '.wbqc-tooltip' ).css( 'display', 'none' );
			}
		);
	} );

	$( document ).click( function( e ) {
		$( '.wbqc-tooltip' ).css( 'display', 'none' );
		if( $( e.target ).attr( 'class' ) === 'wbqc-indicator' ) {
			var tooltip = $( e.target ).parent().find( '.wbqc-tooltip' );
			if( tooltip.css( 'display' ) === 'none' ) {
				tooltip.show();
			}
		}
	} );
} )( jQuery );
