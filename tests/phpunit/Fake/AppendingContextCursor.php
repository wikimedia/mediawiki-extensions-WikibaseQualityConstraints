<?php

namespace WikibaseQuality\ConstraintReport\Tests\Fake;

use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\ContextCursor;

/**
 * @license GPL-2.0-or-later
 */
class AppendingContextCursor implements ContextCursor {

	/** @inheritDoc */
	public function getType() {
		return 'statement';
	}

	/** @inheritDoc */
	public function getEntityId() {
		return 'Q1';
	}

	/** @inheritDoc */
	public function getStatementPropertyId() {
		return 'P1';
	}

	/** @inheritDoc */
	public function getStatementGuid() {
		return 'Q1$fa6a039b-d27f-4849-9039-5b364314d97b';
	}

	/** @inheritDoc */
	public function getSnakPropertyId() {
		return 'P1';
	}

	/** @inheritDoc */
	public function getSnakHash() {
		return 'a35ee6b06a0f0e78614b517e4b72029b535479c0';
	}

	/**
	 * @param ?array $result
	 * @param array[] &$container
	 */
	public function storeCheckResultInArray( ?array $result, array &$container ) {
		if ( $result !== null ) {
			$container[] = $result;
		}
	}

}
