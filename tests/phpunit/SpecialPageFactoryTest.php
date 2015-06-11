<?php

namespace WikibaseQuality\ConstraintReport\Tests;


use WikibaseQuality\ConstraintReport\SpecialPageFactory;


/**
 * @covers WikibaseQuality\ConstraintReport\SpecialPageFactory
 *
 * @group WikibaseQuality
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class SpecialPageFactoryTest extends \MediaWikiTestCase {

	public function testNewSpecialConstraintReport() {
		$specialPage = SpecialPageFactory::newSpecialConstraintReport();

		$this->assertInstanceOf( 'WikibaseQuality\ConstraintReport\Specials\SpecialConstraintReport', $specialPage );
	}

	public function testCreateSpecialConstraintReport() {
		$specialPage = $this->getFactory()->createSpecialConstraintReport();

		$this->assertInstanceOf( 'WikibaseQuality\ConstraintReport\Specials\SpecialConstraintReport', $specialPage );
	}

	/**
	 * @return SpecialPageFactory
	 */
	private function getFactory() {
		$entityLookup = $this->getMockForAbstractClass( 'Wikibase\Lib\Store\EntityLookup' );
		$termLookup = $this->getMockForAbstractClass( 'Wikibase\Lib\Store\TermLookup' );
		$entityTitleLookup = $this->getMockForAbstractClass( 'Wikibase\Lib\Store\EntityTitleLookup' );
		$entityIdParser = $this->getMockForAbstractClass( 'Wikibase\DataModel\Entity\EntityIdParser' );
		$valueFormatterFactory = $this->getMockBuilder( 'Wikibase\Lib\OutputFormatValueFormatterFactory' )
			->disableOriginalConstructor()
			->getMock();
		$constraintChecker = $this->getMockBuilder( 'WikibaseQuality\ConstraintReport\ConstraintCheck\DelegatingConstraintChecker' )
			->disableOriginalConstructor()
			->getMock();


		return new SpecialPageFactory(
			$entityLookup,
			$termLookup,
			$entityTitleLookup,
			$entityIdParser,
			$valueFormatterFactory,
			$constraintChecker
		);
	}
}
