( function( OO, wb ) {
	'use strict';

	/**
	 * A panel for a group of constraint report results.
	 *
	 *     @example
	 *     var group = new wikibase.quality.constraints.ui.ConstraintReportGroup( {
	 *         items: [
	 *             new wikibase.quality.constraints.ui.ConstraintReportPanel( {
	 *                 status: 'compliance',
	 *                 constraint: {
	 *                     type: 'Q1',
	 *                     typeLabel: 'my constraint',
	 *                     link: 'http://example.com/my-constraint'
	 *                 },
	 *                 message: 'everything okay'
	 *             } )
	 *         ],
	 *         heading: 'reports that are fine',
	 *         collapsed: true,
	 *         expanded: false,
	 *         framed: true
	 *     } );
	 *     $( 'body' ).append( group.$element );
	 *
	 * @class
	 * @extends OO.ui.StackLayout
	 *
	 * @constructor
	 * @param {Object} config Configuration options
	 * @cfg {wikibase.quality.constraints.ui.ConstraintReportPanel[]} items The individual constraint report results.
	 * @cfg {string} [heading] The heading of the group.
	 * @cfg {string} [subheading] The subheading of the group. Unlike the heading, it is part of the collapsed content if `collapsible` is `true`.
	 * @cfg {boolean} [collapsible=false] Whether the group can be collapsed or not. If `true`, a `heading` is recommended.
	 * @cfg {boolean} [collapsed=false] Whether the group is collapsed by default or not. (If `true`, implies `collapsible=true` as well.)
	 * @cfg {string} [status] The status of all reports in this group. Unused by this class, but assigned by `ConstraintReportList.fromPanels`.
	 */
	wb.quality.constraints.ui.ConstraintReportGroup = function WBQCConstraintReportGroup( config ) {

		var heading = config.heading || null,
			collapsible = config.collapsible || config.collapsed || false,
			collapsed = config.collapsed || false,
			subheading = config.subheading || null,
			status = config.status || null,
			$subheadingContainer;

		// Configuration initialization
		config.continuous = true;
		config.classes = OO.simpleArrayUnion(
			config.classes || [],
			[ 'wbqc-reports' ]
		);

		// Parent constructor
		wb.quality.constraints.ui.ConstraintReportGroup.parent.call( this, config );

		// Mixin constructors
		// (none)

		// Properties
		this.heading = heading;
		this.collapsible = collapsible;
		this.collapsed = collapsed;
		this.status = status;

		// Initialization
		if ( collapsible ) {
			this.$element.makeCollapsible( { collapsed: collapsed } );
		}
		if ( heading !== null ) {
			this.$element.prepend( $( '<strong>' ).text( heading ) );
		}
		if ( subheading !== null ) {
			$subheadingContainer = collapsible ?
				this.$element.find( '.mw-collapsible-content' ) :
				this.$element;
			$subheadingContainer.prepend(
				$( '<p>' ).append( $( '<small>' ).text( subheading ) )
			);
		}
		if ( collapsible && heading === null && subheading === null ) {
			// we need a spacer before the toggler,
			// otherwise it's not clickable because it's on the same height as the items and behind them
			this.$element.prepend( $( '<span>&nbsp;</span>' ) );
		}
	};

	/* Setup */

	OO.inheritClass( wb.quality.constraints.ui.ConstraintReportGroup, OO.ui.StackLayout );

	/* Methods */

	// (none)

}( OO, wikibase ) );
