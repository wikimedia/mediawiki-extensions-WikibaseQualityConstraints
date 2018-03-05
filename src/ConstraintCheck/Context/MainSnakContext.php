<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Context;

use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Statement\StatementList;
use Wikibase\DataModel\Statement\StatementListProvider;
use Wikimedia\Assert\Assert;

/**
 * A constraint check context for the main snak of a statement.
 *
 * @license GPL-2.0-or-later
 */
class MainSnakContext extends AbstractContext {

	/**
	 * @var Statement
	 */
	private $statement;

	public function __construct( EntityDocument $entity, Statement $statement ) {
		Assert::parameterType( StatementListProvider::class, $entity, '$entity' );
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

	public function getSnakGroup() {
		/** @var StatementList $statements */
		$statements = $this->entity->getStatements();
		return $statements
			->getByRank( [ Statement::RANK_NORMAL, Statement::RANK_PREFERRED ] )
			->getMainSnaks();
	}

	public function getCursor() {
		return new MainSnakContextCursor(
			$this->entity->getId()->getSerialization(),
			$this->statement->getPropertyId()->getSerialization(),
			$this->statement->getGuid(),
			$this->statement->getMainSnak()->getHash()
		);
	}

}
