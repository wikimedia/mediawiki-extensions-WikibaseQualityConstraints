<?php

declare( strict_types = 1 );

namespace WikibaseQuality\ConstraintReport\Tests;

use MediaWiki\Tests\ExtensionJsonTestBase;

/**
 * @coversNothing
 *
 * @group WikibaseQualityConstraints
 *
 * @license GPL-2.0-or-later
 */
class WikibaseQualityConstraintsExtensionJsonTest extends ExtensionJsonTestBase {

	protected string $extensionJsonPath = __DIR__ . '/../../extension.json';

}
