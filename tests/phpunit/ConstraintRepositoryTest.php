<?php

namespace WikibaseQuality\ConstraintReport\Tests;

use Wikibase\DataModel\Entity\PropertyId;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintRepository;

/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintRepository
 *
 * @group WikibaseQualityConstraints
 * @group database
 * @group medium
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class ConstraintRepositoryTest extends \MediaWikiTestCase {

	public function testQueryConstraintsForExistingProperty() {
		$this->insertTestData();

		$repo = new ConstraintRepository();
		$constraints = $repo->queryConstraintsForProperty( new PropertyId( 'P1' ) );

		$this->assertEquals( true, is_array( $constraints) );
		$this->assertEquals( 2, count( $constraints ) );
		$this->assertInstanceOf( Constraint::class, $constraints[0] );
	}

	public function testQueryConstraintsForNonExistingProperty() {
		$this->insertTestData();

		$repo = new ConstraintRepository();
		$constraints = $repo->queryConstraintsForProperty(  new PropertyId( 'P2' ) );

		$this->assertEquals( true, is_array( $constraints ) );
		$this->assertEquals( 0, count( $constraints ) );
	}

	public function testInsertBatch() {
		$this->insertTestData();

        $constraints = array(
            new Constraint( 'foo', new PropertyId('P42'), 'TestConstraint', array( 'foo' => 'bar' ) ),
            new Constraint( 'bar', new PropertyId('P42'), 'TestConstraint', array( 'bar' => 'baz' ) )
        );
        $repo = new ConstraintRepository();
        $repo->insertBatch( $constraints );

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
				array (
					'1',
					1,
					'Multi value',
					'{}'
				),
				array (
					'3',
					1,
					'Single value',
					'{}'
				),
				array (
					'bar',
					'42',
					'TestConstraint',
					'{"bar":"baz"}'
				),
				array (
					'foo',
					'42',
					'TestConstraint',
					'{"foo":"bar"}'
				)
			)
		);
	}

	public function testDeleteAll() {
		$this->insertTestData();

		$repo = new ConstraintRepository();
		$repo->deleteAll();

		$this->assertSelect(
			CONSTRAINT_TABLE,
			'COUNT(constraint_guid)',
			array(),
			array(
				array( 0 )
			)
		);
	}

	public function insertTestData() {
		$this->db->delete( CONSTRAINT_TABLE, '*' );
		$this->db->insert( CONSTRAINT_TABLE,
			array (
			   array (
					'constraint_guid' => '1',
					'pid' => 1,
					'constraint_type_qid' => 'Multi value',
					'constraint_parameters' => '{}'
			   ),
			   array (
					'constraint_guid' => '3',
					'pid' => 1,
					'constraint_type_qid' => 'Single value',
					'constraint_parameters' => '{}'
				)
			)
		);
	}

}
