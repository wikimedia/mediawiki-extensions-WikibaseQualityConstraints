<?php

namespace WikibaseQuality\ConstraintReport\Tests\Job;

use DataValues\TimeValue;
use DataValues\UnboundedQuantityValue;
use MediaWiki\MediaWikiServices;
use MediaWikiTestCase;
use Title;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Snak\PropertySomeValueSnak;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Snak\SnakList;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Statement\StatementList;
use Wikibase\Lib\Store\EntityRevision;
use Wikibase\Lib\Store\EntityRevisionLookup;
use Wikibase\Lib\Store\LookupConstants;
use Wikibase\Lib\Tests\Store\Sql\Terms\Util\FakeLoadBalancer;
use Wikibase\Repo\Tests\NewStatement;
use Wikibase\Repo\WikibaseRepo;
use WikibaseQuality\ConstraintReport\ConstraintRepositoryStore;
use WikibaseQuality\ConstraintReport\ConstraintsServices;
use WikibaseQuality\ConstraintReport\Job\UpdateConstraintsTableJob;
use WikibaseQuality\ConstraintReport\Tests\DefaultConfig;

/**
 * @covers WikibaseQuality\ConstraintReport\Job\UpdateConstraintsTableJob
 *
 * @group WikibaseQualityConstraints
 * @group Database
 * @group medium
 *
 * @author Lucas Werkmeister
 * @license GPL-2.0-or-later
 */
class UpdateConstraintsTableJobTest extends MediaWikiTestCase {

	use DefaultConfig;

	protected function setUp() : void {
		parent::setUp();

		MediaWikiServices::getInstance()->resetServiceForTesting( ConstraintsServices::CONSTRAINT_LOOKUP );
		$this->tablesUsed[] = 'wbqc_constraints';
	}

	protected function tearDown() : void {
		MediaWikiServices::getInstance()->resetServiceForTesting( ConstraintsServices::CONSTRAINT_LOOKUP );
		parent::tearDown();
	}

	public function addDBData() {
		$config = $this->getDefaultConfig();
		$this->db->delete( 'wbqc_constraints', '*' );
		$this->db->insert( 'wbqc_constraints', [
			// a constraint imported from a template (UUID)
			[
				'constraint_guid' => 'afbbe0c2-2bc4-47b6-958c-a318a53814ac',
				'pid' => 42,
				'constraint_type_qid' => 'TestConstraint',
				'constraint_parameters' => '{}'
			],
			// a constraint imported from the statement under test (statement ID)
			[
				'constraint_guid' => 'P2$2892c48c-53e5-40ef-94a2-274ebf35075c',
				'pid' => 2,
				'constraint_type_qid' => $config->get( 'WBQualityConstraintsSingleValueConstraintId' ),
				'constraint_parameters' => '{}'
			],
			// a constraint imported from a different statement (statement ID)
			[
				'constraint_guid' => 'P3$1926459f-a4d6-42f5-a46e-e1866a2499ed',
				'pid' => 3,
				'constraint_type_qid' => $config->get( 'WBQualityConstraintsSingleValueConstraintId' ),
				'constraint_parameters' => '{}'
			],
		] );
	}

