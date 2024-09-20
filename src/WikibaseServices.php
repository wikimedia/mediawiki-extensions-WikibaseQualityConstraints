<?php

declare( strict_types = 1 );

namespace WikibaseQuality\ConstraintReport;

use MediaWiki\MediaWikiServices;
use Wikibase\DataModel\Services\Lookup\EntityLookup;

/**
 * A few Wikibase-related services.
 *
 * Originally, this class and the associated ServiceWiring-Wikibase.php
 * contained some services that Wikibase did not register in the service container;
 * we needed them to be in the service container for tests,
 * so we added them there under WikibaseQualityConstraints-specific names.
 * Subsequently, the service migration in Wikibase moved its services into the service container,
 * which made this class mostly obsolete.
 * It survives only for a few services that are not quite available from Wikibase directly.
 *
 * @license GPL-2.0-or-later
 */
class WikibaseServices {

	public const ENTITY_LOOKUP = 'WBQC_EntityLookup';
	public const ENTITY_LOOKUP_WITHOUT_CACHE = 'WBQC_EntityLookupWithoutCache';

	private static function getService( ?MediaWikiServices $services, $name ) {
		$services ??= MediaWikiServices::getInstance();
		return $services->getService( $name );
	}

	/**
	 * An EntityLookup.
	 *
	 * Unlike {@link WikibaseRepo::getEntityLookup()},
	 * this lookup ignores exceptions (such as unresolved redirects, T93273),
	 * as it is more convenient to treat them all as missing entities in WBQC.
	 */
	public static function getEntityLookup( MediaWikiServices $services = null ): EntityLookup {
		return self::getService( $services, self::ENTITY_LOOKUP );
	}

	/**
	 * An EntityLookup that does not store entities in the cache.
	 *
	 * This was introduced because the many entities loaded by some {@link SymmetricChecker} checks
	 * were exceeding the request memory limit when they were all added to the cache (T227450).
	 * Also, like {@link self::getEntityLookup()}, this lookup ignores exceptions.
	 */
	public static function getEntityLookupWithoutCache( MediaWikiServices $services = null ): EntityLookup {
		return self::getService( $services, self::ENTITY_LOOKUP_WITHOUT_CACHE );
	}

}
