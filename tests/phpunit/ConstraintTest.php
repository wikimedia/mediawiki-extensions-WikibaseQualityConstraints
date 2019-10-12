<?php

namespace WikibaseQuality\ConstraintReport\Tests;

use MediaWiki\MediaWikiServices;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\Lib\Tests\Store\Sql\Terms\Util\FakeLoadBalancer;
use WikibaseQuality\ConstraintReport\ConstraintRepository;
use WikibaseQuality\ConstraintReport\ConstraintsServices;

/**
 * @covers WikibaseQuality\ConstraintReport\Constraint
 *
 * @group WikibaseQualityConstraints
 * @group database
 * @group medium
 *
 * @author BP2014N1
 * @license GPL-2.0-or-later
 */
class ConstraintTest extends \MediaWikiTestCase {

	protected function setUp() : void {
		parent::setUp();
		MediaWikiServices::getInstance()->resetServiceForTesting( ConstraintsServices::CONSTRAINT_LOOKUP );
	}

	protected function tearDown() : void {
		MediaWikiServices::getInstance()->resetServiceForTesting( ConstraintsServices::CONSTRAINT_LOOKUP );
		parent::tearDown();
	}

	public function testConstructAndGetters() {
		$repo = new ConstraintRepository( new FakeLoadBalancer( [ 'dbr' => $this->db ] ) );
		$constraints = $repo->queryConstraintsForProperty( new PropertyId( 'P1' ) );

		$this->assertEquals( 'Item', $constraints[0]->getConstraintTypeItemId() );
		$this->assertEquals( new PropertyId( 'P1' ), $constraints[0]->getPropertyId() );
		$this->assertEquals( '1', $constraints[0]->getConstraintId() );
		$constraintParameters = $constraints[0]->getConstraintParameters();
		$this->assertEquals( 2, count( $constraintParameters ) );
		$this->assertEquals( 'P21', $constraintParameters['property'] );
		$this->assertEquals( 'mandatory', $constraintParameters['constraint_status'] );
	}

	public function addDBData() {
		$this->db->delete( 'wbqc_constraints', '*' );
		$this->db->insert( 'wbqc_constraints', [
			[
				'constraint_guid' => '1',
				'pid' => 1,
				'constraint_type_qid' => 'Item',
				'constraint_parameters' => '{"property":"P21","constraint_status":"mandatory"}'
			],
		] );
	}

}
