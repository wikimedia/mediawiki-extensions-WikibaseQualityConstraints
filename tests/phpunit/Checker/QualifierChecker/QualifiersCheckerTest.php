<?php

namespace WikibaseQuality\ConstraintReport\Test\QualifierChecker;

use Wikibase\DataModel\Entity\ItemId;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\QualifiersChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper;
use WikibaseQuality\Tests\Helper\JsonFileEntityLookup;


/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\QualifiersChecker
 *
 * @group WikibaseQualityConstraints
 *
 * @uses   WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 * @uses   WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class QualifiersCheckerTest extends \MediaWikiTestCase {

	private $helper;
	private $qualifiersList;
	private $lookup;

	protected function setUp() {
		parent::setUp();
		$this->helper = new ConstraintReportHelper();
		$this->qualifiersList = 'P580,P582,P1365,P1366,P642,P805';
		$this->lookup = new JsonFileEntityLookup( __DIR__ );
	}

	protected function tearDown() {
		unset( $this->helper );
		unset( $this->qualifiersList );
		unset( $this->lookup );
		parent::tearDown();
	}

	private function getFirstStatement( $entity ) {
		foreach ( $entity->getStatements() as $statement ) {
			return $statement;
		}
	}

	public function testQualifiersConstraint() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q2' ) );
		$qualifiersChecker = new QualifiersChecker( $this->helper );
		$checkResult = $qualifiersChecker->checkConstraint( $this->getFirstStatement( $entity ), $this->getConstraintMock( array( 'property' => $this->qualifiersList ) ) );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testQualifiersConstraintToManyQualifiers() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q3' ) );
		$qualifiersChecker = new QualifiersChecker( $this->helper );
		$checkResult = $qualifiersChecker->checkConstraint( $this->getFirstStatement( $entity ), $this->getConstraintMock( array( 'property' => $this->qualifiersList ) ) );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testQualifiersConstraintNoQualifiers() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q4' ) );
		$qualifiersChecker = new QualifiersChecker( $this->helper );
		$checkResult = $qualifiersChecker->checkConstraint( $this->getFirstStatement( $entity ), $this->getConstraintMock( array( 'property' => $this->qualifiersList ) ) );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	private function getConstraintMock( $parameter ) {
		$mock = $this
			->getMockBuilder( 'WikibaseQuality\ConstraintReport\Constraint' )
			->disableOriginalConstructor()
			->getMock();
		$mock->expects( $this->any() )
			 ->method( 'getConstraintParameters' )
			 ->will( $this->returnValue( $parameter ) );
		$mock->expects( $this->any() )
			 ->method( 'getConstraintTypeQid' )
			 ->will( $this->returnValue( 'Qualifiers' ) );

		return $mock;
	}

}