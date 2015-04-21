<?php

namespace WikidataQuality\ConstraintReport\Test\CheckResultToViolationTranslator;

use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Claim\Claim;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Entity\PropertyId;
use DataValues\StringValue;
use WikidataQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikidataQuality\ConstraintReport\ConstraintCheck\Result\CheckResultToViolationTranslator;
use Wikibase\Repo\WikibaseRepo;


/**
 * @covers WikidataQuality\ConstraintReport\ConstraintCheck\Result\CheckResultToViolationTranslator
 *
 * @group Database
 * @group medium
 *
 * @uses   WikidataQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class CheckResultTestToViolationTranslator extends \MediaWikiTestCase {

	private $translator;
	private $statement;
	private $constraintName;
	private $parameters;
	private $message;
	private $entity;

	/**
	 * @var EntityId[]
	 */
	private static $idMap;

	protected function setUp() {
		parent::setUp();
		$this->translator = new CheckResultToViolationTranslator();
		$this->statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P1' ), new StringValue( 'Foo' ) ) ) );
		$this->constraintName = 'Range';
		$this->parameters = array ();
		$this->message = 'All right';
		$this->entity = new Item();
		$store = WikibaseRepo::getDefaultInstance()->getEntityStore();
		$store->saveEntity( $this->entity, 'TestEntityQ1', $GLOBALS[ 'wgUser' ], EDIT_NEW );
		self::$idMap[ 'Q1' ] = $this->entity->getId();
	}

	protected function tearDown() {
		parent::tearDown();
		unset( $this->translator );
		unset( $this->statement );
		unset( $this->constraintName );
		unset( $this->parameters );
		unset( $this->message );
		unset( $this->entity );
	}

	public function testSingleComplianceResult() {
		$checkResult = new CheckResult( $this->statement, $this->constraintName, $this->parameters, 'compliance', $this->message );
		$violations = $this->translator->translateToViolation( $this->entity, $checkResult );
		$this->assertEquals( array (), $violations );
	}

	public function testSingleViolationResult() {
		$checkResult = new CheckResult( $this->statement, $this->constraintName, $this->parameters, 'violation', $this->message );
		$violations = $this->translator->translateToViolation( $this->entity, $checkResult );
		$this->assertEquals( 1, sizeof( $violations ) );

		$violation = $violations[ 0 ];
		$this->assertEquals( self::$idMap[ 'Q1' ], $violation->getEntityId() );
		$this->assertEquals( 'P1', $violation->getPropertyId()->getSerialization() );
		$this->assertEquals( $this->statement->getGuid(), $violation->getClaimGuid() );
		$this->assertEquals( md5( $this->statement->getGuid() . $checkResult->getConstraintName() ), $violation->getConstraintClaimGuid() );
		$this->assertEquals( $checkResult->getConstraintName(), $violation->getConstraintTypeEntityId() );

	}

	public function testMultipleCheckResults() {
		$checkResults = array ();
		$checkResults[ ] = new CheckResult( $this->statement, $this->constraintName, $this->parameters, 'violation', $this->message );
		$checkResults[ ] = new CheckResult( $this->statement, $this->constraintName, $this->parameters, 'violation', $this->message );
		$checkResults[ ] = new CheckResult( $this->statement, $this->constraintName, $this->parameters, 'compliance', $this->message );
		$violations = $this->translator->translateToViolation( $this->entity, $checkResults );
		$this->assertEquals( 2, sizeof( $violations ) );
	}

}