	public function testExtractParametersFromQualifiers() {
		$job = UpdateConstraintsTableJob::newFromGlobalState(
			Title::newFromText( 'constraintsTableUpdate' ),
			[ 'propertyId' => 'P2' ]
		);
		$class1 = new EntityIdValue( new ItemId( 'Q5' ) );
		$class2 = new EntityIdValue( new ItemId( 'Q15632617' ) );
		$quantity = UnboundedQuantityValue::newFromNumber( 50, 'kg' );
		$date = $this->getTimeValue( '2000-01-01' );
		$snakP2308A = new PropertyValueSnak(
			new PropertyId( 'P2308' ),
			$class1
		);
		$snakP1646 = new PropertyNoValueSnak(
			new PropertyId( 'P1646' )
		);
		$snakP2308B = new PropertyValueSnak(
			new PropertyId( 'P2308' ),
			$class2
		);
		$snakP2313 = new PropertyValueSnak(
			new PropertyId( 'P2313' ),
			$quantity
		);
		$snakP2310 = new PropertyValueSnak(
			new PropertyId( 'P2310' ),
			$date
		);
		$snakP2305 = new PropertySomeValueSnak(
			new PropertyId( 'P2305' )
		);
		$qualifiers = new SnakList( [
			$snakP2308A,
			$snakP1646,
			$snakP2308B,
			$snakP2313,
			$snakP2310,
			$snakP2305,
		] );
		$parameters = $job->extractParametersFromQualifiers( $qualifiers );
		$deserializer = WikibaseRepo::getDefaultInstance()
			->getBaseDataModelDeserializerFactory()
			->newSnakDeserializer();
		$this->assertEquals( $snakP2308A, $deserializer->deserialize( $parameters['P2308'][0] ) );
		$this->assertEquals( $snakP1646, $deserializer->deserialize( $parameters['P1646'][0] ) );
		$this->assertEquals( $snakP2308B, $deserializer->deserialize( $parameters['P2308'][1] ) );
		$this->assertEquals( $snakP2313, $deserializer->deserialize( $parameters['P2313'][0] ) );
		$this->assertEquals( $snakP2310, $deserializer->deserialize( $parameters['P2310'][0] ) );
		$this->assertEquals( $snakP2305, $deserializer->deserialize( $parameters['P2305'][0] ) );
	}

	public function testExtractConstraintFromStatement_NoParameters() {
		$config = $this->getDefaultConfig();
		$job = UpdateConstraintsTableJob::newFromGlobalState(
			Title::newFromText( 'constraintsTableUpdate' ),
			[ 'propertyId' => 'P2' ]
		);
		$singleValueId = $config->get( 'WBQualityConstraintsSingleValueConstraintId' );
		$statementGuid = 'P2$484b7eaf-e86c-4f25-91dc-7ae19f8be8de';
		$statement = new Statement(
			new PropertyValueSnak(
				new PropertyId( $config->get( 'WBQualityConstraintsPropertyConstraintId' ) ),
				new EntityIdValue( new ItemId( $singleValueId ) )
			)
		);
		$statement->setGuid( $statementGuid );

		$constraint = $job->extractConstraintFromStatement( new PropertyId( 'P2' ), $statement );

		$this->assertEquals( $singleValueId, $constraint->getConstraintTypeItemId() );
		$this->assertEquals( new PropertyId( 'P2' ), $constraint->getPropertyId() );
		$this->assertEquals( $statementGuid, $constraint->getConstraintId() );
		$this->assertEquals( [], $constraint->getConstraintParameters() );

		// TODO is there a good way to assert that this function did not touch the database?
	}

	public function testExtractConstraintFromStatement_Parameters() {
		$job = UpdateConstraintsTableJob::newFromGlobalState(
			Title::newFromText( 'constraintsTableUpdate' ),
			[ 'propertyId' => 'P2' ]
		);

		$config = $this->getDefaultConfig();
		$propertyConstraintId = $config->get( 'WBQualityConstraintsPropertyConstraintId' );
		$typeId = $config->get( 'WBQualityConstraintsTypeConstraintId' );
		$classId = $config->get( 'WBQualityConstraintsClassId' );
		$relationId = $config->get( 'WBQualityConstraintsRelationId' );
		$instanceOfRelationId = $config->get( 'WBQualityConstraintsInstanceOfRelationId' );

		$classHumanSnak = new PropertyValueSnak(
			new PropertyId( $classId ),
			new EntityIdValue( new ItemId( 'Q5' ) )
		);
		$classFictionalHumanSnak = new PropertyValueSnak(
			new PropertyId( $classId ),
			new EntityIdValue( new ItemId( 'Q15632617' ) )
		);
		$relationInstanceOfSnak = new PropertyValueSnak(
			new PropertyId( $relationId ),
			new EntityIdValue( new ItemId( $instanceOfRelationId ) )
		);

		$statementGuid = 'P2$e95e1eb9-eaa5-48d1-a3d6-0b34fc5d3cd0';
		$statement = new Statement(
			new PropertyValueSnak(
				new PropertyId( $propertyConstraintId ),
				new EntityIdValue( new ItemId( $typeId ) )
			),
			new SnakList( [ $classHumanSnak, $classFictionalHumanSnak, $relationInstanceOfSnak ] ),
			null,
			$statementGuid
		);

		$constraint = $job->extractConstraintFromStatement( new PropertyId( 'P2' ), $statement );

		$snakSerializer = WikibaseRepo::getDefaultInstance()
			->getBaseDataModelSerializerFactory()
			->newSnakSerializer();
		$this->assertEquals( $typeId, $constraint->getConstraintTypeItemId() );
		$this->assertEquals( new PropertyId( 'P2' ), $constraint->getPropertyId() );
		$this->assertEquals( $statementGuid, $constraint->getConstraintId() );
		$this->assertSame(
			[
				$classId => [
					$snakSerializer->serialize( $classHumanSnak ),
					$snakSerializer->serialize( $classFictionalHumanSnak )
				],
				$relationId => [
					$snakSerializer->serialize( $relationInstanceOfSnak )
				]
			],
			$constraint->getConstraintParameters()
		);
	}

