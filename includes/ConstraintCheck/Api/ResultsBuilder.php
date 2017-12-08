<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Api;

use Wikibase\DataModel\Entity\EntityId;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachedCheckConstraintsResponse;

/**
 * @author Lucas Werkmeister
 * @license GNU GPL v2+
 */
interface ResultsBuilder {

	/**
	 * @param EntityId[] $entityIds
	 * @param string[] $claimIds
	 * @param string[]|null $constraintIds
	 * @return CachedCheckConstraintsResponse
	 */
	public function getResults(
		array $entityIds,
		array $claimIds,
		array $constraintIds = null
	);

}
