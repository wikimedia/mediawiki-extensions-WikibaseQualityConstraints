export class Wbui2025StatementView {

	private readonly statementId: string;

	public constructor( statementId: string ) {
		this.statementId = statementId;
	}

	public statement(): Chainable {
		return cy.get( `#${ Cypress.$.escapeSelector( this.statementId ) }` );
	}

	public violationIndicatorIcon(): Chainable {
		return this.statement().find( '.indicators span' );
	}
}
