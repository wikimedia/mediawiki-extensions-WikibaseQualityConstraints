<?php
namespace WikibaseQuality\ConstraintReport;

use LogicException;

/**
 * Enum of possible roles of a value for a {@link ViolationMessage} parameter.
 *
 * @author Lucas Werkmeister
 * @license GPL-2.0-or-later
 */
class Role {

	/**
	 * Indicates that a formatted value acts as the subject of a statement.
	 *
	 * @var string
	 */
	const SUBJECT = 'subject';

	/**
	 * Indicates that a formatted value acts as the predicate of a statement.
	 *
	 * @var string
	 */
	const PREDICATE = 'predicate';

	/**
	 * Indicates that a formatted value acts as the object of a statement.
	 *
	 * @var string
	 */
	const OBJECT = 'object';

	/**
	 * Indicates that a formatted value is the property that introduced a constraint.
	 *
	 * @var string
	 */
	const CONSTRAINT_PROPERTY = 'constraint-property';

	/**
	 * Indicates that a formatted value acts as the predicate of a qualifier.
	 *
	 * @var string
	 */
	const QUALIFIER_PREDICATE = 'qualifier-predicate';

	/**
	 * Indicates that a formatted value is the property for a constraint parameter.
	 *
	 * @var string
	 */
	const CONSTRAINT_PARAMETER_PROPERTY = 'constraint-parameter-property';

	/**
	 * Indicates that a formatted value is the value for a constraint parameter.
	 *
	 * @var string
	 */
	const CONSTRAINT_PARAMETER_VALUE = 'constraint-parameter-value';

	/**
	 * Indicates that a formatted value is the item for a constraint type.
	 *
	 * @var string
	 */
	const CONSTRAINT_TYPE_ITEM = 'constraint-type-item';

	/**
	 * @codeCoverageIgnore
	 */
	private function __construct() {
		throw new LogicException( 'This class should never be instantiated.' );
	}

}
