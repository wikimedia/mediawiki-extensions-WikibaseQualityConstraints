<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Result;

use DomainException;
use Wikibase\DataModel\Entity\PropertyId;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\Context;

/**
 * A blank CheckResult that only holds a context, but no actual result.
 * Used for contexts that should appear in the API output
 * even if no constraints are defined for them.
 *
 * @package WikibaseQuality\ConstraintReport\ConstraintCheck\Result
 * @author Lucas Werkmeister
 * @license GNU GPL v2+
 */
class NullResult extends CheckResult {

	/**
	 * The property ID that is used for the fake constraint passed to the superclass.
	 * Since the PropertyId constructor prevents invalid property IDs like “P0”,
	 * we use the maximum permitted property ID and assume that it’s unlikely to actually exist.
	 */
	const NULL_PROPERTY_ID = 'P2147483647';

	public function __construct( Context $context ) {
		$constraint = new Constraint(
			'null',
			new PropertyId( self::NULL_PROPERTY_ID ),
			'none',
			[]
		);
		parent::__construct( $context, $constraint );
	}

	public function getConstraint() {
		throw new DomainException( 'NullResult holds no constraint' );
	}

	public function getConstraintId() {
		throw new DomainException( 'NullResult holds no constraint' );
	}

}
