<?php

namespace WikibaseQuality\ConstraintReport\Test\QualifierChecker;

use Wikibase\DataModel\Entity\ItemId;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\MandatoryQualifiersChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper;
use WikibaseQuality\Tests\Helper\JsonFileEntityLookup;


/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\MandatoryQualifiersChecker
 *
 * @group WikibaseQualityConstraints
 *
 * @uses   WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 * @uses   WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper
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
		$checkResult = $qualifierChecker->checkConstraint( $this->getFirstStatement( $entity ), $this->getConstraintMock( array( 'property' => 'P2' ) ) );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testMandatoryQualifiersConstraintInvalid() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q5' ) );
		$qualifierChecker = new MandatoryQualifiersChecker( $this->helper );
		$checkResult = $qualifierChecker->checkConstraint( $this->getFirstStatement( $entity ), $this->getConstraintMock( array( 'property' => 'P2,P3' ) ) );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	private function getConstraintMock( $parameter ) {
		$mock = $this
			->getMockBuilder( 'WikibaseQuality\ConstraintReport\Constraint' )
			->disableOriginalConstructor()
			->getMock();
		$mock->expects( $this->any() )
			 ->method( 'getConstraintParameters' )
			 ->willReturn( $parameter );
		$mock->expects( $this->any() )
			 ->method( 'getConstraintTypeQid' )
			 ->willReturn( 'Mandatory qualifiers' );

		return $mock;
	}

}