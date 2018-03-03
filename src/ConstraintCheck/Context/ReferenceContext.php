<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Context;

use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Reference;
use Wikibase\DataModel\Snak\Snak;
use Wikibase\DataModel\Statement\Statement;

/**
 * A constraint check context for a snak of a reference of a statement.
 *
 * @license GPL-2.0-or-later
 */
class ReferenceContext extends ApiV2Context {

	/**
	 * @var Statement
	 */
	private $statement;

	/**
	 * @var Reference
	 */
	private $reference;

	public function __construct(
		EntityDocument $entity,
		Statement $statement,
		Reference $reference,
		Snak $snak
	) {
		parent::__construct( $entity, $snak );
		$this->statement = $statement;
		$this->reference = $reference;
	}

	public function getType() {
		return self::TYPE_REFERENCE;
	}

	public function getSnakGroup() {
		$snaks = $this->reference->getSnaks();
		return array_values( $snaks->getArrayCopy() );
	}

	public function getCursor() {
		return new ReferenceContextCursor(
			$this->entity->getId()->getSerialization(),
			$this->statement->getPropertyId()->getSerialization(),
			$this->statement->getGuid(),
			$this->snak->getHash(),
			$this->snak->getPropertyId()->getSerialization(),
			$this->reference->getHash()
		);
	}

	protected function &getMainArray( array &$container ) {
		$statementArray = &$this->getStatementArray(
			$container,
			$this->entity->getId()->getSerialization(),
			$this->statement->getPropertyId()->getSerialization(),
			$this->statement->getGuid()
		);

		if ( !array_key_exists( 'references', $statementArray ) ) {
			$statementArray['references'] = [];
		}
		$referencesArray = &$statementArray['references'];

		foreach ( $referencesArray as &$potentialReferenceArray ) {
			if ( $potentialReferenceArray['hash'] === $this->reference->getHash() ) {
				$referenceArray = &$potentialReferenceArray;
				break;
			}
		}
		if ( !isset( $referenceArray ) ) {
			$referenceArray = [ 'hash' => $this->reference->getHash(), 'snaks' => [] ];
			$referencesArray[] = &$referenceArray;
		}

		$snaksArray = &$referenceArray['snaks'];

		$propertyId = $this->getSnak()->getPropertyId()->getSerialization();
		if ( !array_key_exists( $propertyId, $snaksArray ) ) {
			$snaksArray[$propertyId] = [];
		}
		$propertyArray = &$snaksArray[$propertyId];

		foreach ( $propertyArray as &$potentialSnakArray ) {
			if ( $potentialSnakArray['hash'] === $this->getSnak()->getHash() ) {
				$snakArray = &$potentialSnakArray;
				break;
			}
		}
		if ( !isset( $snakArray ) ) {
			$snakArray = [ 'hash' => $this->getSnak()->getHash() ];
			$propertyArray[] = &$snakArray;
		}

		return $snakArray;
	}

}
