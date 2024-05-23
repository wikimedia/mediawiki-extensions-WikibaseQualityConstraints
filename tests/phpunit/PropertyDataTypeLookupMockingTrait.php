<?php

declare( strict_types = 1 );

namespace WikibaseQuality\ConstraintReport\Tests;

use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;

/**
 * @license GPL-2.0-or-later
 */
trait PropertyDataTypeLookupMockingTrait {

	protected function mockPropertyDataTypeLookup() {
		$propertyDataTypeLookup = $this->createMock( PropertyDataTypeLookup::class );
		$propertyDataTypeLookup->expects( $this->any() )
			->method( 'getDataTypeIdForProperty' )
			->willReturn( 'any_value' );
		$this->setService( 'WikibaseRepo.PropertyDataTypeLookup', $propertyDataTypeLookup );
	}

}
