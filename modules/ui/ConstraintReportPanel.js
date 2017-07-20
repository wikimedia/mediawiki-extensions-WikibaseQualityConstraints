( function( wb ) {
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
	 * @cfg {jQuery} [$heading] The heading element of the panel. Should not contain the help button.
	 * @cfg {OO.ui.ButtonWidget} [helpButton] The help button for the heading.
	 * @cfg {jQuery} [$message] The message paragraph of the panel.
	 */
	wb.quality.constraints.ui.ConstraintReportPanel = function WBQCConstraintReportPanel( config ) {
		// Configuration initialization
		config = $.extend( {
			message: ''
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
		this.helpButton = config.helpButton || new OO.ui.ButtonWidget( {
			icon: 'help',
			framed: false,
			classes: [ 'wbqc-constraint-type-help' ],
			href: 'https://www.wikidata.org/wiki/Help:Property_constraints_portal/' + this.constraint.type,
			target: '_blank'
		} );
		this.message = config.message;
		this.$message = config.$message || $( '<p>' ).html( this.message );

		// Events
		// (none)

		// Initialization
		this.$element
			.addClass( 'wbqc-report' )
			.addClass( 'wbqc-report-status-' + this.status );
		this.$heading.append( this.helpButton.$element );
		this.$element.append( this.$heading );
		this.$element.append( this.$message );
	};

	/* Setup */

	OO.inheritClass( wb.quality.constraints.ui.ConstraintReportPanel, OO.ui.PanelLayout );

	/* Methods */

	// (none)

}( wikibase ) );
