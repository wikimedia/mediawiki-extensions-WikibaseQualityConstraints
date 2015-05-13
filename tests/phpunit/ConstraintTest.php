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
		$this->assertEquals( 1, $constraints[0]->getPropertyId() );
		$this->assertEquals( '1', $constraints[0]->getConstraintClaimGuid() );
		$constraintParameters = $constraints[0]->getConstraintParameters();
		$this->assertEquals( 2, count( $constraintParameters ) );
		$this->assertEquals( 'P21', $constraintParameters['property'] );
		$this->assertEquals( 'mandatory', $constraintParameters['constraint_status'] );
	}



	public function addDBData() {
		$this->db->delete( CONSTRAINT_TABLE, '*' );
		$this->db->insert( CONSTRAINT_TABLE, array (
		   array (
			   'constraint_guid' => '1',
			   'pid' => 1,
			   'constraint_type_qid' => 'Item',
			   'constraint_parameters' => '{"property":"P21","constraint_status":"mandatory"}'
		   ) )
		);
	}

}
