<?php

namespace WikibaseQuality\ConstraintReport\Test\TypeChecker;

use PHPUnit_Framework_TestCase;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\TypeSparqlChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\SparqlHelper;
use WikibaseQuality\ConstraintReport\Tests\DefaultConfig;
use WikibaseQuality\ConstraintReport\Tests\ResultAssertions;
use WikibaseQuality\ConstraintReport\Tests\SparqlHelperMock;
use WikibaseQuality\Tests\Helper\JsonFileEntityLookup;

/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\TypeChecker
 *
 * @group WikibaseQualityConstraints
 *
 * @uses   WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 * @uses   WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser
 *
 * @author Olga Bode
 * @license GNU GPL v2+
 */
class TypeSparqlCheckerTest extends \PHPUnit_Framework_TestCase  {

	use DefaultConfig, ResultAssertions, SparqlHelperMock;

	/**
	 * @var JsonFileEntityLookup
	 */
	private $lookup;

	/**
	 * @var TypeSparqlChecker
	 */
	private $checker;

	/**
	 * @var Statement
	 */
	private $typeStatement;

	protected function setUp() {
		parent::setUp();

		$this->lookup = new JsonFileEntityLookup( __DIR__ );

		$this->typeStatement = new Statement( new PropertyValueSnak( new PropertyId( 'P1' ), new EntityIdValue( new ItemId( 'Q42' ) ) ) );
	}

	protected function tearDown() {
		unset( $this->lookup );
		unset( $this->typeStatement );
		parent::tearDown();
	}

	// relation 'subclass'

	public function testTypeConstraintSubclassValid() {
		$mock = $this->getSparqlHelperMockHasType( 'Q1', [ 'Q100', 'Q101' ], false, true );

		$this->checker = new TypeSparqlChecker(
			$this->lookup,
			new ConstraintParameterParser(),
			$mock
		);

		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );

		$constraintParameters = [
			'class' => 'Q100,Q101',
			'relation' => 'subclass'
		];

		$checkResult = $this->checker->checkConstraint( $this->typeStatement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertCompliance( $checkResult );
	}

	// relation 'subclass', violations

	public function testTypeConstraintSubclassIsInvalid() {
		$mock = $this->getSparqlHelperMockHasType( 'Q1', [ 'Q200', 'Q201' ], false, false );

		$this->checker = new TypeSparqlChecker(
			$this->lookup,
			new ConstraintParameterParser(),
			$mock
		);

		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );

		$constraintParameters = [
			'class' => 'Q200,Q201',
			'relation' => 'subclass'
		];

		$checkResult = $this->checker->checkConstraint( $this->typeStatement, $this->getConstraintMock( $constraintParameters ), $entity );
		$this->assertViolation( $checkResult, 'wbqc-violation-message-sparql-type' );
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
			->will( $this->returnValue( 'Type' ) );

		return $mock;
	}

}
