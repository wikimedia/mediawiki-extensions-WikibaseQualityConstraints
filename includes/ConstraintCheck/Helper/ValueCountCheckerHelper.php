<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Helper;

use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Statement\StatementList;

/**
 * Class for helper functions for value count checkers.
 *
 * @package WikibaseQuality\ConstraintReport\ConstraintCheck\Helper
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class ValueCountCheckerHelper {

	/**
	 * @var int[]
	 */
	private $propertyCount;

	/**
	 * @param StatementList $statements
	 *
	 * @return int[]
	 */
	public function getPropertyCount( StatementList $statements ) {
		if ( !isset( $this->propertyCount ) ) {
			$this->propertyCount = [];

			/** @var Statement $statement */
			foreach ( $statements as $statement ) {
				$counter = $statement->getRank() === Statement::RANK_DEPRECATED ? 0 : 1;
				if ( array_key_exists( $statement->getPropertyId()->getSerialization(), $this->propertyCount ) ) {
					$this->propertyCount[$statement->getPropertyId()->getSerialization()] += $counter;
				} else {
					$this->propertyCount[$statement->getPropertyId()->getSerialization()] = $counter;
				}
			}
		}

		return $this->propertyCount;
	}

}
