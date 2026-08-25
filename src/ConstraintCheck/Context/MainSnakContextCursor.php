<?php

declare( strict_types = 1 );

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Context;

/**
 * @license GPL-2.0-or-later
 */
class MainSnakContextCursor extends ApiV2ContextCursor {

	public function __construct(
		private readonly string $entityId,
		private readonly string $statementPropertyId,
		private readonly string $statementGuid,
		private readonly string $snakHash,
	) {
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
