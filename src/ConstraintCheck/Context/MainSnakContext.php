<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Context;

use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Statement\Statement;

/**
 * A constraint check context for the main snak of a statement.
 *
 * @license GNU GPL v2+
 */
class MainSnakContext extends ApiV2Context {

	/**
	 * @var Statement
	 */
	private $statement;

	/**
	 * @param EntityDocument $entity
	 * @param Statement $statement
	 */
	public function __construct(
		EntityDocument $entity,
		Statement $statement
	) {
		parent::__construct( $entity, $statement->getMainSnak() );
		$this->statement = $statement;
	}

	public function getType() {
		return self::TYPE_STATEMENT;
	}

	public function getSnakRank() {
		return $this->statement->getRank();
	}

	public function getSnakStatement() {
		return $this->statement;
	}

	protected function &getMainArray( array &$container ) {
		$statementArray = &$this->getStatementArray(
			$container,
			$this->entity->getId()->getSerialization(),
			$this->statement->getPropertyId()->getSerialization(),
			$this->statement->getGuid()
		);

		if ( !array_key_exists( 'mainsnak', $statementArray ) ) {
			$statementArray['mainsnak'] = [ 'hash' => $this->statement->getMainSnak()->getHash() ];
		}
		$mainsnakArray = &$statementArray['mainsnak'];

		return $mainsnakArray;
	}

}
