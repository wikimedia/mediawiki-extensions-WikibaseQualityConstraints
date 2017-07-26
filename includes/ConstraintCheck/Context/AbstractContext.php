<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Context;

use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Snak\Snak;
use Wikibase\DataModel\Statement\Statement;

/**
 * Base implementation of some Context functions,
 * given a snak and an entity.
 */
abstract class AbstractContext implements Context {

	/**
	 * @type EntityDocument
	 */
	protected $entity;

	/**
	 * @type Snak
	 */
	protected $snak;

	public function __construct(
		EntityDocument $entity,
		Snak $snak
	) {
		$this->entity = $entity;
		$this->snak = $snak;
	}

	public function getSnak() {
		return $this->snak;
	}

	public function getEntity() {
		return $this->entity;
	}

	// unimplemented: getType

	public function getSnakRank() {
		return null;
	}

	public function getSnakStatement() {
		return null;
	}

	// unimplemented: storeCheckResultInArray

}
