<?php

namespace WikibaseQuality\ConstraintReport\Tests\Job;

use HashConfig;
use MediaWikiTestCase;
use Title;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Statement\StatementList;
use WikibaseQuality\ConstraintReport\ConstraintRepository;
use WikibaseQuality\ConstraintReport\Tests\DefaultConfig;
use WikibaseQuality\ConstraintReport\UpdateConstraintsTableJob;
use WikibaseQuality\Tests\Helper\JsonFileEntityLookup;

/**
 * @covers \WikibaseQuality\ConstraintReport\UpdateConstraintsTableJob
 *
 * @uses \WikibaseQuality\ConstraintReport\ConstraintRepository
 *
 * @group WikibaseQualityConstraints
 * @group Database
 * @group medium
 *
 * @author Lucas Werkmeister
 * @license GNU GPL v2+
 */
class UpdateConstraintsTableTest extends MediaWikiTestCase {

	use DefaultConfig;

	protected function setUp() {
		parent::setUp();

		$this->tablesUsed[] = CONSTRAINT_TABLE;
	}

	public function addDBData() {
		$this->db->delete( CONSTRAINT_TABLE, '*' );
		$this->db->insert( CONSTRAINT_TABLE, [
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
				'constraint_type_qid' => $this->getDefaultConfig()->get( 'WBQualityConstraintsSingleValueConstraintId' ),
				'constraint_parameters' => '{}'
			],
			// a constraint imported from a different statement (statement ID)
			[
				'constraint_guid' => 'P3$1926459f-a4d6-42f5-a46e-e1866a2499ed',
				'pid' => 3,
				'constraint_type_qid' => $this->getDefaultConfig()->get( 'WBQualityConstraintsSingleValueConstraintId' ),
				'constraint_parameters' => '{}'
			],
		] );
	}

	public function testExtractConstraintFromStatement_NoParameters() {
		$job = UpdateConstraintsTableJob::newFromGlobalState( Title::newFromText( 'constraintsTableUpdate' ), [ 'propertyId' => 'P2' ] );
		$singleValueId = $this->getDefaultConfig()->get( 'WBQualityConstraintsSingleValueConstraintId' );
		$statementGuid = 'P2$484b7eaf-e86c-4f25-91dc-7ae19f8be8de';
		$statement = new Statement(
			new PropertyValueSnak(
				new PropertyId( $this->getDefaultConfig()->get( 'WBQualityConstraintsPropertyConstraintId' ) ),
				new EntityIdValue( new ItemId( $singleValueId ) )
			)
		);
		$statement->setGuid( $statementGuid );

		$constraint = $job->extractConstraintFromStatement( new PropertyId( 'P2' ), $statement );

		$this->assertEquals( $singleValueId, $constraint->getConstraintTypeQid() );
		$this->assertEquals( new PropertyId( 'P2' ), $constraint->getPropertyId() );
		$this->assertEquals( $statementGuid, $constraint->getConstraintId() );
		$this->assertEquals( [], $constraint->getConstraintParameters() );

		// TODO is there a good way to assert that this function did not touch the database?
	}

	// TODO add test for extractConstraintFromStatement with parameters once that’s implemented

	public function testImportConstraintsForProperty() {
		$job = UpdateConstraintsTableJob::newFromGlobalState( Title::newFromText( 'constraintsTableUpdate' ), [ 'propertyId' => 'P2' ] );
		$singleValueId = new ItemId( $this->getDefaultConfig()->get( 'WBQualityConstraintsSingleValueConstraintId' ) );
		$propertyConstraintId = new PropertyId( $this->getDefaultConfig()->get( 'WBQualityConstraintsPropertyConstraintId' ) );
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
			new ConstraintRepository(),
			$propertyConstraintId
		);

		$this->assertSelect(
			CONSTRAINT_TABLE,
			[
				'constraint_guid',
				'pid',
				'constraint_type_qid',
				'constraint_parameters'
			],
			[],
			[
				// constraint previously imported from the statement under test is still there
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
				// constraint imported from a different statement is still there
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

	public function testRun() {
		$job = new UpdateConstraintsTableJob(
			Title::newFromText( 'constraintsTableUpdate' ),
			[],
			'P2',
			$this->getDefaultConfig(),
			new ConstraintRepository(),
			new JsonFileEntityLookup( __DIR__ )
		);

		$job->run();

		$this->assertSelect(
			CONSTRAINT_TABLE,
			[
				'constraint_guid',
				'pid',
				'constraint_type_qid',
				'constraint_parameters'
			],
			[],
			[
				// constraint previously imported from the statement under test was removed
				// new constraint imported from the statement under test is there
				[
					'P2$484b7eaf-e86c-4f25-91dc-7ae19f8be8de',
					'2',
					'Q19474404',
					'{}'
				],
				// constraint imported from a different statement is still there
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

}
