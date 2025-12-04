import { Util } from 'cypress-wikibase-api';
import { ItemViewPage } from '../support/pageObjects/ItemViewPage';
import { DesktopStatementView } from '../support/pageObjects/DesktopStatementView';
import ConstraintReportPopup from '../support/pageObjects/ConstraintReportPopup';
import { generateCheckConstraintResponse, ConstraintReportExpectation } from '../support/checkConstraintResponseGenerators';
import { ConstraintReportPanel } from '../support/pageObjects/ConstraintReportPanel';

describe( 'show constraints', () => {
	context( 'desktop view', () => {
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

		const matchPanelToExpectation = ( panel: ConstraintReportPanel, expectation: ConstraintReportExpectation ): void => {
			panel.getElement().should( 'be.visible' );
			panel.reportHeading()
				.should( 'have.text', expectation.constraintTypeLabel )
				.should( 'have.attr', 'href', expectation.constraintLink );
			panel.helpLink().should( 'have.text', 'Help' )
				.should( 'have.attr', 'href' ).and( 'contain', expectation.constraintType );
			panel.discussLink().should( 'have.text', 'Discuss' )
				.should( 'have.attr', 'href', expectation.constraintDiscussLink );
			panel.report().should( 'have.html', expectation.constraintReportHtml );
		};

		it( 'loads the item view with a simple violation', function () {
			const statement = this.item.claims[ this.propertyId ][ 0 ];
			const statementId = statement.id;
			const { responseData, expectations } = generateCheckConstraintResponse( this.itemId, statement, this.propertyId );
			cy.intercept( '**/api.php?action=wbcheckconstraints&*', ( request ) => {
				request.reply( responseData );
			} ).as( 'wbcheckconstraints' );
			const itemViewPage = new ItemViewPage( this.itemId );
			itemViewPage.open();
			cy.wait( '@wbcheckconstraints' ).its( 'request.query' ).should( 'deep.include', {
				id: this.itemId,
				status: 'violation|warning|suggestion|bad-parameters',
			} );
			const statementView = new DesktopStatementView( statementId );
			statementView.mainSnakConstraintIcon().click();
			ConstraintReportPopup.nthPanelOfType( 'warning', 0 ).then( ( panel ) => {
				matchPanelToExpectation( panel, expectations[ 0 ] );
			} );
			ConstraintReportPopup.checkA11y();
		} );

		it( 'loads the item view with an advanced violation', function () {
			const statement = this.item.claims[ this.propertyId ][ 0 ];
			const statementId = statement.id;
			const { responseData, expectations } = generateCheckConstraintResponse(
				this.itemId,
				statement,
				this.propertyId,
				[
					{ status: 'warning', type: 'Q1', typeLabel: 'format constraint', reportHtml: 'Some <span>HTML</span>' },
					{ status: 'bad-parameters', type: 'Q2', typeLabel: 'another constraint', reportHtml: 'Some <span>other HTML</span>' },
				],
			);
			cy.intercept( '**/api.php?action=wbcheckconstraints&*', ( request ) => {
				request.reply( responseData );
			} ).as( 'wbcheckconstraints' );
			const itemViewPage = new ItemViewPage( this.itemId );
			itemViewPage.open();
			cy.wait( '@wbcheckconstraints' ).its( 'request.query' ).should( 'deep.include', {
				id: this.itemId,
				status: 'violation|warning|suggestion|bad-parameters',
			} );
			const statementView = new DesktopStatementView( statementId );
			statementView.mainSnakConstraintIcon().click();
			ConstraintReportPopup.nthPanelOfType( 'warning', 0 ).then( ( panel ) => {
				matchPanelToExpectation( panel, expectations[ 0 ] );
			} );
			ConstraintReportPopup.nthPanelOfType( 'bad-parameters', 0 ).then( ( panel ) => {
				panel.getElement().should( 'not.be.visible' );
			} );
			ConstraintReportPopup.expandAdvancedIssues();
			ConstraintReportPopup.nthPanelOfType( 'bad-parameters', 0 ).then( ( panel ) => {
				matchPanelToExpectation( panel, expectations[ 1 ] );
			} );
			ConstraintReportPopup.checkA11y();
		} );
	} );
} );
