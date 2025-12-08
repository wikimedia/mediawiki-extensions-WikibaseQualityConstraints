import Chainable = Cypress.Chainable;

export class ItemViewPage {

	public static STATEMENTS_SECTION = '.wikibase-statementgrouplistview';

	private itemId: string;

	public constructor( itemId: string ) {
		this.itemId = itemId;
	}

	public open( lang: string = 'en' ): this {
		// We force tests to be in English be default, to be able to make assertions
		// about texts without introducing translation support to Cypress.
		cy.visitTitle( { title: 'Item:' + this.itemId, qs: { uselang: lang } } );
		return this;
	}

	public statementsSection(): Chainable {
		return cy.get( ItemViewPage.STATEMENTS_SECTION );
	}
}
