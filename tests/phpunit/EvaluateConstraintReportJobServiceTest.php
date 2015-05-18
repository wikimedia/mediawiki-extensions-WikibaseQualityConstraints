<?php

namespace WikibaseQuality\ConstraintReport\Tests;

use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Claim\Claim;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Entity\ItemId;
use DataValues\StringValue;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikibaseQuality\ConstraintReport\EvaluateConstraintReportJobService;


/**
 * @covers WikibaseQuality\ConstraintReport\EvaluateConstraintReportJobService
 *
 * @uses   WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 * @uses   WikibaseQuality\ConstraintReport\ConstraintCheck\DelegatingConstraintChecker
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class EvaluateConstraintReportJobServiceTest extends \MediaWikiTestCase {

	private $entityId;
	private $checkTimestamp;
	private $constraintName;
	private $results;
	private $params;

	protected function setUp() {
		parent::setUp();

		$this->entityId = new ItemId( 'Q23' );

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
		$results[] = new CheckResult( $statement, $this->constraintName, array () );
		$results[] = new CheckResult( $statement, $this->constraintName, array () );
		$this->results = $results;

		$this->params = array( 'entityId' => $this->entityId->getSerialization(), 'referenceTimestamp' => null, 'results' => $results );

	}

	protected function tearDown() {

		unset( $this->results );
		unset( $this->constraintName );
		unset( $this->checkTimestamp );
		unset( $this->entityId );
		unset( $this->params );

		parent::tearDown();
	}
#
	public function testBuildResultSummary() {
		$service = new EvaluateConstraintReportJobService();
		$this->assertEquals( '{"Single value":{"compliance":3,"violation":2,"exception":1,"todo":2}}', $service->buildResultSummary( $this->results ) );
	}

	public function testBuildMessageForLog() {
		$service = new EvaluateConstraintReportJobService();
		$messageToLog = (array) json_decode( $service->buildMessageForLog( '{"Single value":{"compliance":3,"violation":2,"exception":1,"todo":2}}', $this->checkTimestamp, $this->params ) );

		$this->assertEquals( 5, count( $messageToLog ) );
		$this->assertEquals( 'SpecialConstraintReport', $messageToLog['special_page_id'] );
		$this->assertEquals( $this->entityId->getSerialization(), $messageToLog['entity_id'] );
		$this->assertEquals( $this->checkTimestamp, $messageToLog['insertion_timestamp'] );
		$this->assertEquals( null, $messageToLog['reference_timestamp'] );

		$resultSummary = (array) json_decode( $messageToLog['result_summary'] );

		$this->assertEquals( 1, count( $resultSummary ) );
		$resultForConstraint = (array) $resultSummary[$this->constraintName];
		$this->assertEquals( 4, count( $resultForConstraint ) );
		$this->assertEquals( 3, $resultForConstraint[CheckResult::STATUS_COMPLIANCE], 'Compliance' );
		$this->assertEquals( 1, $resultForConstraint[CheckResult::STATUS_EXCEPTION], 'Exception' );
		$this->assertEquals( 2, $resultForConstraint[CheckResult::STATUS_VIOLATION], 'Violation' );
		$this->assertEquals( 2, $resultForConstraint[CheckResult::STATUS_TODO], 'Todo' );
	}

	public function testGetResults() {
		$service = new EvaluateConstraintReportJobService();
		$this->assertEquals( $this->results, $service->getResults( $this->params ) );
	}

}
