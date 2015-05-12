<?php

namespace WikidataQuality\ConstraintReport\Tests;

use WikidataQuality\ConstraintReport\Constraint;
use WikidataQuality\ConstraintReport\ConstraintRepository;


/**
 * @covers WikidataQuality\ConstraintReport\ConstraintRepository
 *
 * @group database
 * @group medium
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class ConstraintRepositoryTest extends \MediaWikiTestCase {

	public function testQueryConstraintsForExistingProperty() {
		$repo = new ConstraintRepository();
		$constraints = $repo->queryConstraintsForProperty( 1 );

		$this->assertEquals( true, is_array( $constraints) );
		$this->assertEquals( 2, count( $constraints ) );
		$this->assertEquals( 'WikidataQuality\ConstraintReport\Constraint', get_class( $constraints[0] ) );
	}

	public function testQueryConstraintsForNonExistingProperty() {
		$repo = new ConstraintRepository();
		$constraints = $repo->queryConstraintsForProperty( 2 );

		$this->assertEquals( true, is_array( $constraints ) );
		$this->assertEquals( 0, count( $constraints ) );
	}

	public function addDBData() {
		$this->db->delete( CONSTRAINT_TABLE, '*' );
		$this->db->insert( CONSTRAINT_TABLE, array (
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
		   ) )
		);
	}

}
