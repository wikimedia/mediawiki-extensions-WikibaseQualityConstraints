<?php

namespace WikibaseQuality\ConstraintReport\Tests;

use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\Statement;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachedEntityIds;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\Metadata;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\SparqlHelper;

/**
 * @author Lucas Werkmeister
 * @license GPL-2.0-or-later
 */
trait SparqlHelperMock {

	/**
	 * @param EntityId $expectedEntityId
	 * @param Statement $expectedStatement
	 * @param (EntityId|null)[] $result
	 * @param (PropertyId|null)[]|null $separators
	 *
	 * @return SparqlHelper
	 */
	private function getSparqlHelperMockFindEntities(
		EntityId $expectedEntityId,
		Statement $expectedStatement,
		array $result,
		?array $separators = null
	) {
		$mock = $this->createMock( SparqlHelper::class );

		$args = [ $expectedEntityId, $expectedStatement ];
		if ( $separators ) {
			$args[] = $separators;
		}

		$mock->expects( $this->once() )
			->method( 'findEntitiesWithSameStatement' )
			->with( ...$args )
			->willReturn( new CachedEntityIds( $result, Metadata::blank() ) );

		return $mock;
	}

	/** @return SparqlHelper */
	private function getSparqlHelperMockFindEntitiesQualifierReference(
		EntityId $expectedEntityId,
		PropertyValueSnak $expectedSnak,
		string $expectedType,
		array $result
	) {
		$mock = $this->createMock( SparqlHelper::class );

		$mock->expects( $this->once() )
			->method( 'findEntitiesWithSameQualifierOrReference' )
			->willReturn( new CachedEntityIds( $result, Metadata::blank() ) )
			->with(
				$expectedEntityId,
				$expectedSnak,
				$expectedType,
				$expectedType === 'qualifier'
			);

		return $mock;
	}

}
