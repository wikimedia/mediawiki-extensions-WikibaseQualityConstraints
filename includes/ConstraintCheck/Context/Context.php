<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Context;

use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Snak\Snak;
use Wikibase\DataModel\Statement\Statement;

/**
 * A context in which a constraint check can run.
 */
interface Context {

	/**
	 * The snak that is being checked.
	 *
	 * @return Snak
	 */
	public function getSnak();

	/**
	 * The entity that is being checked.
	 *
	 * @return EntityDocument
	 */
	public function getEntity();

	/**
	 * The type / role of the snak that is being checked within this context.
	 * Not to be confused with the snak’s own type (value/somevalue/novalue).
	 *
	 * @return string 'statement', 'qualifier', 'reference', or something else
	 */
	public function getType();

	/**
	 * The rank of the snak that is being checked.
	 * Only the main snak of a statement has a rank.
	 *
	 * @return integer|null One of the Statement::RANK_* constants
	 * if this is a 'statement' context,
	 * or null if it’s any other type of context.
	 */
	public function getSnakRank();

	/**
	 * The statement that this snak is the main snak of.
	 * Only the snak of a 'statement' context has a statement.
	 *
	 * @return Statement|null The statement if this is a 'statement' context,
	 * or null if it’s any other type of context.
	 */
	public function getSnakStatement();

	/**
	 * Store the check result serialization $result
	 * at the appropriate location for this context in $container.
	 *
	 * Mainly used in the API, where $container is part of the API response.
	 *
	 * @param array $result
	 * @param array &$container
	 */
	public function storeCheckResultInArray( array $result, array &$container );

}
