<?php

namespace WikibaseQuality\ConstraintReport\Tests;

use Wikibase\DataModel\Entity\NumericPropertyId;
use Wikibase\Lib\Tests\Store\Sql\Terms\Util\FakeLoadBalancer;
use WikibaseQuality\ConstraintReport\ConstraintRepositoryLookup;

/**
 * @covers WikibaseQuality\ConstraintReport\Constraint
 *
 * @group WikibaseQualityConstraints
 * @group Database
 * @group medium
 *
 * @author BP2014N1
 * @license GPL-2.0-or-later
 */
class ConstraintTest extends \MediaWikiIntegrationTestCase {

	public function testConstructAndGetters() {
		$repo = new ConstraintRepositoryLookup(
			new FakeLoadBalancer( [ 'dbr' => $this->db ] ),
			false
		);
		$constraints = $repo->queryConstraintsForProperty( new NumericPropertyId( 'P1' ) );

		$this->assertEquals( 'Item', $constraints[0]->getConstraintTypeItemId() );
		$this->assertEquals( new NumericPropertyId( 'P1' ), $constraints[0]->getPropertyId() );
		$this->assertSame( '1', $constraints[0]->getConstraintId() );
		$constraintParameters = $constraints[0]->getConstraintParameters();
		$this->assertCount( 2, $constraintParameters );
		$this->assertSame( 'P21', $constraintParameters['property'] );
		$this->assertSame( 'mandatory', $constraintParameters['constraint_status'] );
	}

	public function addDBData() {
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'wbqc_constraints' )
			->row( [
				'constraint_guid' => '1',
				'pid' => 1,
				'constraint_type_qid' => 'Item',
				'constraint_parameters' => '{"property":"P21","constraint_status":"mandatory"}',
			] )
			->caller( __METHOD__ )
			->execute();
	}

}
