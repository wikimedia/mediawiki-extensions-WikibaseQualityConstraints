<?php

namespace WikibaseQuality\ConstraintReport\Tests\Api;

use Title;
use ValueFormatters\ValueFormatter;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\EntityId\PlainEntityIdFormatter;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Snak\PropertySomeValueSnak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\Lib\Store\EntityTitleLookup;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\Api\CheckingResultsBuilder;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachingMetadata;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\DependencyMetadata;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\Metadata;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\MainSnakContext;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\QualifierContext;
use WikibaseQuality\ConstraintReport\ConstraintCheck\DelegatingConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageRenderer;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\NullResult;
use WikibaseQuality\ConstraintReport\ConstraintParameterRenderer;
use WikibaseQuality\ConstraintReport\Tests\DefaultConfig;
use WikibaseQuality\ConstraintReport\Tests\Fake\FakeSnakContext;

/**
 * @covers WikibaseQuality\ConstraintReport\Api\CheckingResultsBuilder
 *
 * @license GNU GPL v2+
 */
class CheckingResultsBuilderTest extends \PHPUnit_Framework_TestCase {

	const NONEXISTENT_ITEM = 'Q99';
	const NONEXISTENT_CLAIM = 'Q99$dfb32791-ffd5-4420-a1d9-2bc2a0775968';

	use DefaultConfig;

	private function getResultsBuilder(
		DelegatingConstraintChecker $delegatingConstraintChecker = null
	) {
		if ( $delegatingConstraintChecker === null ) {
			$delegatingConstraintChecker = $this->getMockBuilder(
				DelegatingConstraintChecker::class
			)->disableOriginalConstructor()
				->getMock();
		}
		$entityIdFormatter = new PlainEntityIdFormatter();
		$entityTitleLookup = $this->getMock( EntityTitleLookup::class );
		$entityTitleLookup->method( 'getTitleForId' )
			->will( $this->returnCallback( function( EntityId $id ) {
				$title = $this->getMock( Title::class );
				$title->method( 'getFullUrl' )
					->willReturn( 'http://wiki.test/' . $id->getSerialization() );
				$title->method( 'getTalkPage' )
					->will( $this->returnCallback( function() use ( $id ) {
						$title = $this->getMock( Title::class );
						$title->method( 'getFullUrl' )
							->willReturn( 'http://wiki.test/Talk:' . $id->getSerialization() );
						return $title;
					} ) );
				return $title;
			} ) );
		$valueFormatter = $this->getMock( ValueFormatter::class );

		return new CheckingResultsBuilder(
			$delegatingConstraintChecker,
			$entityTitleLookup,
			$entityIdFormatter,
			new ConstraintParameterRenderer(
				$entityIdFormatter,
				$valueFormatter,
				$this->getDefaultConfig()
			),
			new ViolationMessageRenderer( $entityIdFormatter, $valueFormatter ),
			$this->getDefaultConfig()
		);
	}

