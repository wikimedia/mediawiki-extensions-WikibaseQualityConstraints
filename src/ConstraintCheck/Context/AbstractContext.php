<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Context;

use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Snak\Snak;

/**
 * Base implementation of some Context functions,
 * given a snak and an entity.
 *
 * @license GNU GPL v2+
 */
abstract class AbstractContext implements Context {

	/**
	 * @var EntityDocument
	 */
	protected $entity;

	/**
	 * @var Snak
	 */
	protected $snak;

	public function __construct( EntityDocument $entity, Snak $snak ) {
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
