import Chainable = Cypress.Chainable;

export class ConstraintReportPanel {

	private SELECTORS = {
		HEADING: '.wbqc-report-heading > a',
		HELP: '.wbqc-constraint-type-help',
		DISCUSS: '.wbqc-constraint-discuss',
		REPORT: 'p',
	};

	public constructor( element: JQuery<HTMLElement> ) {
		this.element = element;
	}

	public getElement(): Chainable<JQuery<HTMLElement>> {
		return cy.wrap( this.element );
	}

	public reportHeading(): Chainable<JQuery<HTMLElement>> {
		return this.getElement().find( this.SELECTORS.HEADING );
	}

	public helpLink(): Chainable<JQuery<HTMLElement>> {
		return this.getElement().find( this.SELECTORS.HELP );
	}

	public discussLink(): Chainable<JQuery<HTMLElement>> {
		return this.getElement().find( this.SELECTORS.DISCUSS );
	}

	public report(): Chainable<JQuery<HTMLElement>> {
		return this.getElement().find( this.SELECTORS.REPORT );
	}

}
