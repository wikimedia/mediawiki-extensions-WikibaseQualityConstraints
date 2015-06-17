<?php

namespace WikibaseQuality\ConstraintReport\Test\ValueCountChecker;

use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Entity\EntityIdValue;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\UniqueValueChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;
use WikibaseQuality\Tests\Helper\JsonFileEntityLookup;


/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\UniqueValueChecker
 *
 * @group WikibaseQualityConstraints
 *
 * @uses   WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class UniqueValueCheckerTest extends \MediaWikiTestCase {

	private $helper;
	private $uniquePropertyId;
	private $checker;
	private $lookup;

	protected function setUp() {
		parent::setUp();

		$this->helper = new ConstraintParameterParser();
		$this->uniquePropertyId = new PropertyId( 'P227' );
		$this->checker = new UniqueValueChecker( $this->helper );
		$this->lookup = new JsonFileEntityLookup( __DIR__ );
	}

	protected function tearDown() {
		unset( $this->helper );
		unset( $this->uniquePropertyId );
		unset( $this->lookup );
		parent::tearDown();
	}

	// todo: it is currently only testing that 'todo' comes back
	public function testCheckUniqueValueConstraint() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
		$statement = new Statement( new PropertyValueSnak( $this->uniquePropertyId, new EntityIdValue( new ItemId( 'Q404' ) ) ) );
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( array() ) );
		$this->assertEquals( 'todo', $checkResult->getStatus(), 'check should point out that it should be implemented soon' );
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
			 ->willReturn( 'Unique value' );

		return $mock;
	}
}