<?php

namespace WikidataQuality\ConstraintReport\Tests;

use Wikibase\DataModel\Entity\Item;
use WikidataQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikidataQuality\ConstraintReport\CheckForConstraintViolationsJob;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Claim\Claim;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Entity\PropertyId;
use DataValues\StringValue;
use Wikibase\Repo\WikibaseRepo;


/**
 * @covers WikidataQuality\ConstraintReport\CheckForConstraintViolationsJob
 *
 * @group Database
 *
 * @uses   WikidataQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 * @uses   WikidataQuality\ConstraintReport\ConstraintCheck\ConstraintChecker
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class CheckForConstraintViolationsJobTest extends \MediaWikiTestCase {

	private $results;
	private $entity;
	private $checkTimestamp;

	protected function setUp() {
		parent::setUp();

		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P188' ), new StringValue( 'foo' ) ) ) );
		$constraintName = 'Single value';
		$results = array ();
		$results[ ] = new CheckResult( $statement, $constraintName, array (), CheckResult::STATUS_VIOLATION );
		$results[ ] = new CheckResult( $statement, $constraintName, array (), CheckResult::STATUS_VIOLATION );
		$results[ ] = new CheckResult( $statement, $constraintName, array (), CheckResult::STATUS_COMPLIANCE );
		$results[ ] = new CheckResult( $statement, $constraintName, array (), CheckResult::STATUS_COMPLIANCE );
		$results[ ] = new CheckResult( $statement, $constraintName, array (), CheckResult::STATUS_EXCEPTION );
		$results[ ] = new CheckResult( $statement, $constraintName, array (), CheckResult::STATUS_EXCEPTION );
		$this->results = $results;

		$this->checkTimestamp = wfTimestamp( TS_MW );

		// specify database tables used by this test
		$this->tablesUsed[ ] = EVALUATION_TABLE;
	}

	protected function tearDown() {
		unset( $this->results );
		unset( $this->entity );
		unset( $this->checkTimestamp );
		parent::tearDown();
	}

	public function addDBData() {
		$this->db->delete(
			EVALUATION_TABLE,
			'*'
		);

		$this->entity = new Item();
		$store = WikibaseRepo::getDefaultInstance()->getEntityStore();
		$store->saveEntity( $this->entity, 'TestEntityQ1', $GLOBALS[ 'wgUser' ], EDIT_NEW );
	}

	public function testCheckForConstraintViolationJobNow() {
		$job = CheckForConstraintViolationsJob::newInsertNow( $this->entity, $this->checkTimestamp, $this->results );
		$job->run();
		$count = $this->db->select( EVALUATION_TABLE, array ( 'special_page_id' ), array ( 'special_page_id=1' ) )->numRows();
		$result = $this->db->selectRow( EVALUATION_TABLE, array ( 'result_string' ), array ( 'special_page_id=1' ) );
		$this->assertEquals( 1, $count );
		$this->assertEquals( '{Compliances: {Single value: 2, }, {Violations: {Single value: 2, }, {Exceptions: {Single value: 2, }, ', $result->result_string );
	}

	public function testCheckForConstraintViolationJobDeferred() {
		$job = CheckForConstraintViolationsJob::newInsertDeferred( $this->entity, $this->checkTimestamp, 10 );
		$job->run();
		$count = $this->db->select( EVALUATION_TABLE, array ( 'special_page_id' ), array ( 'special_page_id=1' ) )->numRows();
		$this->assertEquals( 1, $count );
	}

}
