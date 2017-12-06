<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Api;

use Wikibase\DataModel\Entity\EntityId;

/**
 * @author Lucas Werkmeister
 * @license GNU GPL v2+
 */
interface ResultsBuilder {

	/**
	 * @param EntityId[] $entityIDs
	 * @param string[] $claimIDs
	 * @param string[]|null $constraintIDs
	 * @return array
	 */
	public function getResults(
		array $entityIDs,
		array $claimIDs,
		array $constraintIDs = null
	);

}
