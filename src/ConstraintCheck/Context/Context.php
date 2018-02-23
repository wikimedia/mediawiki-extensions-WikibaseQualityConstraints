<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Context;

use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Snak\Snak;
use Wikibase\DataModel\Statement\Statement;

/**
 * A context in which a constraint check can run.
 *
 * @license GNU GPL v2+
 */
interface Context {

	/**
	 * Type of a context for the main snak of a statement.
	 * @see getType()
	 * @var string
	 */
	const TYPE_STATEMENT = 'statement';
	/**
	 * Type of a context for a qualifier of a statement.
	 * @see getType()
	 * @var string
	 */
	const TYPE_QUALIFIER = 'qualifier';
	/**
	 * Type of a context for a snak of a reference of a statement.
	 * @see getType()
	 * @var string
	 */
	const TYPE_REFERENCE = 'reference';

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
	 * @return string one of {@link self::TYPE_STATEMENT},
	 * {@link self::TYPE_QUALIFIER} or {@link self::TYPE_REFERENCE}.
	 */
	public function getType();

	/**
	 * The rank of the snak that is being checked.
	 * Only the main snak of a statement has a rank.
	 *
	 * @return integer|null One of the Statement::RANK_* constants
	 * if this is a statement context,
	 * or null if it’s any other type of context.
	 */
	public function getSnakRank();

	/**
	 * The statement that this snak is the main snak of.
	 * Only the snak of a statement context has a statement.
	 *
	 * @return Statement|null The statement if this is a statement context,
	 * or null if it’s any other type of context.
	 */
	public function getSnakStatement();

	/**
	 * The group of snaks that the snak being checked resides in.
	 * For a statement context, this is the main snaks of non-deprecated statements;
	 * for a qualifier context, the qualifiers of the same statement;
	 * and for a reference context, the snaks of the same reference.
	 *
	 * The snak being checked ({@link getSnak}) is always included,
	 * possibly more than once in the case of a statement context,
	 * since an entity can have several statements with the same main snak.
	 *
	 * @return Snak[] not a SnakList because for a statement context,
	 * the returned value might contain the same snak several times.
	 */
	public function getSnakGroup();

	/**
	 * Get the cursor that can be used to address check results for this context.
	 *
	 * @return ContextCursor
	 */
	public function getCursor();

	/**
	 * Store the check result serialization $result
	 * at the appropriate location for this context in $container.
	 *
	 * Mainly used in the API, where $container is part of the API response.
	 *
	 * If $result is null, don’t actually store it,
	 * but still populate the appropriate location for this context in $container.
	 *
	 * @param array|null $result
	 * @param array[] &$container
	 */
	public function storeCheckResultInArray( array $result = null, array &$container );

}
