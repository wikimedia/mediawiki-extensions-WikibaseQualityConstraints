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
	 * @var array $propertyCount
	 */
	private $propertyCount;

	/**
	 * @param StatementList $statements
	 *
	 * @return array
	 */
	public function getPropertyCount( StatementList $statements ) {
		if ( !isset( $this->propertyCount ) ) {
			$this->propertyCount = array ();
			foreach ( $statements as $statement ) {
				if ( $statement->getRank() === Statement::RANK_DEPRECATED ) {
					continue;
				}
				if ( array_key_exists( $statement->getPropertyId()->getNumericId(), $this->propertyCount ) ) {
					$this->propertyCount[ $statement->getPropertyId()->getNumericId() ]++;
				} else {
					$this->propertyCount[ $statement->getPropertyId()->getNumericId() ] = 1;
				}
			}
		}
		return $this->propertyCount;
	}
}