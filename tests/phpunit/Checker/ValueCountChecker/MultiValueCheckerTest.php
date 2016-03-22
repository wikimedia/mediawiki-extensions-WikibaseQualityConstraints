<?php

namespace WikibaseQuality\ConstraintReport\Test\ValueCountChecker;

use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Entity\EntityIdValue;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\MultiValueChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;
use WikibaseQuality\Tests\Helper\JsonFileEntityLookup;

/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\MultiValueChecker
 *
 * @group WikibaseQualityConstraints
 *
 * @uses   WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class MultiValueCheckerTest extends \MediaWikiTestCase {

	/**
	 * @var ConstraintParameterParser
	 */
	private $helper;

	/**
	 * @var PropertyId
	 */
	private $multiPropertyId;

	/**
	 * @var MultiValueChecker
	 */
	private $checker;

	/**
	 * @var JsonFileEntityLookup
	 */
	private $lookup;

	protected function setUp() {
		parent::setUp();

		$this->helper = new ConstraintParameterParser();
		$this->multiPropertyId = new PropertyId( 'P161' );
		$this->checker = new MultiValueChecker( $this->helper );
		$this->lookup = new JsonFileEntityLookup( __DIR__ );
	}

	protected function tearDown() {
		unset( $this->helper );
		unset( $this->multiPropertyId );
		unset( $this->checker );
		unset( $this->lookup );
		parent::tearDown();
	}

	public function testMultiValueConstraintOne() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q4' ) );
		$statement = new Statement( new PropertyValueSnak( $this->multiPropertyId, new EntityIdValue( new ItemId( 'Q207' ) ) ) );
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( array() ), $entity );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testMultiValueConstraintTwo() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q5' ) );
		$statement = new Statement( new PropertyValueSnak( $this->multiPropertyId, new EntityIdValue( new ItemId( 'Q207' ) ) ) );
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( array() ), $entity );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testMultiValueConstraintTwoButOneDeprecated() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q6' ) );
		$statement = new Statement( new PropertyValueSnak( $this->multiPropertyId, new EntityIdValue( new ItemId( 'Q409' ) ) ) );
		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( array() ), $entity );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	/**
	 * @param string[] $parameters
	 *
	 * @return Constraint
	 */
	private function getConstraintMock( array $parameters ) {
		$mock = $this
			->getMockBuilder( Constraint::class )
			->disableOriginalConstructor()
			->getMock();
		$mock->expects( $this->any() )
			 ->method( 'getConstraintParameter' )
			 ->will( $this->returnValue( $parameters ) );
		$mock->expects( $this->any() )
			 ->method( 'getConstraintTypeQid' )
			 ->will( $this->returnValue( 'Multi value' ) );

		return $mock;
	}

}
