<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Context;

/**
 * @license GPL-2.0-or-later
 */
class MainSnakContextCursor extends ApiV2ContextCursor {

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
	 * @param string $entityId
	 * @param string $statementPropertyId
	 * @param string $statementGuid
	 * @param string $snakHash
	 */
	public function __construct(
		$entityId,
		$statementPropertyId,
		$statementGuid,
		$snakHash
	) {
		$this->entityId = $entityId;
		$this->statementPropertyId = $statementPropertyId;
		$this->statementGuid = $statementGuid;
		$this->snakHash = $snakHash;
	}

	/**
	 * @codeCoverageIgnore This method is purely declarative.
	 * @inheritDoc
	 */
	public function getType() {
		return Context::TYPE_STATEMENT;
	}

	/** @inheritDoc */
	public function getEntityId() {
		return $this->entityId;
	}

	/** @inheritDoc */
	public function getStatementPropertyId() {
		return $this->statementPropertyId;
	}

	/** @inheritDoc */
	public function getStatementGuid() {
		return $this->statementGuid;
	}

	/** @inheritDoc */
	public function getSnakPropertyId() {
		return $this->statementPropertyId;
	}

	/** @inheritDoc */
	public function getSnakHash() {
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
