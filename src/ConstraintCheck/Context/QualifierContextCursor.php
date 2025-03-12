<?php

declare( strict_types = 1 );

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Context;

/**
 * @license GPL-2.0-or-later
 */
class QualifierContextCursor extends ApiV2ContextCursor {

	private string $entityId;

	private string $statementPropertyId;

	private string $statementGuid;

	private string $snakHash;

	private string $snakPropertyId;

	public function __construct(
		string $entityId,
		string $statementPropertyId,
		string $statementGuid,
		string $snakHash,
		string $snakPropertyId
	) {
		$this->entityId = $entityId;
		$this->statementPropertyId = $statementPropertyId;
		$this->statementGuid = $statementGuid;
		$this->snakHash = $snakHash;
		$this->snakPropertyId = $snakPropertyId;
	}

	/**
	 * @codeCoverageIgnore This method is purely declarative.
	 */
	public function getType(): string {
		return Context::TYPE_QUALIFIER;
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

	protected function &getMainArray( array &$container ): array {
		$statementArray = &$this->getStatementArray( $container );

		if ( !array_key_exists( 'qualifiers', $statementArray ) ) {
			$statementArray['qualifiers'] = [];
		}
		$qualifiersArray = &$statementArray['qualifiers'];

		$snakPropertyId = $this->getSnakPropertyId();
		if ( !array_key_exists( $snakPropertyId, $qualifiersArray ) ) {
			$qualifiersArray[$snakPropertyId] = [];
		}
		$propertyArray = &$qualifiersArray[$snakPropertyId];

		$snakHash = $this->getSnakHash();
		foreach ( $propertyArray as &$potentialQualifierArray ) {
			if ( $potentialQualifierArray['hash'] === $snakHash ) {
				$qualifierArray = &$potentialQualifierArray;
				break;
			}
		}
		if ( !isset( $qualifierArray ) ) {
			$qualifierArray = [ 'hash' => $snakHash ];
			$propertyArray[] = &$qualifierArray;
		}

		return $qualifierArray;
	}

}
