<?php

namespace WikibaseQuality\ConstraintReport\Test\QualifierChecker;

use ValueFormatters\ValueFormatter;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Services\EntityId\PlainEntityIdFormatter;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Statement\StatementListProvider;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\QualifiersChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;
use WikibaseQuality\ConstraintReport\ConstraintParameterRenderer;
use WikibaseQuality\Tests\Helper\JsonFileEntityLookup;

/**
 * @covers \WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\QualifiersChecker
 *
 * @group WikibaseQualityConstraints
 *
 * @uses   \WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 * @uses   \WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class QualifiersCheckerTest extends \MediaWikiTestCase {

	/**
	 * @var ConstraintParameterParser
	 */
	private $helper;

	/**
	 * @var string
	 */
	private $qualifiersList;

	/**
	 * @var JsonFileEntityLookup
	 */
	private $lookup;

	/**
	 * @var ConstraintParameterRenderer
	 */
	private $constraintParameterRenderer;

	protected function setUp() {
		parent::setUp();
		$this->helper = new ConstraintParameterParser();
		$this->qualifiersList = 'P580,P582,P1365,P1366,P642,P805';
		$this->lookup = new JsonFileEntityLookup( __DIR__ );
		$valueFormatter = $this->getMock( ValueFormatter::class );
		$valueFormatter->method( 'format' )->willReturn( '' );
		$this->constraintParameterRenderer = new ConstraintParameterRenderer(
			new PlainEntityIdFormatter(),
			$valueFormatter
		);
	}

	protected function tearDown() {
		unset( $this->helper );
		unset( $this->qualifiersList );
		unset( $this->lookup );
		unset( $this->constraintParameterRenderer );
		parent::tearDown();
	}

	/**
	 * @param StatementListProvider $entity
	 *
	 * @return Statement|false
	 */
	private function getFirstStatement( StatementListProvider $entity ) {
		$statements = $entity->getStatements()->toArray();
		return reset( $statements );
	}

	public function testQualifiersConstraint() {
		/** @var Item $entity */
		$entity = $this->lookup->getEntity( new ItemId( 'Q2' ) );
		$qualifiersChecker = new QualifiersChecker( $this->helper, $this->constraintParameterRenderer );
		$checkResult = $qualifiersChecker->checkConstraint( $this->getFirstStatement( $entity ), $this->getConstraintMock( [ 'property' => $this->qualifiersList ] ), $entity );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testQualifiersConstraintToManyQualifiers() {
		/** @var Item $entity */
		$entity = $this->lookup->getEntity( new ItemId( 'Q3' ) );
		$qualifiersChecker = new QualifiersChecker( $this->helper, $this->constraintParameterRenderer );
		$checkResult = $qualifiersChecker->checkConstraint( $this->getFirstStatement( $entity ), $this->getConstraintMock( [ 'property' => $this->qualifiersList ] ), $entity );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testQualifiersConstraintNoQualifiers() {
		/** @var Item $entity */
		$entity = $this->lookup->getEntity( new ItemId( 'Q4' ) );
		$qualifiersChecker = new QualifiersChecker( $this->helper, $this->constraintParameterRenderer );
		$checkResult = $qualifiersChecker->checkConstraint( $this->getFirstStatement( $entity ), $this->getConstraintMock( [ 'property' => $this->qualifiersList ] ), $entity );
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
			 ->will( $this->returnValue( 'Qualifiers' ) );

		return $mock;
	}

}
