<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Context;

/**
 * @license GNU GPL v2+
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

	public function getType() {
		return Context::TYPE_STATEMENT;
	}

	public function getStatementGuid() {
		return $this->statementGuid;
	}

	public function getSnakPropertyId() {
		return $this->statementPropertyId;
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

		if ( !array_key_exists( 'mainsnak', $statementArray ) ) {
			$statementArray['mainsnak'] = [ 'hash' => $this->snakHash ];
		}
		$mainsnakArray = &$statementArray['mainsnak'];

		return $mainsnakArray;
	}

}
