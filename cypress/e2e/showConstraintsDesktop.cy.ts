import { Util } from 'cypress-wikibase-api';

import { checkA11y } from '../support/checkA11y';
import { ItemViewPage } from '../support/pageObjects/ItemViewPage';

describe( 'show constraints', () => {
	context( 'desktop view', () => {
		it( 'loads the item view', () => {
			cy.task( 'MwApi:CreateItem', { label: Util.getTestString( 'item' ) } )
				.then( ( itemId: string ) => {
					const itemViewPage = new ItemViewPage( itemId );
					itemViewPage.open().statementsSection();
					checkA11y( ItemViewPage.STATEMENTS_SECTION );
				} );
		} );
	} );
} );
