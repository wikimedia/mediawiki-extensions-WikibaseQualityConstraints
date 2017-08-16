<?php

namespace WikibaseQuality\ConstraintReport\Test\Context;

use Wikibase\DataModel\Statement\Statement;
use Wikibase\Repo\Tests\NewItem;
use Wikibase\Repo\Tests\NewStatement;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\StatementContext;

/**
 * @covers \WikibaseQuality\ConstraintReport\ConstraintCheck\Context\AbstractContext
 * @covers \WikibaseQuality\ConstraintReport\ConstraintCheck\Context\StatementContext
 * @uses \Wikibase\DataModel\Statement\Statement
 * @uses \Wikibase\Repo\Tests\NewItem
 * @uses \Wikibase\Repo\Tests\NewStatement
 *
 * @group WikibaseQualityConstraints
 *
 * @author Lucas Werkmeister
 * @license GNU GPL v2+
 */
class StatementContextTest extends \PHPUnit_Framework_TestCase {

	public function testGetSnak() {
		$entity = NewItem::withId( 'Q1' )->build();
		$statement = NewStatement::noValueFor( 'P1' )->build();
		$context = new StatementContext( $entity, $statement );

		$this->assertSame( $statement->getMainSnak(), $context->getSnak() );
	}

	public function testGetEntity() {
		$entity = NewItem::withId( 'Q1' )->build();
		$statement = NewStatement::noValueFor( 'P1' )->build();
		$context = new StatementContext( $entity, $statement );

		$this->assertSame( $entity, $context->getEntity() );
	}

	public function testGetType() {
		$entity = NewItem::withId( 'Q1' )->build();
		$statement = NewStatement::noValueFor( 'P1' )->build();
		$context = new StatementContext( $entity, $statement );

		$this->assertSame( 'statement', $context->getType() );
	}

	public function testGetSnakRank() {
		$entity = NewItem::withId( 'Q1' )->build();
		$rank = Statement::RANK_DEPRECATED;
		$statement = NewStatement::noValueFor( 'P1' )
			->withRank( $rank )
			->build();
		$context = new StatementContext( $entity, $statement );

		$this->assertSame( $rank, $context->getSnakRank() );
	}

	public function testGetSnakStatement() {
		$entity = NewItem::withId( 'Q1' )->build();
		$statement = NewStatement::noValueFor( 'P1' )->build();
		$context = new StatementContext( $entity, $statement );

		$this->assertSame( $statement, $context->getSnakStatement() );
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
		$context1 = new StatementContext( $entity, $statement1 );
		$context2 = new StatementContext( $entity, $statement2 );
		$context3 = new StatementContext( $entity, $statement3 );
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
				'P1' => [
					'P1$13ea0742-0190-4d88-b7b0-baee67573818' => [
						$result1,
					],
					'P1$9fbfae7f-6f21-4967-8e2c-ec04ca16873d' => [
						$result2,
					],
				],
				'P2' => [
					'P2$4638ca58-5128-4a1f-88a9-b379fe9f8ad9' => [
						$result3,
						$result4,
					],
				],
			],
		];
		$this->assertSame( $expected, $actual );
	}

}
