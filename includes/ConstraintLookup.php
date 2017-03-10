<?php

namespace WikibaseQuality\ConstraintReport;

/**
 * @license GNU GPL v2+
 */
interface ConstraintLookup {
	/**
	 * @param int $numericPropertyId
	 *
	 * @return Constraint[]
	 */
	public function queryConstraintsForProperty( $numericPropertyId );
}
