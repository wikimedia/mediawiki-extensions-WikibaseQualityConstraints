<?php

namespace WikibaseQuality\ConstraintReport\Test\QualifierChecker;

use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Statement\StatementListProvider;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\QualifierChecker;
use WikibaseQuality\ConstraintReport\Tests\ResultAssertions;
use WikibaseQuality\Tests\Helper\JsonFileEntityLookup;

/**
 * @covers \WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\QualifierChecker
 *
 * @group WikibaseQualityConstraints
 *
 * @uses   \WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class QualifierCheckerTest extends \MediaWikiTestCase {

	use ResultAssertions;

	/**
	 * @var JsonFileEntityLookup
	 */
	private $lookup;

	protected function setUp() {
		parent::setUp();
		$this->lookup = new JsonFileEntityLookup( __DIR__ );
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

	public function testQualifierConstraintQualifierProperty() {
		/** @var Item $entity */
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
		$qualifierChecker = new QualifierChecker();
		$checkResult = $qualifierChecker->checkConstraint( $this->getFirstStatement( $entity ), $this->getConstraintMock( [] ), $entity );
		$this->assertViolation( $checkResult, 'wbqc-violation-message-qualifier' );
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
			 ->method( 'getConstraintTypeItemId' )
			 ->will( $this->returnValue( 'Qualifier' ) );

		return $mock;
	}

}
