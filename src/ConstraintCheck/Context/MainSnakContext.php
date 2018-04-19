<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Context;

use LogicException;
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

	public function getSnakGroup( $groupingMode ) {
		/** @var StatementList $statements */
		$statements = $this->entity->getStatements();
		switch ( $groupingMode ) {
			case Context::GROUP_NON_DEPRECATED:
				$statements = $statements->getByRank( [
					Statement::RANK_NORMAL,
					Statement::RANK_PREFERRED,
				] );
				break;
			case Context::GROUP_BEST_RANK:
				$statements = $this->getBestStatementsPerPropertyId( $statements );
				break;
			default:
				throw new LogicException( 'Unknown $groupingMode ' . $groupingMode );
		}
		return $statements->getMainSnaks();
	}

	private function getBestStatementsPerPropertyId( StatementList $statements ) {
		$allBestStatements = new StatementList();
		foreach ( $statements->getPropertyIds() as $propertyId ) {
			$bestStatements = $statements->getByPropertyId( $propertyId )
				->getBestStatements();
			foreach ( $bestStatements as $bestStatement ) {
				$allBestStatements->addStatement( $bestStatement );
			}
		}
		return $allBestStatements;
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
