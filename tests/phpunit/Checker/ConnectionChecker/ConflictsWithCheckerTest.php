<?php

namespace WikibaseQuality\ConstraintReport\Test\ConnectionChecker;

use ValueFormatters\ValueFormatter;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\EntityId\PlainEntityIdFormatter;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\ConflictsWithChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConnectionCheckerHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;
use WikibaseQuality\ConstraintReport\ConstraintParameterRenderer;
use WikibaseQuality\Tests\Helper\JsonFileEntityLookup;

/**
 * @covers \WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\ConflictsWithChecker
 *
 * @group WikibaseQualityConstraints
 *
 * @uses   \WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 * @uses   \WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\constraintParameterParser
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class ConflictsWithCheckerTest extends \MediaWikiTestCase {

	/**
	 * @var JsonFileEntityLookup
	 */
	private $lookup;

	/**
	 * @var ConstraintParameterParser
	 */
	private $helper;

	/**
	 * @var ConnectionCheckerHelper
	 */
	private $connectionCheckerHelper;

	/**
	 * @var ConstraintParameterRenderer
	 */
	private $constraintParameterRenderer;

	/**
	 * @var ConflictsWithChecker
	 */
	private $checker;

	protected function setUp() {
		parent::setUp();
		$this->lookup = new JsonFileEntityLookup( __DIR__ );
		$this->helper = new ConstraintParameterParser();
		$this->connectionCheckerHelper = new ConnectionCheckerHelper();
		$valueFormatter = $this->getMock( ValueFormatter::class );
		$valueFormatter->method( 'format' )->willReturn( '' );
		$this->constraintParameterRenderer = new ConstraintParameterRenderer(
			new PlainEntityIdFormatter(),
			$valueFormatter
		);
		$this->checker = new ConflictsWithChecker(
			$this->lookup,
			$this->helper,
			$this->connectionCheckerHelper,
			$this->constraintParameterRenderer
		);
	}

	protected function tearDown() {
		unset( $this->lookup );
		unset( $this->helper );
		unset( $this->connectionCheckerHelper );
		unset( $this->constraintParameterRenderer );
		unset( $this->checker );
		parent::tearDown();
	}

	public function testConflictsWithConstraintValid() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q4' ) );

		$value = new EntityIdValue( new ItemId( 'Q100' ) );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) );

		$constraintParameters = [
			'property' => 'P2'
		];

		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testConflictsWithConstraintProperty() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q5' ) );
		$this->checker = new ConflictsWithChecker( $this->lookup, $this->helper, $this->connectionCheckerHelper, $this->constraintParameterRenderer );

		$value = new EntityIdValue( new ItemId( 'Q100' ) );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) );

		$constraintParameters = [
			'property' => 'P2'
		];

		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testConflictsWithConstraintPropertyButNotItem() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q5' ) );
		$this->checker = new ConflictsWithChecker( $this->lookup, $this->helper, $this->connectionCheckerHelper, $this->constraintParameterRenderer );

		$value = new EntityIdValue( new ItemId( 'Q100' ) );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) );

		$constraintParameters = [
			'item' => 'Q1',
			'property' => 'P2'
		];

		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testConflictsWithConstraintPropertyAndItem() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q5' ) );
		$this->checker = new ConflictsWithChecker( $this->lookup, $this->helper, $this->connectionCheckerHelper, $this->constraintParameterRenderer );

		$value = new EntityIdValue( new ItemId( 'Q100' ) );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) );

		$constraintParameters = [
			'item' => 'Q42',
			'property' => 'P2'
		];

		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testConflictsWithConstraintWithoutProperty() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q4' ) );
		$this->checker = new ConflictsWithChecker( $this->lookup, $this->helper, $this->connectionCheckerHelper, $this->constraintParameterRenderer );

		$value = new EntityIdValue( new ItemId( 'Q100' ) );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) );

		$constraintParameters = [];

		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testConflictsWithConstraintPropertyAndNoValue() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q6' ) );
		$this->checker = new ConflictsWithChecker( $this->lookup, $this->helper, $this->connectionCheckerHelper, $this->constraintParameterRenderer );

		$value = new EntityIdValue( new ItemId( 'Q100' ) );
		$statement = new Statement( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) );

		$constraintParameters = [
			'item' => 'Q42',
			'property' => 'P2'
		];

		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
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
			 ->method( 'getConstraintParameters' )
			 ->will( $this->returnValue( $parameters ) );
		$mock->expects( $this->any() )
			 ->method( 'getConstraintTypeQid' )
			 ->will( $this->returnValue( 'Conflicts with' ) );

		return $mock;
	}

}