	public function testGetResults() {
		$q1 = new ItemId( 'Q1' );
		$q2 = new ItemId( 'Q2' );
		$s1 = 'Q3$7f6d761c-bad5-47b6-a335-89635f102771';
		$s2 = 'Q4$41dcb5ec-2ca5-4cfa-822b-a602038fc99f';
		$constraintIds = [ 'P1$47681880-d5f5-417d-96c3-570d6e94d234' ];
		$mock = $this->getMockBuilder( DelegatingConstraintChecker::class )
			->disableOriginalConstructor()
			->setMethods( [ 'checkAgainstConstraintsOnEntityId', 'checkAgainstConstraintsOnClaimId' ] );
		$delegatingConstraintChecker = $mock->getMock();
		$delegatingConstraintChecker->method( 'checkAgainstConstraintsOnEntityId' )
			->withConsecutive(
				[ $this->equalTo( $q1 ), $this->equalTo( $constraintIds ), $this->callback( 'is_callable' ) ],
				[ $this->equalTo( $q2 ), $this->equalTo( $constraintIds ), $this->callback( 'is_callable' ) ]
			)
			->will( $this->returnCallback( function ( $entityId ) {
				return [ new CheckResult(
					new MainSnakContext(
						new Item( $entityId ),
						new Statement( new PropertyNoValueSnak( new PropertyId( 'P1' ) ) )
					),
					new Constraint(
						'P1$47681880-d5f5-417d-96c3-570d6e94d234',
						new PropertyId( 'P1' ),
						'Q1',
						[]
					)
				) ];
			} ) );
		$delegatingConstraintChecker->method( 'checkAgainstConstraintsOnClaimId' )
			->withConsecutive(
				[ $this->equalTo( $s1 ), $this->equalTo( $constraintIds ), $this->callback( 'is_callable' ) ],
				[ $this->equalTo( $s2 ), $this->equalTo( $constraintIds ), $this->callback( 'is_callable' ) ]
			)
			->will( $this->returnCallback( function ( $claimId ) {
				$entityId = new ItemId( substr( $claimId, 0, 2 ) );
				return [ new CheckResult(
					new MainSnakContext(
						new Item( $entityId ),
						new Statement( new PropertyNoValueSnak( new PropertyId( 'P1' ) ) )
					),
					new Constraint(
						'P1$47681880-d5f5-417d-96c3-570d6e94d234',
						new PropertyId( 'P1' ),
						'Q1',
						[]
					)
				) ];
			} ) );

		$result = $this->getResultsBuilder( $delegatingConstraintChecker )->getResults(
			[ $q1, $q2 ],
			[ $s1, $s2 ],
			$constraintIds,
			[ CheckResult::STATUS_TODO ]
		)->getArray();

		$this->assertSame( [ 'Q1', 'Q2', 'Q3', 'Q4' ], array_keys( $result ) );
		foreach ( $result as $resultByQ ) {
			$this->assertSame( [ 'P1' ], array_keys( $resultByQ['claims'] ) );
			$this->assertCount( 1, $resultByQ['claims']['P1'] );
			$this->assertCount( 1, $resultByQ['claims']['P1'][0]['mainsnak']['results'] );
		}
	}

	public function testGetResults_Empty() {
		$mock = $this->getMockBuilder( DelegatingConstraintChecker::class )
			->disableOriginalConstructor()
			->setMethods( [ 'checkAgainstConstraintsOnEntityId', 'checkAgainstConstraintsOnClaimId' ] );
		$delegatingConstraintChecker = $mock->getMock();
		$delegatingConstraintChecker->method( 'checkAgainstConstraintsOnEntityId' )
			->willReturn( [] );
		$delegatingConstraintChecker->method( 'checkAgainstConstraintsOnClaimId' )
			->willReturn( [] );

		$result = $this->getResultsBuilder( $delegatingConstraintChecker )->getResults(
			[ new ItemId( self::NONEXISTENT_ITEM ) ],
			[ self::NONEXISTENT_CLAIM ],
			[],
			[ CheckResult::STATUS_TODO ]
		)->getArray();

		$this->assertEmpty( $result );
	}

