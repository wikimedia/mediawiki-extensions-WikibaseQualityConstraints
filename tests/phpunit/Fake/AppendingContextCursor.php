<?php

declare( strict_types = 1 );

namespace WikibaseQuality\ConstraintReport\Tests\Fake;

use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\ContextCursor;

/**
 * @license GPL-2.0-or-later
 */
class AppendingContextCursor implements ContextCursor {

	public function getType(): string {
		return 'statement';
	}

	public function getEntityId(): string {
		return 'Q1';
	}

	public function getStatementPropertyId(): string {
		return 'P1';
	}

	public function getStatementGuid(): string {
		return 'Q1$fa6a039b-d27f-4849-9039-5b364314d97b';
	}

	public function getSnakPropertyId(): string {
		return 'P1';
	}

	public function getSnakHash(): string {
		return 'a35ee6b06a0f0e78614b517e4b72029b535479c0';
	}

	public function storeCheckResultInArray( ?array $result, array &$container ): void {
		if ( $result !== null ) {
			$container[] = $result;
		}
	}

}
