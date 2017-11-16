( function( mw, wb ) {
	'use strict';

	/**
	 * A panel for a single constraint report result.
	 *
	 *     @example
	 *     var report = new wikibase.quality.constraints.ui.ConstraintReportPanel( {
	 *         status: 'compliance',
	 *         constraint: {
	 *             type: 'Q1',
	 *             typeLabel: 'my constraint',
	 *             link: 'http://example.com/my-constraint'
	 *         },
	 *         message: 'everything okay',
	 *         expanded: false,
	 *         framed: true
	 *     } );
	 *     $( 'body' ).append( report.$element );
	 *
	 * @class
	 * @extends OO.ui.PanelLayout
	 *
	 * @constructor
	 * @param {Object} config Configuration options
	 * @cfg {string} status The status of the report, e.g. 'violation' or 'compliance'.
	 * @cfg {Object} constraint The constraint of the report, as returned by the wbcheckconstraints API.
	 * @cfg {string} [message=''] The message (HTML) of the report, if present.
	 * @cfg {string[]} [ancillaryMessages=[]] Additional messages (HTML) attached to the report, if any.
	 * @cfg {jQuery} [$heading] The heading element of the panel. Should not contain the help link.
	 * @cfg {jQuery} [$helpLink] The help link for the heading.
	 * @cfg {jQuery} [$message] The message paragraph of the panel.
	 * @cfg {jQuery} [$ancillaryMessages] The container of the additional messages.
	 */
	wb.quality.constraints.ui.ConstraintReportPanel = function WBQCConstraintReportPanel( config ) {
		// Configuration initialization
		config = $.extend( {
			message: '',
			ancillaryMessages: []
		}, config );
		config.expanded = false;

		// Parent constructor
		wb.quality.constraints.ui.ConstraintReportPanel.parent.call( this, config );

		// Mixin constructors
		// (none)

		// Properties
		this.status = config.status;
		this.constraint = config.constraint;
		this.$heading = config.$heading || $( '<h4>' ).append(
			$( '<a>' )
				.text( this.constraint.typeLabel )
				.attr( 'href', this.constraint.link )
				.attr( 'target', '_blank' )
		);
		this.$helpLink = config.$helpLink || $( '<small class="wbqc-constraint-type-help">' ).append(
			$( '<a>' )
				.text( mw.message( 'wbqc-constrainttypehelp-short' ).text() )
				.attr( 'title', mw.message( 'wbqc-constrainttypehelp-long' ).text() )
				.attr( 'href', 'https://www.wikidata.org/wiki/Help:Property_constraints_portal/' + this.constraint.type )
				.attr( 'target', '_blank' )
		);
		this.message = config.message;
		this.$message = config.$message || $( '<p>' ).html( this.message );
		this.ancillaryMessages = config.ancillaryMessages;
		this.$ancillaryMessages = config.$ancillaryMessages || $( '<div class="wbqc-ancillary-messages">' ).append(
			this.ancillaryMessages.map( function( ancillaryMessage ) {
				return $( '<p>' ).html( ancillaryMessage );
			} )
		);

		// Events
		// (none)

		// Initialization
		this.$element
			.addClass( 'wbqc-report' )
			.addClass( 'wbqc-report-status-' + this.status );
		this.$heading.append( this.$helpLink );
		this.$element.append( this.$heading );
		this.$element.append( this.$message );
		this.$element.append( this.$ancillaryMessages );
	};

	/* Setup */

	OO.inheritClass( wb.quality.constraints.ui.ConstraintReportPanel, OO.ui.PanelLayout );

	/* Methods */

	// (none)

}( mediaWiki, wikibase ) );
