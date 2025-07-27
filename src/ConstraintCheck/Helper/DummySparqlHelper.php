<?php

declare( strict_types = 1 );

// @phan-file-suppress PhanPluginNeverReturnMethod

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Helper;

use LogicException;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\Statement;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachedBool;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachedEntityIds;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachedQueryResults;

/**
 * A fake {@link SparqlHelper} that only serves as a default implementation
 * for {@link WikibaseQuality\ConstraintReport\ConstraintsServices ConstraintsServices}.
 *
 * TODO: SparqlHelper should be refactored so that this isn’t necessary.
 * See T196053#4514308 for details.
 *
 * @license GPL-2.0-or-later
 */
class DummySparqlHelper extends SparqlHelper {

	public function __construct() {
		// no parent::__construct() call
	}

	public function hasType( EntityId $id, array $classes ): CachedBool {
		throw new LogicException( 'methods of this class should never be called' );
	}

	public function findEntitiesWithSameStatement(
		EntityId $entityId,
		Statement $statement,
		array $separators
	): CachedEntityIds {
		throw new LogicException( 'methods of this class should never be called' );
	}

	public function findEntitiesWithSameQualifierOrReference(
		EntityId $entityId,
		PropertyValueSnak $snak,
		string $type,
		bool $ignoreDeprecatedStatements
	): CachedEntityIds {
		throw new LogicException( 'methods of this class should never be called' );
	}

	public function matchesRegularExpression( string $text, string $regex ): bool {
		throw new LogicException( 'methods of this class should never be called' );
	}

	public function runQuery( string $query, string $endpoint, bool $needsPrefixes = true ): CachedQueryResults {
		throw new LogicException( 'methods of this class should never be called' );
	}

}
