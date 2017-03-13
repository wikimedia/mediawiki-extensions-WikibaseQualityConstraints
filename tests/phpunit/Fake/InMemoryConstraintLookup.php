<?php

namespace WikibaseQuality\ConstraintReport\Tests\Fake;

use Wikibase\DataModel\Entity\PropertyId;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintLookup;
use Wikimedia\Assert\Assert;

class InMemoryConstraintLookup implements ConstraintLookup {

	/**
	 * @var Constraint[]
	 */
	private $constraints = [];

	/**
	 * @param Constraint[] $constraints
	 */
	public function __construct( array $constraints ) {
		Assert::parameterElementType( Constraint::class, $constraints, '$constraints' );

		$this->constraints = $constraints;
	}

	/**
	 * @param PropertyId $propertyId
	 *
	 * @return Constraint[]
	 */
	public function queryConstraintsForProperty( PropertyId $propertyId ) {
		return array_filter(
			$this->constraints,
			function ( Constraint $constraint ) use ( $propertyId ) {
				return $constraint->getPropertyId()->equals( $propertyId );
			}
		);
	}

}
