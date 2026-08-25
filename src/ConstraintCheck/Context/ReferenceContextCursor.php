<?php

declare( strict_types = 1 );

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Context;

/**
 * @license GPL-2.0-or-later
 */
class ReferenceContextCursor extends ApiV2ContextCursor {

	public function __construct(
		private readonly string $entityId,
		private readonly string $statementPropertyId,
		private readonly string $statementGuid,
		private readonly string $snakHash,
		private readonly string $snakPropertyId,
		private readonly string $referenceHash,
	) {
	}

	/**
	 * @codeCoverageIgnore This method is purely declarative.
	 */
	public function getType(): string {
		return Context::TYPE_REFERENCE;
	}

	public function getEntityId(): string {
		return $this->entityId;
	}

	public function getStatementPropertyId(): string {
		return $this->statementPropertyId;
	}

	public function getStatementGuid(): string {
		return $this->statementGuid;
	}

	public function getSnakPropertyId(): string {
		return $this->snakPropertyId;
	}

	public function getSnakHash(): string {
		return $this->snakHash;
	}

	public function getReferenceHash(): string {
		return $this->referenceHash;
	}

	protected function &getMainArray( array &$container ): array {
		$statementArray = &$this->getStatementArray( $container );

		if ( !array_key_exists( 'references', $statementArray ) ) {
			$statementArray['references'] = [];
		}
		$referencesArray = &$statementArray['references'];

		$referenceHash = $this->getReferenceHash();
		foreach ( $referencesArray as &$potentialReferenceArray ) {
			if ( $potentialReferenceArray['hash'] === $referenceHash ) {
				$referenceArray = &$potentialReferenceArray;
				break;
			}
		}
		if ( !isset( $referenceArray ) ) {
			$referenceArray = [ 'hash' => $referenceHash, 'snaks' => [] ];
			$referencesArray[] = &$referenceArray;
		}

		$snaksArray = &$referenceArray['snaks'];

		$snakPropertyId = $this->getSnakPropertyId();
		if ( !array_key_exists( $snakPropertyId, $snaksArray ) ) {
			$snaksArray[$snakPropertyId] = [];
		}
		$propertyArray = &$snaksArray[$snakPropertyId];

		$snakHash = $this->getSnakHash();
		foreach ( $propertyArray as &$potentialSnakArray ) {
			if ( $potentialSnakArray['hash'] === $snakHash ) {
				$snakArray = &$potentialSnakArray;
				break;
			}
		}
		if ( !isset( $snakArray ) ) {
			$snakArray = [ 'hash' => $snakHash ];
			$propertyArray[] = &$snakArray;
		}

		return $snakArray;
	}

}
