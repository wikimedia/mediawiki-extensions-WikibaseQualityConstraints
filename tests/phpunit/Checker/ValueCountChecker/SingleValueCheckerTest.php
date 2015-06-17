<?php

namespace WikibaseQuality\ConstraintReport\Test\ValueCountChecker;

use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Entity\EntityIdValue;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\SingleValueChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;
use WikibaseQuality\Tests\Helper\JsonFileEntityLookup;


/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\SingleValueChecker
 *
 * @group WikibaseQualityConstraints
 *
 * @uses   WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class SingleValueCheckerTest extends \MediaWikiTestCase {

	private $helper;
	private $singlePropertyId;
	private $checker;
	private $lookup;

	protected function setUp() {
		parent::setUp();

		$this->helper = new ConstraintParameterParser();
		$this->singlePropertyId = new PropertyId( 'P36' );
		$this->checker = new SingleValueChecker( $this->helper );
		$this->lookup = new JsonFileEntityLookup( __DIR__ );
	}

	protected function tearDown() {
		unset( $this->helper );
		unset( $this->singlePropertyId );
		unset( $this->checker );
		unset( $this->lookup );
		parent::tearDown();
	}

	public function testSingleValueConstraintOne() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
		$statement = new Statement( new PropertyValueSnak( $this->singlePropertyId, new EntityIdValue( new ItemId( 'Q1384' ) ) ) );
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( array() ), $entity );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testSingleValueConstraintTwo() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q2' ) );
		$statement = new Statement( new PropertyValueSnak( $this->singlePropertyId, new EntityIdValue( new ItemId( 'Q1384' ) ) ) );
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( array() ), $entity );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testSingleValueConstraintTwoButOneDeprecated() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q3' ) );
		$statement = new Statement( new PropertyValueSnak( $this->singlePropertyId, new EntityIdValue( new ItemId( 'Q1384' ) ) ) );
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( array() ), $entity );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	private function getConstraintMock( $parameter ) {
		$mock = $this
			->getMockBuilder( 'WikibaseQuality\ConstraintReport\Constraint' )
			->disableOriginalConstructor()
			->getMock();
		$mock->expects( $this->any() )
			 ->method( 'getConstraintParameter' )
			 ->willReturn( $parameter );
		$mock->expects( $this->any() )
			 ->method( 'getConstraintTypeQid' )
			 ->willReturn( 'Single value' );

		return $mock;
	}

}