<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Context;

/**
 * @license GPL-2.0-or-later
 */
class ReferenceContextCursor extends ApiV2ContextCursor {

	/**
	 * @var string
	 */
	private $entityId;

	/**
	 * @var string
	 */
	private $statementPropertyId;

	/**
	 * @var string
	 */
	private $statementGuid;

	/**
	 * @var string
	 */
	private $snakHash;

	/**
	 * @var string
	 */
	private $snakPropertyId;

	/**
	 * @var string
	 */
	private $referenceHash;

	/**
	 * @param string $entityId
	 * @param string $statementPropertyId
	 * @param string $statementGuid
	 * @param string $snakHash
	 * @param string $snakPropertyId
	 * @param string $referenceHash
	 */
	public function __construct(
		$entityId,
		$statementPropertyId,
		$statementGuid,
		$snakHash,
		$snakPropertyId,
		$referenceHash
	) {
		$this->entityId = $entityId;
		$this->statementPropertyId = $statementPropertyId;
		$this->statementGuid = $statementGuid;
		$this->snakHash = $snakHash;
		$this->snakPropertyId = $snakPropertyId;
		$this->referenceHash = $referenceHash;
	}

	public function getType() {
		return Context::TYPE_REFERENCE;
	}

	public function getStatementGuid() {
		return $this->statementGuid;
	}

	public function getSnakPropertyId() {
		return $this->snakPropertyId;
	}

	public function getSnakHash() {
		return $this->snakHash;
	}

	protected function &getMainArray( array &$container ) {
		$statementArray = &$this->getStatementArray(
			$container,
			$this->entityId,
			$this->statementPropertyId,
			$this->statementGuid
		);

		if ( !array_key_exists( 'references', $statementArray ) ) {
			$statementArray['references'] = [];
		}
		$referencesArray = &$statementArray['references'];

		foreach ( $referencesArray as &$potentialReferenceArray ) {
			if ( $potentialReferenceArray['hash'] === $this->referenceHash ) {
				$referenceArray = &$potentialReferenceArray;
				break;
			}
		}
		if ( !isset( $referenceArray ) ) {
			$referenceArray = [ 'hash' => $this->referenceHash, 'snaks' => [] ];
			$referencesArray[] = &$referenceArray;
		}

		$snaksArray = &$referenceArray['snaks'];

		if ( !array_key_exists( $this->snakPropertyId, $snaksArray ) ) {
			$snaksArray[$this->snakPropertyId] = [];
		}
		$propertyArray = &$snaksArray[$this->snakPropertyId];

		foreach ( $propertyArray as &$potentialSnakArray ) {
			if ( $potentialSnakArray['hash'] === $this->snakHash ) {
				$snakArray = &$potentialSnakArray;
				break;
			}
		}
		if ( !isset( $snakArray ) ) {
			$snakArray = [ 'hash' => $this->snakHash ];
			$propertyArray[] = &$snakArray;
		}

		return $snakArray;
	}

}
