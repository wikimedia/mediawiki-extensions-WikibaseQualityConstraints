<?php

namespace WikibaseQuality\ConstraintReport\Tests\Helper;

use HashConfig;
use IBufferingStatsdDataFactory;
use PHPUnit4And6Compat;
use Psr\Log\LoggerInterface;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\Repo\Tests\NewItem;
use Wikibase\Repo\Tests\NewStatement;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\MainSnakContext;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\LoggingHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessage;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;

/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\LoggingHelper
 *
 * @group WikibaseQualityConstraints
 *
 * @author Lucas Werkmeister
 * @license GPL-2.0-or-later
 */
class LoggingHelperTest extends \PHPUnit\Framework\TestCase {
	use PHPUnit4And6Compat;

	/**
	 * @dataProvider provideConstraintCheckDurationsAndLogLevels
	 */
	public function testLogConstraintCheck( $durationSeconds, $expectedLevel, $expectedLimit ) {
		$statement = NewStatement::noValueFor( 'P1' )->build();
		$constraint = new Constraint( 'test constraint id', new PropertyId( 'P1' ), 'Q100', [] );
		$entity = NewItem::withId( 'Q1' )->build();
		$context = new MainSnakContext( $entity, $statement );
		$checkResult = new CheckResult(
			$context,
			$constraint,
			[ 'test' => 'params' ],
			CheckResult::STATUS_VIOLATION,
			new ViolationMessage( 'wbqc-violation-message-single-value' )
		);

		$dataFactory = $this->getMock( IBufferingStatsdDataFactory::class );
		$dataFactory->expects( $this->once() )
			->method( 'timing' )
			->with(
				$this->identicalTo( 'wikibase.quality.constraints.check.timing.Q100-TestChecker' ),
				$this->identicalTo( $durationSeconds * 1000 )
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
						'resultMessage' => 'wbqc-violation-message-single-value',
					]
				)
			);

		$loggingHelper = new LoggingHelper( $dataFactory, $logger, new HashConfig( [
			'WBQualityConstraintsCheckDurationInfoSeconds' => 1.0,
			'WBQualityConstraintsCheckDurationWarningSeconds' => 10.0,
		] ) );

		$loggingHelper->logConstraintCheck(
			$context, $constraint,
			$checkResult,
			'\Test\Namespace\TestChecker', $durationSeconds,
			__METHOD__
		);
	}

	public function provideConstraintCheckDurationsAndLogLevels() {
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
		$context = new MainSnakContext( $entity, $statement );
		$checkResult = new CheckResult(
			$context,
			$constraint,
			[ 'test' => 'params' ],
			CheckResult::STATUS_VIOLATION,
			new ViolationMessage( 'wbqc-violation-message-single-value' )
		);

		$dataFactory = $this->getMock( IBufferingStatsdDataFactory::class );
		$dataFactory->expects( $this->once() )
			->method( 'timing' )
			->with(
				$this->identicalTo( 'wikibase.quality.constraints.check.timing.Q100-TestChecker' ),
				$this->identicalTo( 5000.0 )
			);

		$logger = $this->getMock( LoggerInterface::class );
		$logger->expects( $this->never() )->method( 'log' );

		$loggingHelper = new LoggingHelper( $dataFactory, $logger, new HashConfig( [
			'WBQualityConstraintsCheckDurationInfoSeconds' => null,
			'WBQualityConstraintsCheckDurationWarningSeconds' => null,
		] ) );

		$loggingHelper->logConstraintCheck(
			$context, $constraint,
			$checkResult,
			'\Test\Namespace\TestChecker', 5.0,
			__METHOD__
		);
	}

}
