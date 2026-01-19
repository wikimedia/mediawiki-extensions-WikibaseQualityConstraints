import Chainable = Cypress.Chainable;

export class Wbui2025ConstraintReportPopover {

	private SELECTORS = {
		POPOVER: '.wikibase-wbui2025-indicator-popover',
		HEADER: '.cdx-popover__header',
		BODY: '.cdx-popover__body',
		FOOTER: '.cdx-popover__footer',
		LINK_CONTAINER: '.wikibase-wbui2025-wbqc-constraint-links',
		REPORT_HTML: '.wikibase-wbui2025-wbqc-constraint-content',
		REPORT_LABEL: '.wikibase-wbui2025-wbqc-constraint-header > a',
		MUTLISTEP_NAVIGATION_BUTTONS: '.wikibase-wbui2025-indicator-popover-multistep-navigation button',
	};

	public getElement(): Chainable {
		return cy.get( this.SELECTORS.POPOVER );
	}

	public header(): Chainable {
		return this.getElement().find( this.SELECTORS.HEADER );
	}

	public body(): Chainable {
		return this.getElement().find( this.SELECTORS.BODY );
	}

	public footer(): Chainable {
		return this.getElement().find( this.SELECTORS.FOOTER );
	}

	public helpLink(): Chainable {
		return this.getElement()
			.find( this.SELECTORS.LINK_CONTAINER )
			.find( 'a' )
			.eq( 0 );
	}

	public discussLink(): Chainable {
		return this.getElement()
			.find( this.SELECTORS.LINK_CONTAINER )
			.find( 'a' )
			.eq( 1 );
	}

	public reportHtml(): Chainable {
		return this.getElement()
			.find( this.SELECTORS.REPORT_HTML );
	}

	public reportLabel(): Chainable {
		return this.getElement()
			.find( this.SELECTORS.REPORT_LABEL );
	}

	public nextButton(): Chainable {
		return this.getElement()
			.find( this.SELECTORS.MUTLISTEP_NAVIGATION_BUTTONS ).eq( 1 );
	}
}
