<?php

namespace WikibaseQuality\ConstraintReport\Violations;

use InvalidArgumentException;
use Wikibase\DataModel\Entity\Entity;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\Lib\Store\EntityRevisionLookup;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikibaseQuality\Violations\Violation;

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

		$violationArray = array();
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
			$constraintId =
				$this->setConstraintId( $checkResult, $statement, $constraintTypeEntityId );
			$revisionId = $this->entityRevisionLookup->getLatestRevisionId( $entityId );
			$status = CheckResult::STATUS_VIOLATION;

			$constraintParameters = $checkResult->getParameters();

			$additionalInfo = array();

			if ( array_key_exists( 'constraint_status', $constraintParameters ) ) {
				$additionalInfo['constraint_status'] = $constraintParameters['constraint_status'];
			}

			$violationArray[] =
				new Violation(
					$entityId,
					$propertyId,
					$claimGuid,
					$constraintId,
					$constraintTypeEntityId,
					$revisionId,
					$status,
					$additionalInfo
				);
		}

		return $violationArray;
	}

	/**
	 * @param CheckResult|CheckResult[] $checkResultOrArray
	 *
	 * @return CheckResult[]
	 */
	private function setCheckResultArray( $checkResultOrArray ) {

		if ( $checkResultOrArray instanceof CheckResult ) {
			return array( $checkResultOrArray );
		} elseif ( is_array( $checkResultOrArray ) ) {
			return $checkResultOrArray;
		}

		throw new InvalidArgumentException;
	}

	private function setConstraintId(
		CheckResult $checkResult,
		Statement $statement,
		$constraintTypeEntityId
	) {
		$constraintId = $statement->getGuid() . $constraintTypeEntityId;
		$parameters = $checkResult->getParameters();
		if ( is_array( $parameters ) ) {
			foreach ( $parameters as $par ) {
				$constraintId .= implode( ', ', $par );
			}
		}

		return WBQ_CONSTRAINTS_ID . Violation::CONSTRAINT_ID_DELIMITER . $constraintId;
	}
}
