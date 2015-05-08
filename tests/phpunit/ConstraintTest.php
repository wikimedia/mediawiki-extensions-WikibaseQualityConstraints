<?php

namespace WikidataQuality\ConstraintReport\Tests;

use WikidataQuality\ConstraintReport\ConstraintRepository;


/**
 * @covers WikidataQuality\ConstraintReport\Constraint
 *
 * @group database
 * @group medium
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class ConstraintTest extends \MediaWikiTestCase {

	public function testConstructAndGetters() {
		$repo = new ConstraintRepository();
		$constraints = $repo->queryConstraintsForProperty( 1 );

		$this->assertEquals( 'Item', $constraints[0]->getConstraintTypeQid() );
		$this->assertEquals( array( 'property' => array( 'P1' ), 'exceptions' => array( '' ), 'item' => array( '' ) ), $constraints[0]->getConstraintParameter() );
	}



	public function addDBData() {
		$this->db->delete( CONSTRAINT_TABLE, '*' );
		$this->db->insert( CONSTRAINT_TABLE, array (
		   array (
			   'constraint_guid' => '1',
			   'pid' => 1,
			   'constraint_type_qid' => 'Item',
			   'constraint_parameters' => '{"property": "P1"}'
		   ) )
		);
	}

}
