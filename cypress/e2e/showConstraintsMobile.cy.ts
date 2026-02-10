import { Util } from 'cypress-wikibase-api';
import { ItemViewPage } from '../support/pageObjects/ItemViewPage';
import { Wbui2025StatementView } from '../support/pageObjects/Wbui2025StatementView';
import { Wbui2025ConstraintReportPopover } from '../support/pageObjects/Wbui2025ConstraintReportPopover';
import { generateCheckConstraintResponse, ConstraintReportExpectation } from '../support/checkConstraintResponseGenerators';

describe( 'show constraints', () => {
	context( 'wbui2025 mobile view', () => {
		beforeEach( () => {
			cy.task( 'MwApi:GetOrCreatePropertyIdByDataType', { datatype: 'string' } )
				.as( 'propertyId' )
				.then( ( propertyId: string ) => cy.task( 'MwApi:CreateItem', {
					label: Util.getTestString( 'item' ),
					data: {
						claims: {
							[ propertyId ]: [ {
								type: 'statement',
								rank: 'normal',
								mainsnak: {
									snaktype: 'value',
									property: propertyId,
									datavalue: {
										type: 'string',
										value: 'test string',
									},
								},
							} ],
						},
					},
				} ) )
				.as( 'itemId' )
				.then( ( itemId ) => {
					cy.task( 'MwApi:GetEntityData', { entityId: itemId } )
						.as( 'item' );
				} );
		} );

		const matchPopoverToExpectation = ( popover: Wbui2025ConstraintReportPopover, expectation: ConstraintReportExpectation ): void => {
			popover.getElement().should( 'be.visible' );
			popover.getElement().find( expectation.constraintStatusIcon );
			popover.reportLabel()
				.should( 'contain.text', expectation.constraintTypeLabel )
				.should( 'have.attr', 'href', expectation.constraintLink );
			popover.helpLink().should( 'have.text', 'Help' )
				.should( 'have.attr', 'href' ).and( 'contain', expectation.constraintType );
			popover.discussLink().should( 'have.text', 'Discuss' )
				.should( 'have.attr', 'href', expectation.constraintDiscussLink );
			popover.reportHtml().should( 'have.html', expectation.constraintReportHtml );
		};

		it( 'adds an indicator, and a popover containing all violations', function () {
			const statement = this.item.claims[ this.propertyId ][ 0 ];
			const { responseData, expectations } = generateCheckConstraintResponse(
				this.itemId,
				statement,
				this.propertyId,
				[
					{ status: 'bad-parameters', type: 'Q2', typeLabel: 'parameters problem', reportHtml: '<span>advanced problem</span>' },
					{ status: 'warning', type: 'Q1', typeLabel: 'format constraint', reportHtml: 'Some <span>HTML</span>' },
				],
			);
			cy.intercept( '**/api.php?action=wbcheckconstraints&*', ( request ) => {
				request.reply( responseData );
			} ).as( 'wbcheckconstraints' );

			const itemViewPage = new ItemViewPage( this.itemId, true );
			itemViewPage.open();
			cy.wait( '@wbcheckconstraints' ).its( 'request.query' ).should( 'deep.include', {
				id: this.itemId,
				status: 'violation|warning|suggestion|bad-parameters',
			} );

			const wbui2025StatementView = new Wbui2025StatementView( statement.id );
			wbui2025StatementView.violationIndicatorIcon().click();

			const wbui2025Popover = new Wbui2025ConstraintReportPopover();
			matchPopoverToExpectation( wbui2025Popover, expectations[ 1 ] );

			wbui2025Popover.nextButton().click();
			matchPopoverToExpectation( wbui2025Popover, expectations[ 0 ] );
		} );
	} );
} );
