<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Context;

use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Statement\Statement;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;

/**
 * A constraint check context for the main snak of a statement.
 *
 * The result format used by storeCheckResultInArray() is only suitable
 * if no other kinds of contexts are to be stored in the same container.
 */
class StatementContext extends AbstractContext {

	/**
	 * @type Statement
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

	/**
	 * The $container is keyed by entity ID, then by property ID,
	 * then by claim ID, and then contains the $result in a list:
	 * { "Q1": { "P1": { "Q1$1a2b...": [ { "status": "compliance", ... } ] } } }
	 *
	 * @param array $result
	 * @param array &$container
	 */
	public function storeCheckResultInArray( array $result, array &$container ) {
		$entityId = $this->entity->getId()->getSerialization();
		$propertyId = $this->statement->getPropertyId()->getSerialization();
		$statementId = $this->statement->getGuid();

		$container[$entityId][$propertyId][$statementId][] = $result;
	}

}