	public function testGetResults_DependencyMetadata() {
		$mock = $this->getMockBuilder( DelegatingConstraintChecker::class )
			->disableOriginalConstructor()
			->setMethods( [ 'checkAgainstConstraintsOnEntityId', 'checkAgainstConstraintsOnClaimId' ] );
		$delegatingConstraintChecker = $mock->getMock();
		$delegatingConstraintChecker->method( 'checkAgainstConstraintsOnEntityId' )
			->willReturn( [
				( new CheckResult(
					new MainSnakContext(
						new Item( new ItemId( 'Q1' ) ),
						new Statement( new PropertyNoValueSnak( new PropertyId( 'P1' ) ) )
					),
					new Constraint(
						'P1$47681880-d5f5-417d-96c3-570d6e94d234',
						new PropertyId( 'P1' ),
						'Q1',
						[]
					)
				) )->withMetadata( Metadata::ofDependencyMetadata(
					DependencyMetadata::ofEntityId( new ItemId( 'Q100' ) ) ) )
			] );
		$delegatingConstraintChecker->method( 'checkAgainstConstraintsOnClaimId' )
			->willReturn( [
				( new CheckResult(
					new MainSnakContext(
						new Item( new ItemId( 'Q2' ) ),
						new Statement( new PropertyNoValueSnak( new PropertyId( 'P1' ) ) )
					),
					new Constraint(
						'P1$47681880-d5f5-417d-96c3-570d6e94d234',
						new PropertyId( 'P1' ),
						'Q1',
						[]
					)
				) )->withMetadata( Metadata::ofDependencyMetadata(
					DependencyMetadata::ofEntityId( new PropertyId( 'P100' ) ) ) )
			] );

		$metadata = $this->getResultsBuilder( $delegatingConstraintChecker )->getResults(
			[ new ItemId( 'Q1' ) ],
			[ 'Q2$73408a9b-b1b0-4035-bf36-1e65ecf8772d' ],
			null,
			[ CheckResult::STATUS_TODO ]
		)->getMetadata();

		$expected = [ new ItemId( 'Q100' ), new PropertyId( 'P100' ) ];
		$actual = $metadata->getDependencyMetadata()->getEntityIds();
		sort( $expected );
		sort( $actual );
		$this->assertEquals( $expected, $actual );
	}

	public function testGetResults_FilterStatuses() {
		$q1 = new ItemId( 'Q1' );
		$mock = $this->getMockBuilder( DelegatingConstraintChecker::class )
			->disableOriginalConstructor()
			->setMethods( [ 'checkAgainstConstraintsOnEntityId' ] );
		$delegatingConstraintChecker = $mock->getMock();
		$constraint = new Constraint(
			'P1$47681880-d5f5-417d-96c3-570d6e94d234',
			new PropertyId( 'P1' ),
			'Q1',
			[]
		);
		$delegatingConstraintChecker->method( 'checkAgainstConstraintsOnEntityId' )
			->willReturn( [
				new CheckResult(
					new MainSnakContext(
						new Item( $q1 ),
						new Statement( new PropertyNoValueSnak( new PropertyId( 'P1' ) ) )
					),
					$constraint,
					[],
					CheckResult::STATUS_VIOLATION
				),
				new CheckResult(
					new MainSnakContext(
						new Item( $q1 ),
						new Statement( new PropertySomeValueSnak( new PropertyId( 'P1' ) ) )
					),
					$constraint,
					[],
					CheckResult::STATUS_COMPLIANCE
				),
			] );

		$result = $this->getResultsBuilder( $delegatingConstraintChecker )->getResults(
			[ $q1 ],
			[],
			[],
			[ CheckResult::STATUS_VIOLATION ]
		)->getArray();

		$statementResults = $result['Q1']['claims']['P1'][0]['mainsnak']['results'];
		$this->assertCount( 1, $statementResults );
		$this->assertSame( CheckResult::STATUS_VIOLATION, $statementResults[0]['status'] );
	}

	public function testCheckResultToArray_NullResult() {
		$checkResult = new NullResult(
			new FakeSnakContext( new PropertyNoValueSnak( new PropertyId( 'P1' ) ) )
		);

		$result = $this->getResultsBuilder()->checkResultToArray( $checkResult );

		$this->assertNull( $result );
	}

