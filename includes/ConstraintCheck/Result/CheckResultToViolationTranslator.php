<?php

namespace WikidataQuality\ConstraintReport\ConstraintCheck\Result;

use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Entity\Entity;
use WikidataQuality\Result\ResultToViolationTranslator;
use WikidataQuality\Violations\Violation;


class CheckResultToViolationTranslator extends ResultToViolationTranslator {

	public function translateToViolation( Entity $entity, $checkResultOrArray ) {

		if ( $checkResultOrArray instanceof CheckResult ) {
			$checkResultArray = array ( $checkResultOrArray );
		} elseif ( is_array( $checkResultOrArray ) ) {
			$checkResultArray = $checkResultOrArray;
		} else {
			throw new InvalidArgumentException;
		}

		$violationArray = array ();
		foreach ( $checkResultArray as $checkResult ) {
			if ( $checkResult->getStatus() !== CheckResult::STATUS_VIOLATION ) {
				continue;
			}

			$statement = $checkResult->getStatement();
			$propertyId = $statement->getPropertyId();
			$claimGuid = $statement->getGuid();
			$entityId = $entity->getId();

			//TODO: Use real claimGuid
			$constraintTypeEntityId = $checkResult->getConstraintName();
			$constraintId = $claimGuid . $constraintTypeEntityId;
			$parameters = $checkResult->getParameters();
			if ( is_array( $parameters ) ) {
				foreach ( $parameters as $par ) {
					$constraintId .= implode( ', ', $par );
				}
			}
			$constraintId = md5( $constraintId );
			$revisionId = $this->getRevisionIdForEntity( $entityId );
			$status = CheckResult::STATUS_VIOLATION;

			$violationArray[ ] = new Violation( $entityId, $propertyId, $claimGuid, $constraintId, $constraintTypeEntityId, $revisionId, $status );
		}

		return $violationArray;
	}
}