	public function testImportConstraintsForProperty() {
		$config = $this->getDefaultConfig();
		$job = UpdateConstraintsTableJob::newFromGlobalState(
			Title::newFromText( 'constraintsTableUpdate' ),
			[ 'propertyId' => 'P2' ]
		);
		$singleValueId = new ItemId( $config->get( 'WBQualityConstraintsSingleValueConstraintId' ) );
		$propertyConstraintId = new PropertyId( $config->get( 'WBQualityConstraintsPropertyConstraintId' ) );
		$statementGuid = 'P2$484b7eaf-e86c-4f25-91dc-7ae19f8be8de';
		$statement = new Statement(
			new PropertyValueSnak(
				$propertyConstraintId,
				new EntityIdValue( $singleValueId )
			)
		);
		$statement->setGuid( $statementGuid );
		$property = new Property(
			new PropertyId( 'P2' ),
			null, '',
			new StatementList( [ $statement ] )
		);

		$job->importConstraintsForProperty(
			$property,
			new ConstraintRepositoryStore( new FakeLoadBalancer( [ 'dbr' => $this->db ] ), false ),
			$propertyConstraintId
		);

		$this->assertSelect(
			'wbqc_constraints',
			[
				'constraint_guid',
				'pid',
				'constraint_type_qid',
				'constraint_parameters'
			],
			[],
			[
				// constraint previously imported from the property under test is still there
				[
					'P2$2892c48c-53e5-40ef-94a2-274ebf35075c',
					'2',
					$singleValueId->getSerialization(),
					'{}'
				],
				// new constraint imported from the statement under test is there
				[
					$statementGuid,
					'2',
					$singleValueId->getSerialization(),
					'{}'
				],
				// constraint imported from a different property is still there
				[
					'P3$1926459f-a4d6-42f5-a46e-e1866a2499ed',
					'3',
					$singleValueId->getSerialization(),
					'{}'
				],
				// constraint imported from a template is still there
				[
					'afbbe0c2-2bc4-47b6-958c-a318a53814ac',
					'42',
					'TestConstraint',
					'{}'
				],
			]
		);
	}

