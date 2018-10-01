<?php

namespace WikibaseQuality\ConstraintReport\Tests\Job;

use WikibaseQuality\ConstraintReport\Api\CachingResultsSource;
use WikibaseQuality\ConstraintReport\Job\CheckConstraintsJob;
use Wikibase\DataModel\Entity\ItemId;
use MediaWikiTestCase;
use Title;

/**
 * @covers \WikibaseQuality\ConstraintReport\Job\CheckConstraintsJob
 *
 * @group WikibaseQualityConstraints
 * @group Database
 * @group medium
 *
 * @author Jonas Kress
 * @license GPL-2.0-or-later
 */
class CheckConstraintsJobTest extends MediaWikiTestCase {

	const ENTITY_ID = 'Q101';
	const JOB_TITLE_STRING = 'CheckConstraintsJobTitleString';

	private function createJob( $titleString, $params ) {
		$title = Title::makeTitle( NS_MAIN, $titleString );
		return new CheckConstraintsJob( $title, $params );
	}

	public function testJobDuplicationInfo() {
		$params = [ 'entityId' => self::ENTITY_ID ];
		$job = $this->createJob( self::JOB_TITLE_STRING, $params );

		$this->assertEquals(
			[
				'type' => CheckConstraintsJob::COMMAND,
				'namespace' => NS_MAIN,
				'title' => self::JOB_TITLE_STRING,
				'params' => $params
			],
			$job->getDeduplicationInfo()
		);

		$this->assertTrue( $job->ignoreDuplicates() );
	}

	public function testJobRun() {
		$resultSource = $this->getMock(
			CachingResultsSource::class, [], [], '', false
		);
		$resultSource->expects( $this->once() )
			->method( 'getResults' )
			->with( $this->equalTo( [ new ItemId( self::ENTITY_ID ) ] ) );

		$job = $this->createJob( self::JOB_TITLE_STRING, [ 'entityId' => self::ENTITY_ID ] );
		$job->setResultsSource( $resultSource );

		$job->run();
	}

}
