<?php

namespace WikidataQuality\ConstraintReport\ConstraintCheck\Result;

use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Entity\Entity;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\Lib\Store\EntityRevisionLookup;
use WikidataQuality\Result\ResultToViolationTranslator;
use WikidataQuality\Violations\Violation;
use Doctrine\Instantiator\Exception\InvalidArgumentException;


class CheckResultToViolationTranslator {

    /**
     * @var EntityRevisionLookup
     */
    private $entityRevisionLookup;

    /**
     * @param EntityRevisionLookup $entityRevisionLookup
     */
    public function __construct( EntityRevisionLookup $entityRevisionLookup ) {
        $this->entityRevisionLookup = $entityRevisionLookup;
    }

	public function translateToViolation( Entity $entity, $checkResultOrArray ) {

	    $checkResultArray = $this->setCheckResultArray( $checkResultOrArray );

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
            $constraintId = $this->setConstraintId( $checkResult, $statement, $constraintTypeEntityId );
			$revisionId = $this->entityRevisionLookup->getLatestRevisionId( $entityId );
			$status = CheckResult::STATUS_VIOLATION;

			$violationArray[ ] = new Violation( $entityId, $propertyId, $claimGuid, $constraintId, $constraintTypeEntityId, $revisionId, $status );
		}

		return $violationArray;
	}

    private function setCheckResultArray( $checkResultOrArray ){

        if ( $checkResultOrArray instanceof CheckResult ) {
            return array ( $checkResultOrArray );
        } elseif ( is_array( $checkResultOrArray ) ) {
            return $checkResultOrArray;
        }

        throw new InvalidArgumentException;
    }

    private function setConstraintId( CheckResult $checkResult, Statement $statement, $constraintTypeEntityId  ){
        $constraintId = $statement->getGuid() . $constraintTypeEntityId;
        $parameters = $checkResult->getParameters();
        if ( is_array( $parameters ) ) {
            foreach ( $parameters as $par ) {
                $constraintId .= implode( ', ', $par );
            }
        }

        return md5( $constraintId );
    }
}