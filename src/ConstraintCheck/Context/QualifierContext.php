<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Context;

use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Snak\Snak;
use Wikibase\DataModel\Statement\Statement;

/**
 * A constraint check context for a qualifier of a statement.
 *
 * @license GNU GPL v2+
 */
class QualifierContext extends ApiV2Context {

	/**
	 * @var Statement
	 */
	private $statement;

	public function __construct(
		EntityDocument $entity,
		Statement $statement,
		Snak $snak
	) {
		parent::__construct( $entity, $snak );
		$this->statement = $statement;
	}

	public function getType() {
		return self::TYPE_QUALIFIER;
	}

	protected function &getMainArray( array &$container ) {
		$statementArray = &$this->getStatementArray(
			$container,
			$this->entity->getId()->getSerialization(),
			$this->statement->getPropertyId()->getSerialization(),
			$this->statement->getGuid()
		);

		if ( !array_key_exists( 'qualifiers', $statementArray ) ) {
			$statementArray['qualifiers'] = [];
		}
		$qualifiersArray = &$statementArray['qualifiers'];

		$propertyId = $this->getSnak()->getPropertyId()->getSerialization();
		if ( !array_key_exists( $propertyId, $qualifiersArray ) ) {
			$qualifiersArray[$propertyId] = [];
		}
		$propertyArray = &$qualifiersArray[$propertyId];

		foreach ( $propertyArray as &$potentialQualifierArray ) {
			if ( $potentialQualifierArray['hash'] === $this->getSnak()->getHash() ) {
				$qualifierArray = &$potentialQualifierArray;
				break;
			}
		}
		if ( !isset( $qualifierArray ) ) {
			$qualifierArray = [ 'hash' => $this->getSnak()->getHash() ];
			$propertyArray[] = &$qualifierArray;
		}

		return $qualifierArray;
	}

}
