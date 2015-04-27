<?php

namespace WikidataQuality\ConstraintReport\Test\ConstraintChecker;

use WikidataQuality\ConstraintReport\ConstraintCheck\CheckerMapBuilder;
use WikidataQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper;
use WikidataQuality\Tests\Helper\JsonFileEntityLookup;


/**
 * @covers WikidataQuality\ConstraintReport\ConstraintCheck\DelegatingConstraintChecker
 *
 * @uses   WikidataQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper
 * @uses   WikidataQuality\ConstraintReport\ConstraintCheck\Checker\RangeChecker
 * @uses   WikidataQuality\ConstraintReport\ConstraintCheck\Checker\DiffWithinRangeChecker
 * @uses   WikidataQuality\ConstraintReport\ConstraintCheck\Checker\SingleValueChecker
 * @uses   WikidataQuality\ConstraintReport\ConstraintCheck\Checker\MultiValueChecker
 * @uses   WikidataQuality\ConstraintReport\ConstraintCheck\Checker\UniqueValueChecker
 * @uses   WikidataQuality\ConstraintReport\ConstraintCheck\Checker\OneOfChecker
 * @uses   WikidataQuality\ConstraintReport\ConstraintCheck\Checker\CommonsLinkChecker
 * @uses   WikidataQuality\ConstraintReport\ConstraintCheck\Checker\TargetRequiredClaimChecker
 * @uses   WikidataQuality\ConstraintReport\ConstraintCheck\Checker\ItemChecker
 * @uses   WikidataQuality\ConstraintReport\ConstraintCheck\Checker\ConflictsWithChecker
 * @uses   WikidataQuality\ConstraintReport\ConstraintCheck\Checker\SymmetricChecker
 * @uses   WikidataQuality\ConstraintReport\ConstraintCheck\Checker\InverseChecker
 * @uses   WikidataQuality\ConstraintReport\ConstraintCheck\Checker\FormatChecker
 * @uses   WikidataQuality\ConstraintReport\ConstraintCheck\Checker\QualifierChecker
 * @uses   WikidataQuality\ConstraintReport\ConstraintCheck\Checker\QualifiersChecker
 * @uses   WikidataQuality\ConstraintReport\ConstraintCheck\Checker\MandatoryQualifiersChecker
 * @uses   WikidataQuality\ConstraintReport\ConstraintCheck\Checker\TypeChecker
 * @uses   WikidataQuality\ConstraintReport\ConstraintCheck\Checker\ValueTypeChecker
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class CheckerMapBuilderTest extends \MediaWikiTestCase {

	private $checkerMap;

	protected function setUp() {
		parent::setUp();
		$lookup = new JsonFileEntityLookup();
		$this->checkerMap = new CheckerMapBuilder( $lookup, new ConstraintReportHelper() );

	}

	protected function tearDown() {
		unset( $this->checkerMap );
		parent::tearDown();
	}

	public function testGetCheckerMap() {
		$map = $this->checkerMap->getCheckerMap();
		$classPrefix = 'WikidataQuality\\ConstraintReport\\ConstraintCheck\\Checker\\';
		$this->assertEquals( $classPrefix . 'SingleValueChecker', get_class( $map['Single value'] ) );
		$this->assertEquals( $classPrefix . 'MultiValueChecker', get_class( $map['Multi value'] ) );
		$this->assertEquals( $classPrefix . 'UniqueValueChecker', get_class( $map['Unique value'] ) );
		$this->assertEquals( $classPrefix . 'SymmetricChecker', get_class( $map['Symmetric'] ) );
		$this->assertEquals( $classPrefix . 'InverseChecker', get_class( $map['Inverse'] ) );
		$this->assertEquals( $classPrefix . 'ItemChecker', get_class( $map['Item'] ) );
		$this->assertEquals( $classPrefix . 'ConflictsWithChecker', get_class( $map['Conflicts with'] ) );
		$this->assertEquals( $classPrefix . 'TargetRequiredClaimChecker', get_class( $map['Target required claim'] ) );
		$this->assertEquals( $classPrefix . 'TypeChecker', get_class( $map['Type'] ) );
		$this->assertEquals( $classPrefix . 'ValueTypeChecker', get_class( $map['Value type'] ) );
		$this->assertEquals( $classPrefix . 'OneOfChecker', get_class( $map['One of'] ) );
		$this->assertEquals( $classPrefix . 'CommonsLinkChecker', get_class( $map['Commons link'] ) );
		$this->assertEquals( $classPrefix . 'FormatChecker', get_class( $map['Format'] ) );
		$this->assertEquals( $classPrefix . 'RangeChecker', get_class( $map['Range'] ) );
		$this->assertEquals( $classPrefix . 'DiffWithinRangeChecker', get_class( $map['Diff within range'] ) );
		$this->assertEquals( $classPrefix . 'QualifierChecker', get_class( $map['Qualifier'] ) );
		$this->assertEquals( $classPrefix . 'QualifiersChecker', get_class( $map['Qualifiers'] ) );
		$this->assertEquals( $classPrefix . 'MandatoryQualifiersChecker', get_class( $map['Mandatory qualifiers'] ) );
	}

}