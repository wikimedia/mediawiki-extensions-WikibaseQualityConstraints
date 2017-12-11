<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Api;

use Wikibase\DataModel\Entity\EntityId;

/**
 * @author Lucas Werkmeister
 * @license GNU GPL v2+
 */
interface ResultsBuilder {

	/**
	 * @param EntityId[] $entityIds
	 * @param string[] $claimIds
	 * @param string[]|null $constraintIds
	 * @return array
	 */
	public function getResults(
		array $entityIds,
		array $claimIds,
		array $constraintIds = null
	);

}
