<?php

namespace WikibaseQuality\ConstraintReport\Tests\Maintenance;

use MediaWikiTestCase;
use WikibaseQuality\ConstraintReport\Maintenance\UpdateConstraintsTable;

/**
 * @covers \WikibaseQuality\ConstraintReport\Maintenance\UpdateConstraintsTable
 *
 * @group WikibaseQualityConstraints
 * @group Database
 * @group medium
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class UpdateConstraintsTableTest extends MediaWikiTestCase {

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
			// a constraint imported from a statement (statement ID)
			[
				'constraint_guid' => 'P2$2892c48c-53e5-40ef-94a2-274ebf35075c',
				'pid' => 42,
				'constraint_type_qid' => 'TestConstraint',
				'constraint_parameters' => '{}'
			],
		] );
	}

	public function testExecute() {
		$maintenanceScript = new UpdateConstraintsTable();
		$args = [
			'csv-file' => __DIR__ . '/constraints.csv',
			'batch-size' => 2,
			'quiet' => true,
		];
		$maintenanceScript->loadParamsAndArgs( null, $args );
		$maintenanceScript->execute();

		$this->assertSelect(
			CONSTRAINT_TABLE,
			[
				'constraint_guid',
				'pid',
				'constraint_type_qid',
				'constraint_parameters',
			],
			[],
			[
				// existing constraint imported from a statement is not deleted
				[
					'P2$2892c48c-53e5-40ef-94a2-274ebf35075c',
					42,
					'TestConstraint',
					'{}'
				],
				// new constraints
				[
					'c3d49df6-a4f1-413d-903d-57907aa439f9',
					'42',
					'ConstraintFromCsv',
					'{"foo":"bar"}'
				],
				[
					'daa9c35c-0455-4c8d-8804-a73cd78fcc4a',
					'42',
					'ConstraintFromCsv',
					'{"bar":"baz"}'
				],
				[
					'e28b1517-a7f6-4479-bdc8-6687e944ba31',
					'42',
					'ConstraintFromCsv',
					'{"foobar":"bar"}'
				],
				// existing constrant imported from a template is deleted
			]
		);
	}

}
