interface Statement {
	id: string;
	mainsnak: {
		hash: string;
	};
}

export interface ConstraintReportExpectation {
	constraintId: string;
	constraintLink: string;
	constraintDiscussLink: string;
	constraintType: string;
	constraintTypeLabel: string;
	constraintReportHtml: string;
	constraintStatusIcon: string;
}

interface ConstraintResponse {
	responseData: object;
	expectations: ConstraintReportExpectation[];
}

interface ConstraintViolation {
	status: string;
	type: string;
	typeLabel: string;
	reportHtml: string;
}

interface ViolationData {
	status: string;
	property: string;
	constraint: {
		id: string;
		type: string;
		typeLabel: string;
		link: string;
		discussLink: string;
	};
	'message-html': string;
}

const statusToIconClass = {
	violation: '.wikibase-wbui2025-wbqc-icon--error',
	warning: '.wikibase-wbui2025-wbqc-icon--notice',
	suggestion: '.wikibase-wbui2025-wbqc-icon--flag',
	'bad-parameters': '.wikibase-wbui2025-wbqc-icon--flask',
};

export function generateCheckConstraintResponse(
	itemId: string,
	statement: Statement,
	propertyId: string,
	violations: ConstraintViolation[] = [],
): ConstraintResponse {
	if ( violations.length === 0 ) {
		violations = [ { status: 'warning', type: 'Q1', typeLabel: 'format constraint', reportHtml: 'Some <span>HTML</span>' } ];
	}
	const violationData: ViolationData[] = violations.map( ( violation ) => {
		const constraintId = `${ propertyId }$00000000-0000-0000-0000-000000000000`;
		const constraintLink = `/index.php?title=Property:${ propertyId }#${ constraintId }`;
		const constraintDiscussLink = `/index.php?title=Property_talk:${ propertyId }`;
		return {
			status: violation.status,
			property: propertyId,
			constraint: {
				id: constraintId,
				type: violation.type,
				typeLabel: violation.typeLabel,
				link: constraintLink,
				discussLink: constraintDiscussLink,
			},
			'message-html': violation.reportHtml,
		};
	} );
	return {
		responseData: {
			wbcheckconstraints: { [ itemId ]: { claims: {
				[ propertyId ]: [ {
					id: statement.id,
					mainsnak: {
						hash: statement.mainsnak.hash,
						results: violationData,
					},
				} ],
			} } },
		},
		expectations: violationData.map( ( datum ) => ( {
			constraintId: datum.constraint.id,
			constraintLink: datum.constraint.link,
			constraintDiscussLink: datum.constraint.discussLink,
			constraintType: datum.constraint.type,
			constraintTypeLabel: datum.constraint.typeLabel,
			constraintReportHtml: datum[ 'message-html' ],
			constraintStatusIcon: statusToIconClass[ datum.status ],
		} ) ),
	};
}
