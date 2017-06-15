<?php

namespace WikibaseQuality\ConstraintReport\Test\QualifierChecker;

use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Statement\StatementListProvider;
use Wikibase\Repo\WikibaseRepo;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\QualifiersChecker;
use WikibaseQuality\ConstraintReport\Tests\ConstraintParameters;
use WikibaseQuality\ConstraintReport\Tests\ResultAssertions;
use WikibaseQuality\Tests\Helper\JsonFileEntityLookup;

/**
 * @covers \WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\QualifiersChecker
 *
 * @group WikibaseQualityConstraints
 *
 * @uses   \WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 * @uses   \WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintStatementParameterParser
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class QualifiersCheckerTest extends \MediaWikiTestCase {

	use ConstraintParameters, ResultAssertions;

	/**
	 * @var string
	 */
	private $qualifiersList;

	/**
	 * @var JsonFileEntityLookup
	 */
	private $lookup;

	protected function setUp() {
		parent::setUp();
		$this->qualifiersList = 'P580,P582,P1365,P1366,P642,P805';
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

	public function testQualifiersConstraint() {
		/** @var Item $entity */
		$entity = $this->lookup->getEntity( new ItemId( 'Q2' ) );
		$qualifiersChecker = new QualifiersChecker( $this->getConstraintParameterParser(), $this->getConstraintParameterRenderer() );
		$checkResult = $qualifiersChecker->checkConstraint( $this->getFirstStatement( $entity ), $this->getConstraintMock( [ 'property' => $this->qualifiersList ] ), $entity );
		$this->assertCompliance( $checkResult );
	}

	public function testQualifiersConstraintTooManyQualifiers() {
		/** @var Item $entity */
		$entity = $this->lookup->getEntity( new ItemId( 'Q3' ) );
		$qualifiersChecker = new QualifiersChecker( $this->getConstraintParameterParser(), $this->getConstraintParameterRenderer() );
		$checkResult = $qualifiersChecker->checkConstraint( $this->getFirstStatement( $entity ), $this->getConstraintMock( [ 'property' => $this->qualifiersList ] ), $entity );
		$this->assertViolation( $checkResult, 'wbqc-violation-message-qualifiers' );
	}

	public function testQualifiersConstraintNoQualifiers() {
		/** @var Item $entity */
		$entity = $this->lookup->getEntity( new ItemId( 'Q4' ) );
		$qualifiersChecker = new QualifiersChecker( $this->getConstraintParameterParser(), $this->getConstraintParameterRenderer() );
		$checkResult = $qualifiersChecker->checkConstraint( $this->getFirstStatement( $entity ), $this->getConstraintMock( [ 'property' => $this->qualifiersList ] ), $entity );
		$this->assertCompliance( $checkResult );
	}

	/**
	 * Logically identical to {@link testQualifiersConstraint},
	 * but with statement parameters instead of template parameters.
	 */
	public function testQualifiersConstraintWithStatement() {
		/** @var Item $entity */
		$entity = $this->lookup->getEntity( new ItemId( 'Q2' ) );
		$qualifiersChecker = new QualifiersChecker( $this->getConstraintParameterParser(), $this->getConstraintParameterRenderer() );

		$snakSerializer = WikibaseRepo::getDefaultInstance()->getSerializerFactory()->newSnakSerializer();
		$config = $this->getDefaultConfig();
		$propertyId = $config->get( 'WBQualityConstraintsPropertyId' );
		$parameters = [ $propertyId => array_map(
			function( $id ) use ( $snakSerializer, $propertyId ) {
				return $snakSerializer->serialize( new PropertyValueSnak( new PropertyId( $propertyId ), new EntityIdValue( new PropertyId( $id ) ) ) );
			},
			explode( ',', $this->qualifiersList )
		) ];

		$checkResult = $qualifiersChecker->checkConstraint( $this->getFirstStatement( $entity ), $this->getConstraintMock( $parameters ), $entity );

		$this->assertCompliance( $checkResult );
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
