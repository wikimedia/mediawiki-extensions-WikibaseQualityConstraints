<?php

namespace WikidataQuality\ConstraintReport\Tests;

use Wikibase\DataModel\Entity\Item;
use WikidataQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Claim\Claim;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Entity\ItemId;
use DataValues\StringValue;
use WikidataQuality\ConstraintReport\EvaluateConstraintReportJobService;


/**
 * @covers WikidataQuality\ConstraintReport\EvaluateConstraintReportJobService
 *
 * @uses   WikidataQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 * @uses   WikidataQuality\ConstraintReport\ConstraintCheck\DelegatingConstraintChecker
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
		$results[] = new CheckResult( $statement, $this->constraintName, array (), 'some other status' );
		$results[] = new CheckResult( $statement, $this->constraintName, array (), 'yet another one' );
		$this->results = $results;

		$this->params = array( 'entityId' => $this->entityId, 'referenceTimestamp' => null, 'results' => $results );

	}

	protected function tearDown() {

		unset( $this->results );
		unset( $this->constraintName );
		unset( $this->checkTimestamp );
		unset( $this->entityId );
		unset( $this->params );

		parent::tearDown();
	}

	public function testBuildMessageForLog() {
		$service = new EvaluateConstraintReportJobService();
		$messageToLog = (array) json_decode( $service->buildMessageForLog( $this->results, $this->checkTimestamp, $this->params ) );

		$this->assertEquals( 5, count( $messageToLog ) );
		$this->assertEquals( 'SpecialConstraintReport', $messageToLog['special_page_id'] );
		$this->assertEquals( $this->entityId->getSerialization(), $messageToLog['entity_id'] );
		$this->assertEquals( $this->checkTimestamp, $messageToLog['insertion_timestamp'] );
		$this->assertEquals( null, $messageToLog['reference_timestamp'] );

		$resultSummary = (array) $messageToLog['result_summary'];
		$this->assertEquals( 1, count( $resultSummary ) );

		$resultForConstraint = (array) $resultSummary[$this->constraintName];
		$this->assertEquals( 3, count( $resultForConstraint ) );
		$this->assertEquals( 3, $resultForConstraint[CheckResult::STATUS_COMPLIANCE] );
	}

	public function testGetResults() {
		$service = new EvaluateConstraintReportJobService();
		$this->assertEquals( $this->results, $service->getResults( $this->params ) );
	}

}
