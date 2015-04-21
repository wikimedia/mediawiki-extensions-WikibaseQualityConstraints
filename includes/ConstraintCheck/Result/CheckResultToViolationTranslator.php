<?php

namespace WikidataQuality\ConstraintReport\ConstraintCheck\Result;

use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Entity\Entity;
use WikidataQuality\Result\ResultToViolationTranslator;
use WikidataQuality\Violations\Violation;


class CheckResultToViolationTranslator extends ResultToViolationTranslator{

	public function translateToViolation( Entity $entity, $checkResultOrArray ) {

		if( $checkResultOrArray instanceof CheckResult ) {
			$checkResultArray = array( $checkResultOrArray );
		} elseif( is_array( $checkResultOrArray ) ) {
			$checkResultArray = $checkResultOrArray;
		} else {
			throw new InvalidArgumentException;
		}

		$violationArray = array();
		foreach( $checkResultArray as $checkResult ) {
			if( $checkResult->getStatus() !== CheckResult::STATUS_VIOLATION ){
				continue;
			}

			$statement = $checkResult->getStatement();
			$entityId = $entity->getId();

			//TODO: Use real claimGuid
			$constraintTypeEntityId = $checkResult->getConstraintName();
			$constraintClaimGuid = $statement->getGuid() . $constraintTypeEntityId;
			$parameters = $checkResult->getParameters();
			if( is_array( $parameters) ) {
				foreach( $parameters as $par ) {
					$constraintClaimGuid .= implode(', ', $par );
				}
			}
			$constraintClaimGuid = md5( $constraintClaimGuid );
			$revisionId = $this->getRevisionIdForEntity( $entityId );
			$status = CheckResult::STATUS_VIOLATION;

			$violationArray[] = new Violation( $entityId, $statement, $constraintClaimGuid, $constraintTypeEntityId, $revisionId, $status);
		}

		return $violationArray;
	}
}