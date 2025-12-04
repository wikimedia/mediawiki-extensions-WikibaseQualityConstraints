import Chainable = Cypress.Chainable;
import { checkA11y } from '../checkA11y';
import { ConstraintReportPanel } from './ConstraintReportPanel';

class ConstraintReportPopup {

	public expandAdvancedIssues(): Chainable<JQuery<HTMLElement>> {
		return cy.get( '.wbqc-reports-status-bad-parameters .mw-collapsible-toggle' )
			.then( ( toggle ) => {
				if ( toggle.hasClass( 'mw-collapsible-toggle-collapsed' ) ) {
					return cy.wrap( toggle ).click();
				}
			} );
	}

	public nthPanelOfType( type: string, index: number ): Chainable<ConstraintReportPanel> {
		return cy.get( `.wbqc-reports-status-${ Cypress.$.escapeSelector( type ) } .wbqc-report` )
			.eq( index ).then( ( element: JQuery<HTMLElement> ) => new ConstraintReportPanel( element ) );
	}

	public checkA11y(): void {
		checkA11y( '.wbqc-reports-all', { rules: { 'heading-order': { enabled: false } } } );
	}

}

export default new ConstraintReportPopup();
