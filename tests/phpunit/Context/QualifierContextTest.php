<?php

namespace WikibaseQuality\ConstraintReport\Test\Context;

use Wikibase\DataModel\Statement\Statement;
use Wikibase\Repo\Tests\NewItem;
use Wikibase\Repo\Tests\NewStatement;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\Context;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\QualifierContext;

/**
 * @covers \WikibaseQuality\ConstraintReport\ConstraintCheck\Context\AbstractContext
 * @covers \WikibaseQuality\ConstraintReport\ConstraintCheck\Context\ApiV2Context
 * @covers \WikibaseQuality\ConstraintReport\ConstraintCheck\Context\QualifierContext
 *
 * @group WikibaseQualityConstraints
 *
 * @author Lucas Werkmeister
 * @license GNU GPL v2+
 */
class QualifierContextTest extends \PHPUnit_Framework_TestCase {

	public function testGetSnak() {
		$entity = NewItem::withId( 'Q1' )->build();
		$statement = NewStatement::noValueFor( 'P1' )->build();
		$snak = NewStatement::someValueFor( 'P2' )->build()->getMainSnak();
		$context = new QualifierContext( $entity, $statement, $snak );

		$this->assertSame( $snak, $context->getSnak() );
	}

	public function testGetEntity() {
		$entity = NewItem::withId( 'Q1' )->build();
		$statement = NewStatement::noValueFor( 'P1' )->build();
		$snak = NewStatement::someValueFor( 'P2' )->build()->getMainSnak();
		$context = new QualifierContext( $entity, $statement, $snak );

		$this->assertSame( $entity, $context->getEntity() );
	}

	public function testGetType() {
		$entity = NewItem::withId( 'Q1' )->build();
		$statement = NewStatement::noValueFor( 'P1' )->build();
		$snak = NewStatement::someValueFor( 'P2' )->build()->getMainSnak();
		$context = new QualifierContext( $entity, $statement, $snak );

		$this->assertSame( Context::TYPE_QUALIFIER, $context->getType() );
	}

	public function testGetSnakRank() {
		$entity = NewItem::withId( 'Q1' )->build();
		$rank = Statement::RANK_DEPRECATED;
		$statement = NewStatement::noValueFor( 'P1' )
			->withRank( $rank )
			->build();
		$snak = NewStatement::someValueFor( 'P2' )->build()->getMainSnak();
		$context = new QualifierContext( $entity, $statement, $snak );

		$this->assertSame( null, $context->getSnakRank() );
	}

	public function testGetSnakStatement() {
		$entity = NewItem::withId( 'Q1' )->build();
		$statement = NewStatement::noValueFor( 'P1' )->build();
		$snak = NewStatement::someValueFor( 'P2' )->build()->getMainSnak();
		$context = new QualifierContext( $entity, $statement, $snak );

		$this->assertSame( null, $context->getSnakStatement() );
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
		$snak1 = NewStatement::noValueFor( 'P11' )->build()->getMainSnak();
		$snak2 = NewStatement::someValueFor( 'P11' )->build()->getMainSnak();
		$snak3 = NewStatement::noValueFor( 'P12' )->build()->getMainSnak();
		$context1 = new QualifierContext( $entity, $statement1, $snak1 );
		$context2 = new QualifierContext( $entity, $statement1, $snak2 );
		$context3 = new QualifierContext( $entity, $statement1, $snak3 );
		$context4 = new QualifierContext( $entity, $statement2, $snak3 );
		$context5 = new QualifierContext( $entity, $statement3, $snak3 );
		$result1 = [ 'result1' ];
		$result2 = [ 'status' => 'some status', 'result' => 'second result' ];
		$result3 = [ 3 ];
		$result4 = [ [ 'the fourth result' ] ];
		$result5 = [ [ [ 5.0 ] ] ];

		$actual = [];
		$context1->storeCheckResultInArray( $result1, $actual );
		$context2->storeCheckResultInArray( $result2, $actual );
		$context3->storeCheckResultInArray( $result3, $actual );
		$context3->storeCheckResultInArray( $result4, $actual );
		$context4->storeCheckResultInArray( $result4, $actual );
		$context5->storeCheckResultInArray( $result5, $actual );

		$expected = [
			'Q1' => [
				'claims' => [
					'P1' => [
						[
							'id' => 'P1$13ea0742-0190-4d88-b7b0-baee67573818',
							'qualifiers' => [
								'P11' => [
									[
										'hash' => $snak1->getHash(),
										'results' => [
											$result1,
										]
									],
									[
										'hash' => $snak2->getHash(),
										'results' => [
											$result2,
										]
									]
								],
								'P12' => [
									[
										'hash' => $snak3->getHash(),
										'results' => [
											$result3,
											$result4,
										]
									]
								]
							],
						],
						[
							'id' => 'P1$9fbfae7f-6f21-4967-8e2c-ec04ca16873d',
							'qualifiers' => [
								'P12' => [
									[
										'hash' => $snak3->getHash(),
										'results' => [
											$result4,
										]
									]
								]
							],
						],
					],
					'P2' => [
						[
							'id' => 'P2$4638ca58-5128-4a1f-88a9-b379fe9f8ad9',
							'qualifiers' => [
								'P12' => [
									[
										'hash' => $snak3->getHash(),
										'results' => [
											$result5,
										]
									]
								]
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
		$snak1 = NewStatement::noValueFor( 'P11' )->build()->getMainSnak();
		$snak2 = NewStatement::someValueFor( 'P11' )->build()->getMainSnak();
		$snak3 = NewStatement::noValueFor( 'P12' )->build()->getMainSnak();
		$context1 = new QualifierContext( $entity, $statement1, $snak1 );
		$context2 = new QualifierContext( $entity, $statement1, $snak2 );
		$context3 = new QualifierContext( $entity, $statement1, $snak3 );
		$context4 = new QualifierContext( $entity, $statement2, $snak3 );
		$context5 = new QualifierContext( $entity, $statement3, $snak3 );

		$actual = [];
		$context1->storeCheckResultInArray( null, $actual );
		$context2->storeCheckResultInArray( null, $actual );
		$context3->storeCheckResultInArray( null, $actual );
		$context4->storeCheckResultInArray( null, $actual );
		$context5->storeCheckResultInArray( null, $actual );

		$expected = [
			'Q1' => [
				'claims' => [
					'P1' => [
						[
							'id' => 'P1$13ea0742-0190-4d88-b7b0-baee67573818',
							'qualifiers' => [
								'P11' => [
									[
										'hash' => $snak1->getHash(),
										'results' => []
									],
									[
										'hash' => $snak2->getHash(),
										'results' => []
									]
								],
								'P12' => [
									[
										'hash' => $snak3->getHash(),
										'results' => []
									]
								]
							],
						],
						[
							'id' => 'P1$9fbfae7f-6f21-4967-8e2c-ec04ca16873d',
							'qualifiers' => [
								'P12' => [
									[
										'hash' => $snak3->getHash(),
										'results' => []
									]
								]
							],
						],
					],
					'P2' => [
						[
							'id' => 'P2$4638ca58-5128-4a1f-88a9-b379fe9f8ad9',
							'qualifiers' => [
								'P12' => [
									[
										'hash' => $snak3->getHash(),
										'results' => []
									]
								]
							],
						],
					],
				],
			],
		];
		$this->assertSame( $expected, $actual );
	}

}
