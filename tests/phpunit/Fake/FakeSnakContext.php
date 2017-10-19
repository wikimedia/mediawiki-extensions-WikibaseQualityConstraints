<?php

namespace WikibaseQuality\ConstraintReport\Tests\Fake;

use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Snak\Snak;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\AbstractContext;

/**
 * A constraint check context for a snak not connected to any statement.
 * This is a minimal Context implementation for tests.
 */
class FakeSnakContext extends AbstractContext {

	/**
	 * @param Snak $snak
	 * @param EntityDocument|null $entity defaults to a new Q1 item
	 */
	public function __construct(
		Snak $snak,
		EntityDocument $entity = null
	) {
		parent::__construct(
			$entity ?: new Item( new ItemId( 'Q1' ) ),
			$snak
		);
	}

	public function getType() {
		return 'statement';
	}

	public function storeCheckResultInArray( $result, array &$container ) {
		if ( $result !== null ) {
			$container[] = $result;
		}
	}

}
