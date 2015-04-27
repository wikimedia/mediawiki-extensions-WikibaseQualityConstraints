<?php

namespace WikidataQuality\ConstraintReport\Test\QualifierChecker;

use Wikibase\DataModel\Entity\ItemId;
use WikidataQuality\ConstraintReport\ConstraintCheck\Checker\QualifierChecker;
use WikidataQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper;
use WikidataQuality\Tests\Helper\JsonFileEntityLookup;


/**
 * @covers WikidataQuality\ConstraintReport\ConstraintCheck\Checker\QualifierChecker
 *
 * @uses   WikidataQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 * @uses   WikidataQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper
 *
 * @group WikidataQualityConstraints
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class QualifierCheckerTest extends \MediaWikiTestCase {

	private $helper;
	private $qualifiersList;
	private $lookup;

	protected function setUp() {
		parent::setUp();
		$this->helper = new ConstraintReportHelper();
		$this->qualifiersList = array ( 'P580', 'P582', 'P1365', 'P1366', 'P642', 'P805' );
		$this->lookup = new JsonFileEntityLookup( __DIR__ );
	}

	protected function tearDown() {
		unset( $this->helper );
		unset( $this->qualifiersList );
		parent::tearDown();
	}

	private function getFirstStatement( $entity ) {
		foreach ( $entity->getStatements() as $statement ) {
			return $statement;
		}
	}

	/*
	 * Following tests are testing the 'Qualifier' constraint.
	 */

	public function testQualifierConstraintQualifierProperty() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
		$qualifierChecker = new QualifierChecker( $this->helper );
		$checkResult = $qualifierChecker->checkQualifierConstraint( $this->getFirstStatement( $entity ) );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	/*
	 * Following tests are testing the 'Qualifiers' constraint.
	 */

	public function testQualifiersConstraint() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q2' ) );
		$qualifierChecker = new QualifierChecker( $this->helper );
		$checkResult = $qualifierChecker->checkQualifiersConstraint( $this->getFirstStatement( $entity ), $this->qualifiersList );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testQualifiersConstraintToManyQualifiers() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q3' ) );
		$qualifierChecker = new QualifierChecker( $this->helper );
		$checkResult = $qualifierChecker->checkQualifiersConstraint( $this->getFirstStatement( $entity ), $this->qualifiersList );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testQualifiersConstraintNoQualifiers() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q4' ) );
		$qualifierChecker = new QualifierChecker( $this->helper );
		$checkResult = $qualifierChecker->checkQualifiersConstraint( $this->getFirstStatement( $entity ), array ( '' ) );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	/*
	 * Following tests are testing the 'Mandatory qualifiers' constraint
	 */

	public function testMandatoryQualifiersConstraintValid() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q5' ) );
		$qualifierChecker = new QualifierChecker( $this->helper );
		$checkResult = $qualifierChecker->checkMandatoryQualifiersConstraint( $this->getFirstStatement( $entity ), array ( 'P2' ) );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testMandatoryQualifiersConstraintInvalid() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q5' ) );
		$qualifierChecker = new QualifierChecker( $this->helper );
		$checkResult = $qualifierChecker->checkMandatoryQualifiersConstraint( $this->getFirstStatement( $entity ), array (
			'P2',
			'P3'
		) );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

}