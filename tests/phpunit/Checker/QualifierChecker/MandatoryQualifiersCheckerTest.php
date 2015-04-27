<?php

namespace WikidataQuality\ConstraintReport\Test\QualifierChecker;

use Wikibase\DataModel\Entity\ItemId;
use WikidataQuality\ConstraintReport\ConstraintCheck\Checker\MandatoryQualifiersChecker;
use WikidataQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper;
use WikidataQuality\Tests\Helper\JsonFileEntityLookup;


/**
 * @covers WikidataQuality\ConstraintReport\ConstraintCheck\Checker\MandatoryQualifiersChecker
 *
 * @uses   WikidataQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 * @uses   WikidataQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class MandatoryQualifiersCheckerTest extends \MediaWikiTestCase {

	private $helper;
	private $lookup;

	protected function setUp() {
		parent::setUp();
		$this->helper = new ConstraintReportHelper();
		$this->lookup = new JsonFileEntityLookup( __DIR__ );
	}

	protected function tearDown() {
		unset( $this->helper );
		unset( $this->lookup );
		parent::tearDown();
	}

	private function getFirstStatement( $entity ) {
		foreach ( $entity->getStatements() as $statement ) {
			return $statement;
		}
	}

	public function testMandatoryQualifiersConstraintValid() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q5' ) );
		$qualifierChecker = new MandatoryQualifiersChecker( $this->helper );
		$checkResult = $qualifierChecker->checkConstraint( $this->getFirstStatement( $entity ), array( 'property' => array ( 'P2' ) ) );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testMandatoryQualifiersConstraintInvalid() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q5' ) );
		$qualifierChecker = new MandatoryQualifiersChecker( $this->helper );
		$checkResult = $qualifierChecker->checkConstraint( $this->getFirstStatement( $entity ), array( 'property' => array (
			'P2',
			'P3'
		) ) );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

}