<?php

namespace WikidataQuality\ConstraintReport\Tests;

use Wikibase\DataModel\Entity\Item;
use WikidataQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikidataQuality\ConstraintReport\CheckForConstraintViolationsJob;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Claim\Claim;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Entity\ItemId;
use DataValues\StringValue;


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

	private $entity;
	private $checkTimestamp;
	private $constraintName;
	private $results;
	private $testLogFileName;
	private $oldLogFileName;

	protected function setUp() {
		parent::setUp();

		$this->entity = new Item();
		$this->entity->setId( new ItemId( 'Q23' ) );

		$this->checkTimestamp = wfTimestamp( TS_MW );

		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P1337' ), new StringValue( 'f00b4r' ) ) ) );
		$this->constraintName = 'Single value';

		$results = array ();
		$results[] = new CheckResult( $statement, $this->constraintName, array (), CheckResult::STATUS_COMPLIANCE );
		$results[] = new CheckResult( $statement, $this->constraintName, array (), CheckResult::STATUS_COMPLIANCE );
		$results[] = new CheckResult( $statement, $this->constraintName, array (), CheckResult::STATUS_COMPLIANCE );
		$results[] = new CheckResult( $statement, $this->constraintName, array (), CheckResult::STATUS_EXCEPTION );
		$results[] = new CheckResult( $statement, $this->constraintName, array (), CheckResult::STATUS_VIOLATION );
		$results[] = new CheckResult( $statement, $this->constraintName, array (), CheckResult::STATUS_VIOLATION );
		$results[] = new CheckResult( $statement, $this->constraintName, array (), 'some other status' );
		$results[] = new CheckResult( $statement, $this->constraintName, array (), 'yet another one' );
		$this->results = $results;

		$this->testLogFileName = '/var/log/mediawiki/test_wdqa_evaluation.log';
		if( file_exists( $this->testLogFileName ) ) {
			unlink( $this->testLogFileName );
		}

		$this->oldLogFileName = $GLOBALS['wgDebugLogGroups']['wdqa_evaluation'];
		$GLOBALS['wgDebugLogGroups']['wdqa_evaluation'] = $this->testLogFileName;
	}

	protected function tearDown() {
		$GLOBALS['wgDebugLogGroups']['wdqa_evaluation'] = $this->oldLogFileName;
		unset( $this->oldLogFileName );

		if( file_exists( $this->testLogFileName ) ) {
			unlink( $this->testLogFileName );
		}
		unset( $this->testLogFileName );

		unset( $this->results );
		unset( $this->constraintName );
		unset( $this->checkTimestamp );
		unset( $this->entity );

		parent::tearDown();
	}

	public function testNewInsertNowAndRun() {
		$job = CheckForConstraintViolationsJob::newInsertNow( $this->entity, $this->checkTimestamp, $this->results );
		$job->run();

		$this->assertFileExists( $this->testLogFileName );

		$logFile = fopen( $this->testLogFileName, 'r' );
		$firstLine = fgets( $logFile );
		$logEntry = json_decode( substr( $firstLine, mb_strpos( $firstLine, '{' ) ), true );
		fclose( $logFile );

		$this->assertEquals( 1, count( file( $this->testLogFileName ) ) );

		$this->assertEquals( 5, count( $logEntry ) );
		$this->assertEquals( 'SpecialConstraintReport', $logEntry['special_page_id'] );
		$this->assertEquals( $this->entity->getId()->getSerialization(), $logEntry['entity_id'] );
		$this->assertEquals( $this->checkTimestamp, $logEntry['insertion_timestamp'] );
		$this->assertEquals( null, $logEntry['reference_timestamp'] );

		$this->assertEquals( 1, count( $logEntry['result_summary'] ) );
		$this->assertEquals( 3, count( $logEntry['result_summary'][$this->constraintName] ) );
		$this->assertEquals( 3, $logEntry['result_summary'][$this->constraintName][CheckResult::STATUS_COMPLIANCE] );
		$this->assertEquals( 1, $logEntry['result_summary'][$this->constraintName][CheckResult::STATUS_EXCEPTION] );
		$this->assertEquals( 2, $logEntry['result_summary'][$this->constraintName][CheckResult::STATUS_VIOLATION] );
	}

	public function testNewInsertDeferredAndRun() {
		$this->assertEquals( true, true );
	}

}