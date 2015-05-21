<?php

namespace WikibaseQuality\ConstraintReport\Test\CheckResultToViolationTranslator;

use Wikibase\DataModel\Entity\Entity;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Claim\Claim;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Entity\PropertyId;
use DataValues\StringValue;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResultToViolationTranslator;
use Wikibase\Repo\WikibaseRepo;


/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResultToViolationTranslator
 *
 * @group WikidataQualityConstraints
 * @group Database
 * @group medium
 *
 * @uses   WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class CheckResultTestToViolationTranslatorTest extends \MediaWikiTestCase {

    /**
     * @var CheckResultToViolationTranslator
     */
    private $translator;

    /**
     * @var PropertyId
     */
    private $propertyId;

    /**
     * @var Statement
     */
    private $statement;

    /**
     * @var array
     */
    private $parameters;

    /**
     * @var string
     */
    private $message;

    /**
     * @var string
     */
    private $claimGuid;

    /**
     * @var string
     */
    private $constraintName;

    /**
     * @var Entity
     */
    private $entity;

    /**
     * @var EntityId[]
     */
    private static $idMap;

	protected function setUp() {
		parent::setUp();
		$this->translator = new CheckResultToViolationTranslator( $this->getEntityRevisionLookupMock() );
		$this->statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P1' ), new StringValue( 'Foo' ) ) ) );
		$this->propertyId =  new PropertyId( 'P1' );
		$this->claimGuid = 'P1$aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';
		$this->statement->setGuid( 'P1$aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee' );
		$this->constraintName = 'Single value';
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

	public function testSingleViolationResultWithoutParameter() {
		$checkResult = new CheckResult( $this->statement, $this->constraintName, $this->parameters, 'violation', $this->message );
		$violations = $this->translator->translateToViolation( $this->entity, $checkResult );
		$this->assertEquals( 1, sizeof( $violations ) );

		$violation = $violations[ 0 ];
		$this->assertEquals( self::$idMap[ 'Q1' ], $violation->getEntityId() );
		$this->assertEquals( 'P1', $violation->getPropertyId()->getSerialization() );
		$this->assertEquals( $this->statement->getGuid(), $violation->getClaimGuid() );
		$this->assertEquals( md5( $this->statement->getGuid() . $checkResult->getConstraintName() ), $violation->getConstraintId() );
		$this->assertEquals( $checkResult->getConstraintName(), $violation->getConstraintTypeEntityId() );
		$this->assertEquals( 42, $violation->getRevisionId() );
		$this->assertEquals( '[]', $violation->getAdditionalInfo() );
	}

	public function testSingleViolationResultWithParameter() {
		$checkResult = new CheckResult( $this->statement, $this->constraintName, array( array( 'constraint_status' => 'mandatory' ) ), 'violation', $this->message );
		$violations = $this->translator->translateToViolation( $this->entity, $checkResult );
		$violation = $violations[0];
		$this->assertEquals( '[{"constraint_status":"mandatory"}]', $violation->getAdditionalInfo() );
	}

	public function testMultipleCheckResults() {
		$checkResults = array ();
		$checkResults[ ] = new CheckResult( $this->statement, $this->constraintName, $this->parameters, 'violation', $this->message );
		$checkResults[ ] = new CheckResult( $this->statement, $this->constraintName, $this->parameters, 'violation', $this->message );
		$checkResults[ ] = new CheckResult( $this->statement, $this->constraintName, $this->parameters, 'compliance', $this->message );
		$violations = $this->translator->translateToViolation( $this->entity, $checkResults );
		$this->assertEquals( 2, sizeof( $violations ) );
	}


    private function getEntityRevisionLookupMock() {
        $mock = $this->getMockBuilder( 'Wikibase\Lib\Store\EntityRevisionLookup' )
            ->setMethods( array( 'getLatestRevisionId' ) )
            ->getMockForAbstractClass();
        $mock->expects( $this->any() )
            ->method( 'getLatestRevisionId' )
            ->willReturn( 42 );

        return $mock;
    }
}