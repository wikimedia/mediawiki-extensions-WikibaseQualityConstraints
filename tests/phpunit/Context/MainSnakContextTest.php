<?php

namespace WikibaseQuality\ConstraintReport\Tests\Context;

use Wikibase\DataModel\Statement\Statement;
use Wikibase\Repo\Tests\NewItem;
use Wikibase\Repo\Tests\NewStatement;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\Context;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\MainSnakContext;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\MainSnakContextCursor;

/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Context\AbstractContext
 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Context\ApiV2Context
 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Context\MainSnakContext
 *
 * @group WikibaseQualityConstraints
 *
 * @author Lucas Werkmeister
 * @license GPL-2.0-or-later
 */
class MainSnakContextTest extends \PHPUnit\Framework\TestCase {

	public function testGetSnak() {
		$entity = NewItem::withId( 'Q1' )->build();
		$statement = NewStatement::noValueFor( 'P1' )->build();
		$context = new MainSnakContext( $entity, $statement );

		$this->assertSame( $statement->getMainSnak(), $context->getSnak() );
	}

	public function testGetEntity() {
		$entity = NewItem::withId( 'Q1' )->build();
		$statement = NewStatement::noValueFor( 'P1' )->build();
		$context = new MainSnakContext( $entity, $statement );

		$this->assertSame( $entity, $context->getEntity() );
	}

	public function testGetType() {
		$entity = NewItem::withId( 'Q1' )->build();
		$statement = NewStatement::noValueFor( 'P1' )->build();
		$context = new MainSnakContext( $entity, $statement );

		$this->assertSame( Context::TYPE_STATEMENT, $context->getType() );
	}

	public function testGetSnakRank() {
		$entity = NewItem::withId( 'Q1' )->build();
		$rank = Statement::RANK_DEPRECATED;
		$statement = NewStatement::noValueFor( 'P1' )
			->withRank( $rank )
			->build();
		$context = new MainSnakContext( $entity, $statement );

		$this->assertSame( $rank, $context->getSnakRank() );
	}

	public function testGetSnakStatement() {
		$entity = NewItem::withId( 'Q1' )->build();
		$statement = NewStatement::noValueFor( 'P1' )->build();
		$context = new MainSnakContext( $entity, $statement );

		$this->assertSame( $statement, $context->getSnakStatement() );
	}

	public function testGetSnakGroup() {
		$statement1 = NewStatement::noValueFor( 'P1' )->build();
		$statement2 = NewStatement::noValueFor( 'P1' )->build();
		$statement3 = NewStatement::noValueFor( 'P2' )
			->withDeprecatedRank()
			->build();
		$entity = NewItem::withId( 'Q1' )
			->andStatement( $statement1 )
			->andStatement( $statement2 )
			->andStatement( $statement3 )
			->build();
		$context = new MainSnakContext( $entity, $statement1 );

		$snakGroup = $context->getSnakGroup();

		$this->assertSame( [ $statement1->getMainSnak(), $statement2->getMainSnak() ], $snakGroup );
	}

	public function testGetCursor() {
		$entity = NewItem::withId( 'Q1' )->build();
		$statement = NewStatement::noValueFor( 'P1' )->build();
		$context = new MainSnakContext( $entity, $statement );

		$cursor = $context->getCursor();

		$this->assertInstanceOf( MainSnakContextCursor::class, $cursor );
	}

	public function testStoreCheckResultInArray() {
		$entity = NewItem::withId( 'Q1' )->build();
		$statement1 = NewStatement::noValueFor( 'P1' )
			->withGuid( 'P1$13ea0742-0190-4d88-b7b0-baee67573818' )
			->build();
		$statement2 = NewStatement::noValueFor( 'P1' )
			->withGuid( 'P1$9fbfae7f-6f21-4967-8e2c-ec04ca16873d' )
			->build();
		$statement3 = NewStatement::noValueFor( 'P2' )
			->withGuid( 'P2$4638ca58-5128-4a1f-88a9-b379fe9f8ad9' )
			->build();
		$context1 = new MainSnakContext( $entity, $statement1 );
		$context2 = new MainSnakContext( $entity, $statement2 );
		$context3 = new MainSnakContext( $entity, $statement3 );
		$result1 = [ 'result1' ];
		$result2 = [ 'status' => 'some status', 'result' => 'second result' ];
		$result3 = [ 3 ];
		$result4 = [ [ 'the fourth result' ] ];

		$actual = [];
		$context1->storeCheckResultInArray( $result1, $actual );
		$context2->storeCheckResultInArray( $result2, $actual );
		$context3->storeCheckResultInArray( $result3, $actual );
		$context3->storeCheckResultInArray( $result4, $actual );

		$expected = [
			'Q1' => [
				'claims' => [
					'P1' => [
						[
							'id' => 'P1$13ea0742-0190-4d88-b7b0-baee67573818',
							'mainsnak' => [
								'hash' => $statement1->getMainSnak()->getHash(),
								'results' => [
									$result1,
								],
							],
						],
						[
							'id' => 'P1$9fbfae7f-6f21-4967-8e2c-ec04ca16873d',
							'mainsnak' => [
								'hash' => $statement2->getMainSnak()->getHash(),
								'results' => [
									$result2,
								],
							],
						],
					],
					'P2' => [
						[
							'id' => 'P2$4638ca58-5128-4a1f-88a9-b379fe9f8ad9',
							'mainsnak' => [
								'hash' => $statement3->getMainSnak()->getHash(),
								'results' => [
									$result3,
									$result4,
								],
							],
						],
					],
				],
			],
		];
		$this->assertSame( $expected, $actual );
	}

	public function testStoreCheckResultInArray_NullResult() {
		$entity = NewItem::withId( 'Q1' )->build();
		$statement1 = NewStatement::noValueFor( 'P1' )
			->withGuid( 'P1$13ea0742-0190-4d88-b7b0-baee67573818' )
			->build();
		$statement2 = NewStatement::noValueFor( 'P1' )
			->withGuid( 'P1$9fbfae7f-6f21-4967-8e2c-ec04ca16873d' )
			->build();
		$statement3 = NewStatement::noValueFor( 'P2' )
			->withGuid( 'P2$4638ca58-5128-4a1f-88a9-b379fe9f8ad9' )
			->build();
		$context1 = new MainSnakContext( $entity, $statement1 );
		$context2 = new MainSnakContext( $entity, $statement2 );
		$context3 = new MainSnakContext( $entity, $statement3 );

		$actual = [];
		$context1->storeCheckResultInArray( null, $actual );
		$context2->storeCheckResultInArray( null, $actual );
		$context3->storeCheckResultInArray( null, $actual );

		$expected = [
			'Q1' => [
				'claims' => [
					'P1' => [
						[
							'id' => 'P1$13ea0742-0190-4d88-b7b0-baee67573818',
							'mainsnak' => [
								'hash' => $statement1->getMainSnak()->getHash(),
								'results' => [],
							],
						],
						[
							'id' => 'P1$9fbfae7f-6f21-4967-8e2c-ec04ca16873d',
							'mainsnak' => [
								'hash' => $statement2->getMainSnak()->getHash(),
								'results' => [],
							],
						],
					],
					'P2' => [
						[
							'id' => 'P2$4638ca58-5128-4a1f-88a9-b379fe9f8ad9',
							'mainsnak' => [
								'hash' => $statement3->getMainSnak()->getHash(),
								'results' => [],
							],
						],
					],
				],
			],
		];
		$this->assertSame( $expected, $actual );
	}

}
