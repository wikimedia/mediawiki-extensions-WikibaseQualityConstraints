<?php

namespace WikibaseQuality\ConstraintReport\Tests\Fake;

use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\ContextCursor;

/**
 * @license GNU GPL v2+
 */
class AppendingContextCursor implements ContextCursor {

	/**
	 * @param array|null $result
	 * @param array[] &$container
	 */
	public function storeCheckResultInArray( array $result = null, array &$container ) {
		if ( $result !== null ) {
			$container[] = $result;
		}
	}

}
