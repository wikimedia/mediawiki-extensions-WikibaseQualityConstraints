<?php

namespace WikibaseQuality\ConstraintReport\Tests\Maintenance;

use WikibaseQuality\ConstraintReport\Maintenance\UpdateConstraintsTable;

/**
 * @covers WikibaseQuality\ConstraintReport\Maintenance\UpdateConstraintsTable
 *
 * @group WikibaseQualityConstraints
 * @group Database
 * @group medium
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class UpdateConstraintsTableTest extends \MediaWikiTestCase {

	protected function setup() {
		parent::setup();

		$this->tablesUsed[] = CONSTRAINT_TABLE;
	}

	public function addDBData() {
		$this->db->delete( CONSTRAINT_TABLE, '*' );
		$this->db->insert( CONSTRAINT_TABLE,
			array(
				array(
					'constraint_guid' => 'foo',
					'pid' => 42,
					'constraint_type_qid' => 'TestConstraint',
					'constraint_parameters' => '{}'
				),
				array(
					'constraint_guid' => 'bar',
					'pid' => 42,
					'constraint_type_qid' => 'TestConstraint',
					'constraint_parameters' => '{}'
				)
			)
		);
	}

	public function testExecute() {
		$maintenanceScript = new UpdateConstraintsTable();
		$args = array(
			'csv-file' => __DIR__ . '/constraints.csv',
			'batch-size' => 2,
			'quiet' => true
		);
		$maintenanceScript->loadParamsAndArgs( null, $args );
		$maintenanceScript->execute();

		$this->assertSelect(
			CONSTRAINT_TABLE,
			array(
				'constraint_guid',
				'pid',
				'constraint_type_qid',
				'constraint_parameters'
			),
			array(),
			array(
				array(
					'baz',
					'42',
					'ConstraintFromCsv',
					'{"foo":"bar"}'
				),
				array(
					'foobar',
					'42',
					'ConstraintFromCsv',
					'{"foobar":"bar"}'
				),
				array(
					'foobaz',
					'42',
					'ConstraintFromCsv',
					'{"bar":"baz"}'
				)
			)
		);
	}

}
