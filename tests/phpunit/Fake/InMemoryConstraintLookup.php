<?php

namespace WikibaseQuality\ConstraintReport\Tests\Fake;

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
	 * @param int $numericPropertyId
	 *
	 * @return Constraint[]
	 */
	public function queryConstraintsForProperty( $numericPropertyId ) {
		return array_filter(
			$this->constraints,
			function ( Constraint $constraint ) use ( $numericPropertyId ) {
				return $constraint->getPropertyId()->getNumericId() === $numericPropertyId;
			}
		);
	}

}
