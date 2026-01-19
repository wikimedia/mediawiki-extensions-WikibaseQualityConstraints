import Chainable = Cypress.Chainable;

export class ItemViewPage {

	public static STATEMENTS_SECTION = '.wikibase-statementgrouplistview';

	private readonly itemId: string;

	private readonly mobile: boolean;

	public constructor( itemId: string, mobile: boolean = false ) {
		this.itemId = itemId;
		this.mobile = mobile;
	}

	public open( lang: string = 'en' ): this {
		// We force tests to be in English be default, to be able to make assertions
		// about texts without introducing translation support to Cypress.
		const qs = { uselang: lang };
		if ( this.mobile ) {
			qs.useformat = 'mobile';
		}

		cy.visitTitle( { title: 'Item:' + this.itemId, qs } );
		return this;
	}

	public statementsSection(): Chainable {
		return cy.get( ItemViewPage.STATEMENTS_SECTION );
	}
}
