export class DesktopStatementView {

	private statementId: string;

	public constructor( statementId: string ) {
		this.statementId = statementId;
	}

	private statement() {
		return cy.get( `#${ Cypress.$.escapeSelector( this.statementId ) }` );
	}

	public mainSnakConstraintIcon() {
		return this.statement()
			.find( '.wikibase-statementview-mainsnak' )
			.find( '.wikibase-snakview-indicators .wbqc-reports-button' );
	}

}
