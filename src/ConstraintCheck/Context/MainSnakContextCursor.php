<?php

declare( strict_types = 1 );

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Context;

/**
 * @license GPL-2.0-or-later
 */
class MainSnakContextCursor extends ApiV2ContextCursor {

	private string $entityId;

	private string $statementPropertyId;

	private string $statementGuid;

	private string $snakHash;

	public function __construct(
		string $entityId,
		string $statementPropertyId,
		string $statementGuid,
		string $snakHash
	) {
		$this->entityId = $entityId;
		$this->statementPropertyId = $statementPropertyId;
		$this->statementGuid = $statementGuid;
		$this->snakHash = $snakHash;
	}

	/**
	 * @codeCoverageIgnore This method is purely declarative.
	 */
	public function getType(): string {
		return Context::TYPE_STATEMENT;
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
		return $this->statementPropertyId;
	}

	public function getSnakHash(): string {
		return $this->snakHash;
	}

	protected function &getMainArray( array &$container ): array {
		$statementArray = &$this->getStatementArray( $container );

		if ( !array_key_exists( 'mainsnak', $statementArray ) ) {
			$snakHash = $this->getSnakHash();
			$statementArray['mainsnak'] = [ 'hash' => $snakHash ];
		}
		$mainsnakArray = &$statementArray['mainsnak'];

		return $mainsnakArray;
	}

}