	public function testCheckResultToArray_Constraint() {
		$checkResult = new CheckResult(
			new MainSnakContext(
				new Item( new ItemId( 'Q1' ) ),
				new Statement( new PropertyNoValueSnak( new PropertyId( 'P1' ) ) )
			),
			new Constraint(
				'P1$31d77e02-e1bd-423e-811e-7f6dd5da0b90',
				new PropertyId( 'P1' ),
				'Q1',
				[]
			)
		);

		$result = $this->getResultsBuilder()->checkResultToArray( $checkResult );
		$constraint = $result['constraint'];

		$this->assertSame( $checkResult->getConstraintId(), $constraint['id'] );
		$this->assertSame( 'Q1', $constraint['type'] );
		$this->assertSame( 'Q1', $constraint['typeLabel'] );
		$this->assertSame( 'http://wiki.test/P1#P2302', $constraint['link'] );
		$this->assertSame( 'http://wiki.test/Talk:P1', $constraint['discussLink'] );
		if ( $this->getDefaultConfig()->get( 'WBQualityConstraintsIncludeDetailInApi' ) ) {
			$this->assertSame( [], $constraint['detail'] );
			$this->assertNull( $constraint['detailHTML'] );
		} else {
			$this->assertArrayNotHasKey( 'detail', $constraint );
			$this->assertArrayNotHasKey( 'detailHTML', $constraint );
		}
	}

	public function testCheckResultToArray_Result() {
		$checkResult = new CheckResult(
			new MainSnakContext(
				new Item( new ItemId( 'Q1' ) ),
				new Statement(
					new PropertyNoValueSnak( new PropertyId( 'P1' ) ),
					null,
					null,
					'Q1$1deb7c9e-8de4-4bc2-baab-add9d4f538c3'
				)
			),
			new Constraint(
				'P1$31d77e02-e1bd-423e-811e-7f6dd5da0b90',
				new PropertyId( 'P1' ),
				'Q1',
				[]
			),
			[ 'parameters' => [] ],
			'status',
			'<strong>message</strong>'
		);

		$result = $this->getResultsBuilder()->checkResultToArray( $checkResult );

		$this->assertSame( 'status', $result['status'] );
		$this->assertSame( 'P1', $result['property'] );
		$this->assertSame( '<strong>message</strong>', $result['message-html'] );
		$this->assertSame( 'Q1$1deb7c9e-8de4-4bc2-baab-add9d4f538c3', $result['claim'] );
		$this->assertArrayNotHasKey( 'cached', $result );
	}

	public function testCheckResultToArray_Qualifier() {
		$checkResult = new CheckResult(
			new QualifierContext(
				new Item( new ItemId( 'Q1' ) ),
				new Statement( new PropertyNoValueSnak( new PropertyId( 'P2' ) ) ),
				new PropertyNoValueSnak( new PropertyId( 'P1' ) )
			),
			new Constraint(
				'P1$31d77e02-e1bd-423e-811e-7f6dd5da0b90',
				new PropertyId( 'P1' ),
				'Q1',
				[]
			)
		);
		$checkResult->withMetadata( Metadata::ofCachingMetadata(
			CachingMetadata::ofMaximumAgeInSeconds( 10 ) ) );

		$result = $this->getResultsBuilder()->checkResultToArray( $checkResult );

		$this->assertSame( 'P1', $result['property'] );
		$this->assertArrayNotHasKey( 'claim', $result );
	}

	public function testCheckResultToArray_Cached() {
		$checkResult = new CheckResult(
			new MainSnakContext(
				new Item( new ItemId( 'Q1' ) ),
				new Statement( new PropertyNoValueSnak( new PropertyId( 'P1' ) ) )
			),
			new Constraint(
				'P1$31d77e02-e1bd-423e-811e-7f6dd5da0b90',
				new PropertyId( 'P1' ),
				'Q1',
				[]
			)
		);
		$checkResult->withMetadata( Metadata::ofCachingMetadata(
			CachingMetadata::ofMaximumAgeInSeconds( 10 ) ) );

		$result = $this->getResultsBuilder()->checkResultToArray( $checkResult );

		$this->assertSame( [ 'maximumAgeInSeconds' => 10 ], $result['cached'] );
	}

}
