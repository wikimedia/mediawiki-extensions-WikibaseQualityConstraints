<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Context;

use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Entity\StatementListProvidingEntity;
use Wikibase\DataModel\Snak\Snak;
use Wikibase\DataModel\Statement\Statement;

/**
 * A context in which a constraint check can run.
 *
 * @license GPL-2.0-or-later
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
	 * Grouping mode to include the snaks of all non-deprecated statements.
	 * @see getSnakGroup()
	 * @var string
	 */
	const GROUP_NON_DEPRECATED = 'non-deprecated';
	/**
	 * Grouping mode to include the snaks of the best-rank statement(s) per property.
	 * @see getSnakGroup()
	 * @var string
	 */
	const GROUP_BEST_RANK = 'best-rank';

	/**
	 * The snak that is being checked.
	 *
	 * @return Snak
	 */
	public function getSnak();

	/**
	 * The entity that is being checked.
	 *
	 * @return StatementListProvidingEntity
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
	 * For a statement context, this is the main snaks of other statements;
	 * for a qualifier context, the qualifiers of the same statement;
	 * and for a reference context, the snaks of the same reference.
	 *
	 * The snak being checked ({@link getSnak}) is always included,
	 * possibly more than once in the case of a statement context,
	 * since an entity can have several statements with the same main snak.
	 *
	 * For a statement context, the $groupingMode argument specifies
	 * how the rank of the other statements is considered,
	 * and statements which have different qualifiers for the properties listed in $separators
	 * than the current snak are not included in the current snak group.
	 * Both of these parameters have no effect with other types of contexts.
	 *
	 * @param string $groupingMode One of the self::GROUP_* constants.
	 * @param PropertyId[] $separators Properties that can separate different snak groups.
	 *
	 * @return Snak[] not a SnakList because for a statement context,
	 * the returned value might contain the same snak several times.
	 */
	public function getSnakGroup( $groupingMode, array $separators = [] );

	/**
	 * Get the cursor that can be used to address check results for this context.
	 *
	 * @return ContextCursor
	 */
	public function getCursor();

}
