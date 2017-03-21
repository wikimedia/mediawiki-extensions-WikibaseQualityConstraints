<?php

namespace WikibaseQuality\ConstraintReport\Tests;

use Wikibase\DataModel\Entity\PropertyId;
use WikibaseQuality\ConstraintReport\ConstraintRepository;

/**
 * @covers WikibaseQuality\ConstraintReport\Constraint
 *
 * @group WikibaseQualityConstraints
 * @group database
 * @group medium
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class ConstraintTest extends \MediaWikiTestCase {

	public function testConstructAndGetters() {
		$repo = new ConstraintRepository();
		$constraints = $repo->queryConstraintsForProperty( new PropertyId( 'P1' ) );

		$this->assertEquals( 'Item', $constraints[0]->getConstraintTypeQid() );
		$this->assertEquals( new PropertyId( 'P1' ), $constraints[0]->getPropertyId() );
		$this->assertEquals( '1', $constraints[0]->getConstraintStatementGuid() );
		$constraintParameters = $constraints[0]->getConstraintParameters();
		$this->assertEquals( 2, count( $constraintParameters ) );
		$this->assertEquals( 'P21', $constraintParameters['property'] );
		$this->assertEquals( 'mandatory', $constraintParameters['constraint_status'] );
	}

	public function addDBData() {
		$this->db->delete( CONSTRAINT_TABLE, '*' );
		$this->db->insert( CONSTRAINT_TABLE, [
			[
				'constraint_guid' => '1',
				'pid' => 1,
				'constraint_type_qid' => 'Item',
				'constraint_parameters' => '{"property":"P21","constraint_status":"mandatory"}'
			],
		] );
	}

}
