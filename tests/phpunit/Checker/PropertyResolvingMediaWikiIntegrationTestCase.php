<?php

declare( strict_types = 1 );

namespace WikibaseQuality\ConstraintReport\Tests\Checker;

use WikibaseQuality\ConstraintReport\Tests\PropertyDataTypeLookupMockingTrait;

/**
 * @license GPL-2.0-or-later
 */
class PropertyResolvingMediaWikiIntegrationTestCase extends \MediaWikiIntegrationTestCase {

	use PropertyDataTypeLookupMockingTrait;

	protected function setUp(): void {
		parent::setUp();

		$this->mockPropertyDataTypeLookup();
	}

}
