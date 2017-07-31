<?php

namespace WikibaseQuality\ConstraintReport\Test\ConstraintChecker;

use HashConfig;
use Psr\Log\LoggerInterface;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\Repo\Tests\NewItem;
use Wikibase\Repo\Tests\NewStatement;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\LoggingHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;

/**
 * @covers \WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\LoggingHelper
 *
 * @group WikibaseQualityConstraints
 *
 * @author Lucas Werkmeister
 * @license GNU GPL v2+
 */
class LoggingHelperTest extends \PHPUnit_Framework_TestCase {

	/**
	 * @dataProvider logLongConstraintProvider
	 */
	public function testLogConstraintCheck( $durationSeconds, $expectedLevel, $expectedLimit ) {
		$statement = NewStatement::noValueFor( 'P1' )->build();
		$constraint = new Constraint( 'test constraint id', new PropertyId( 'P1' ), 'Q100', [] );
		$entity = NewItem::withId( 'Q1' )->build();
		$checkResult = new CheckResult(
			$entity->getId(),
			$statement,
			$constraint,
			[ 'test' => 'params' ],
			CheckResult::STATUS_VIOLATION,
			'test message'
		);

		$logger = $this->getMock( LoggerInterface::class );
		$logger->expects( $expectedLevel !== null ? $this->once() : $this->never() )
			->method( 'log' )
			->with(
				$this->identicalTo( $expectedLevel ),
				$this->identicalTo(
					'Constraint check with {constraintCheckerClassShortName} ' .
					'took longer than {limitSeconds} second(s) ' .
					'(duration: {durationSeconds} seconds).'
				),
				$this->equalTo(
					[
						'method' => __METHOD__,
						'loggingMethod' => LoggingHelper::class . '::logConstraintCheck',
						'durationSeconds' => $durationSeconds,
						'limitSeconds' => $expectedLimit,
						'constraintId' => 'test constraint id',
						'constraintPropertyId' => 'P1',
						'constraintTypeItemId' => 'Q100',
						'constraintParameters' => [],
						'constraintCheckerClass' => '\Test\Namespace\TestChecker',
						'constraintCheckerClassShortName' => 'TestChecker',
						'entityId' => 'Q1',
						'statementGuid' => $statement->getGuid(),
						'resultStatus' => CheckResult::STATUS_VIOLATION,
						'resultParameters' => [ 'test' => 'params' ],
						'resultMessage' => 'test message',
					]
				)
			);

		$loggingHelper = new LoggingHelper( $logger, new HashConfig( [
			'WBQualityConstraintsCheckDurationInfoSeconds' => 1.0,
			'WBQualityConstraintsCheckDurationWarningSeconds' => 10.0,
		] ) );

		$loggingHelper->logConstraintCheck(
			$statement, $constraint, $entity,
			$checkResult,
			'\Test\Namespace\TestChecker', $durationSeconds,
			__METHOD__
		);
	}

	public function logLongConstraintProvider() {
		return [
			'short constraint check, nothing to log' => [ 0.5, null, null ],
			'long but not extremely long constraint check, log as info' => [ 5.0, 'info', 1.0 ],
			'extremely long constraint check, log as warning' => [ 50.0, 'warning', 10.0 ],
		];
	}

	public function testLogConstraintCheckDisabled() {
		$statement = NewStatement::noValueFor( 'P1' )->build();
		$constraint = new Constraint( 'test constraint id', new PropertyId( 'P1' ), 'Q100', [] );
		$entity = NewItem::withId( 'Q1' )->build();
		$checkResult = new CheckResult(
			$entity->getId(),
			$statement,
			$constraint,
			[ 'test' => 'params' ],
			CheckResult::STATUS_VIOLATION,
			'test message'
		);

		$logger = $this->getMock( LoggerInterface::class );
		$logger->expects( $this->never() )->method( 'log' );

		$loggingHelper = new LoggingHelper( $logger, new HashConfig( [
			'WBQualityConstraintsCheckDurationInfoSeconds' => null,
			'WBQualityConstraintsCheckDurationWarningSeconds' => null,
		] ) );

		$loggingHelper->logConstraintCheck(
			$statement, $constraint, $entity,
			$checkResult,
			'\Test\Namespace\TestChecker', 5.0,
			__METHOD__
		);
	}

}