	public function testImportConstraintsForProperty_Deprecated() {
		$config = $this->getDefaultConfig();
		$propertyConstraintId = $config->get( 'WBQualityConstraintsPropertyConstraintId' );
		$usedForValuesOnlyId = $config->get( 'WBQualityConstraintsUsedForValuesOnlyConstraintId' );
		$usedAsQualifierId = $config->get( 'WBQualityConstraintsUsedAsQualifierConstraintId' );
		$usedAsReferenceId = $config->get( 'WBQualityConstraintsUsedAsReferenceConstraintId' );
		$preferredConstraintStatement = NewStatement::forProperty( $propertyConstraintId )
			->withValue( new ItemId( $usedForValuesOnlyId ) )
			->withPreferredRank()
			->build();
		$normalConstraintStatement = NewStatement::forProperty( $propertyConstraintId )
			->withValue( new ItemId( $usedAsQualifierId ) )
			->withNormalRank()
			->build();
		$deprecatedConstraintStatement = NewStatement::forProperty( $propertyConstraintId )
			->withValue( new ItemId( $usedAsReferenceId ) )
			->withDeprecatedRank()
			->build();
		$property = new Property(
			new PropertyId( 'P3' ),
			null,
			'string',
			new StatementList( [
				$preferredConstraintStatement,
				$normalConstraintStatement,
				$deprecatedConstraintStatement
			] )
		);
		$entityRevisionLookup = $this->createMock( EntityRevisionLookup::class );
		$entityRevisionLookup->method( 'getEntityRevision' )
			->with( $property->getId(), 0, LookupConstants::LATEST_FROM_REPLICA )
			->willReturn( new EntityRevision( $property ) );

		$constraintRepository = $this->getMockBuilder( ConstraintRepositoryStore::class )
			->disableOriginalConstructor()
			->getMock();
		$constraintRepository->expects( $this->once() )
			->method( 'insertBatch' )
			->with( $this->callback(
				function( array $constraints ) use ( $usedForValuesOnlyId, $usedAsQualifierId ) {
					$this->assertCount( 2, $constraints );
					$this->assertSame( $usedForValuesOnlyId, $constraints[0]->getConstraintTypeItemId() );
					$this->assertSame( $usedAsQualifierId, $constraints[1]->getConstraintTypeItemId() );
					return true;
				}
			) );

		$job = new UpdateConstraintsTableJob(
			Title::newFromText( 'constraintsTableUpdate' ),
			[],
			'P3',
			null,
			$config,
			$constraintRepository,
			$entityRevisionLookup,
			WikibaseRepo::getDefaultInstance()->getBaseDataModelSerializerFactory()->newSnakSerializer()
		);
		$job->importConstraintsForProperty(
			$property,
			$constraintRepository,
			new PropertyId( $propertyConstraintId )
		);
	}

	public function testRun() {
		$config = $this->getDefaultConfig();
		$propertyConstraintId = $config->get( 'WBQualityConstraintsPropertyConstraintId' );
		$singleValueConstraintId = $config->get( 'WBQualityConstraintsSingleValueConstraintId' );
		$property = new Property(
			new PropertyId( 'P2' ),
			null,
			'wikibase-item',
			new StatementList(
				NewStatement::forProperty( $propertyConstraintId )
					->withValue( new ItemId( $singleValueConstraintId ) )
					->withGuid( 'P2$484b7eaf-e86c-4f25-91dc-7ae19f8be8de' )
					->build()
			)
		);
		$entityRevisionLookup = $this->createMock( EntityRevisionLookup::class );
		$entityRevisionLookup->method( 'getEntityRevision' )
			->with( $property->getId(), 0, LookupConstants::LATEST_FROM_REPLICA )
			->willReturn( new EntityRevision( $property ) );

		$job = new UpdateConstraintsTableJob(
			Title::newFromText( 'constraintsTableUpdate' ),
			[],
			'P2',
			null,
			$this->getDefaultConfig(),
			new ConstraintRepositoryStore( new FakeLoadBalancer( [ 'dbr' => $this->db ] ), false ),
			$entityRevisionLookup,
			WikibaseRepo::getDefaultInstance()->getBaseDataModelSerializerFactory()->newSnakSerializer()
		);

		$job->run();

		$this->assertSelect(
			'wbqc_constraints',
			[
				'constraint_guid',
				'pid',
				'constraint_type_qid',
				'constraint_parameters'
			],
			[],
			[
				// constraint previously imported from the property under test was removed
				// new constraint imported from the statement under test is there
				[
					'P2$484b7eaf-e86c-4f25-91dc-7ae19f8be8de',
					'2',
					'Q19474404',
					'{}'
				],
				// constraint imported from a different property is still there
				[
					'P3$1926459f-a4d6-42f5-a46e-e1866a2499ed',
					'3',
					'Q19474404',
					'{}'
				],
				// constraint imported from a template is still there
				[
					'afbbe0c2-2bc4-47b6-958c-a318a53814ac',
					'42',
					'TestConstraint',
					'{}'
				],
			]
		);
	}

	private function getTimeValue( $date ): TimeValue {
		return new TimeValue(
			"+{$date}T00:00:00Z",
			0,
			0,
			0,
			TimeValue::PRECISION_DAY,
			TimeValue::CALENDAR_GREGORIAN
		);
	}

}
