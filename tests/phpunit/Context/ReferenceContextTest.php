<?php

namespace WikibaseQuality\ConstraintReport\Tests\Context;

use PHPUnit\Framework\TestCase;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Reference;
use Wikibase\DataModel\ReferenceList;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Snak\PropertySomeValueSnak;
use Wikibase\DataModel\Snak\SnakList;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\Repo\Tests\NewItem;
use Wikibase\Repo\Tests\NewStatement;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\Context;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\ReferenceContext;

/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Context\AbstractContext
 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Context\ApiV2Context
 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Context\ReferenceContext
 *
 * @group WikibaseQualityConstraints
 *
 * @author Lucas Werkmeister
 * @license GNU GPL v2+
 */
class ReferenceContextTest extends TestCase {

	public function testGetSnak() {
		$entity = NewItem::withId( 'Q1' )->build();
		$statement = NewStatement::noValueFor( 'P1' )->build();
		$snak = new PropertySomeValueSnak( new PropertyId( 'P2' ) );
		$reference = new Reference( [ $snak ] );
		$statement->getReferences()->addReference( $reference );
		$context = new ReferenceContext( $entity, $statement, $reference, $snak );

		$this->assertSame( $snak, $context->getSnak() );
	}

	public function testGetEntity() {
		$entity = NewItem::withId( 'Q1' )->build();
		$statement = NewStatement::noValueFor( 'P1' )->build();
		$snak = new PropertySomeValueSnak( new PropertyId( 'P2' ) );
		$reference = new Reference( [ $snak ] );
		$statement->getReferences()->addReference( $reference );
		$context = new ReferenceContext( $entity, $statement, $reference, $snak );

		$this->assertSame( $entity, $context->getEntity() );
	}

	public function testGetType() {
		$entity = NewItem::withId( 'Q1' )->build();
		$statement = NewStatement::noValueFor( 'P1' )->build();
		$snak = new PropertySomeValueSnak( new PropertyId( 'P2' ) );
		$reference = new Reference( [ $snak ] );
		$statement->getReferences()->addReference( $reference );
		$context = new ReferenceContext( $entity, $statement, $reference, $snak );

		$this->assertSame( Context::TYPE_REFERENCE, $context->getType() );
	}

	public function testGetSnakRank() {
		$entity = NewItem::withId( 'Q1' )->build();
		$rank = Statement::RANK_DEPRECATED;
		$statement = NewStatement::noValueFor( 'P1' )
			->withRank( $rank )
			->build();
		$snak = new PropertySomeValueSnak( new PropertyId( 'P2' ) );
		$reference = new Reference( [ $snak ] );
		$statement->getReferences()->addReference( $reference );
		$context = new ReferenceContext( $entity, $statement, $reference, $snak );

		$this->assertSame( null, $context->getSnakRank() );
	}

	public function testGetSnakStatement() {
		$entity = NewItem::withId( 'Q1' )->build();
		$statement = NewStatement::noValueFor( 'P1' )->build();
		$snak = new PropertySomeValueSnak( new PropertyId( 'P2' ) );
		$reference = new Reference( [ $snak ] );
		$statement->getReferences()->addReference( $reference );
		$context = new ReferenceContext( $entity, $statement, $reference, $snak );

		$this->assertSame( null, $context->getSnakStatement() );
	}

	public function testGetSnakGroup() {
		$referenceSnak1 = new PropertyNoValueSnak( new PropertyId( 'P100' ) );
		$referenceSnak2 = new PropertySomeValueSnak( new PropertyId( 'P200' ) );
		$referenceSnak3 = new PropertyNoValueSnak( new PropertyId( 'P300' ) );
		$referenceSnak4 = new PropertySomeValueSnak( new PropertyId( 'P400' ) );
		$reference1 = new Reference( [ $referenceSnak1, $referenceSnak2 ] );
		$reference2 = new Reference( [ $referenceSnak3 ] );
		$reference3 = new Reference( [ $referenceSnak4 ] );
		$statement1 = new Statement(
			new PropertyNoValueSnak( new PropertyId( 'P1' ) ),
			/* qualifiers = */ new SnakList( [ $referenceSnak3 ] ),
			new ReferenceList( [ $reference1, $reference2 ] )
		);
		$statement2 = new Statement(
			new PropertySomeValueSnak( new PropertyId( 'P2' ) ),
			null,
			new ReferenceList( [ $reference2, $reference3 ] )
		);
		$entity = NewItem::withId( 'Q1' )
			->andStatement( $statement1 )
			->andStatement( $statement2 )
			->build();
		$context = new ReferenceContext( $entity, $statement1, $reference1, $referenceSnak1 );

		$snakGroup = $context->getSnakGroup();

		$this->assertSame( [ $referenceSnak1, $referenceSnak2 ], $snakGroup );
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
		$snak1 = new PropertyNoValueSnak( new PropertyId( 'P11' ) );
		$snak2 = new PropertySomeValueSnak( new PropertyId( 'P11' ) );
		$snak3 = new PropertyNoValueSnak( new PropertyId( 'P12' ) );
		$reference1 = new Reference( [ $snak1, $snak2, $snak3 ] );
		$statement1->getReferences()->addReference( $reference1 );
		$reference2 = new Reference( [ $snak2, $snak3 ] );
		$statement1->getReferences()->addReference( $reference2 );
		$statement2->getReferences()->addReference( $reference1 );
		$statement3->getReferences()->addReference( $reference2 );
		$context1 = new ReferenceContext( $entity, $statement1, $reference1, $snak1 );
		$context2 = new ReferenceContext( $entity, $statement1, $reference1, $snak2 );
		$context3 = new ReferenceContext( $entity, $statement1, $reference1, $snak3 );
		$context4 = new ReferenceContext( $entity, $statement1, $reference2, $snak2 );
		$context5 = new ReferenceContext( $entity, $statement1, $reference2, $snak3 );
		$context6 = new ReferenceContext( $entity, $statement2, $reference1, $snak1 );
		$context7 = new ReferenceContext( $entity, $statement3, $reference2, $snak3 );
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
		$context6->storeCheckResultInArray( $result5, $actual );
		$context7->storeCheckResultInArray( $result5, $actual );

		$expected = [
			'P1' => [
				[
					'id' => 'P1$13ea0742-0190-4d88-b7b0-baee67573818',
					'references' => [
						[
							'hash' => $reference1->getHash(),
							'snaks' => [
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
							]
						],
						[
							'hash' => $reference2->getHash(),
							'snaks' => [
								'P11' => [
									[
										'hash' => $snak2->getHash(),
										'results' => [
											$result4,
										]
									]
								],
								'P12' => [
									[
										'hash' => $snak3->getHash(),
										'results' => [
											$result5,
										]
									]
								]
							]
						],
					],
				],
				[
					'id' => 'P1$9fbfae7f-6f21-4967-8e2c-ec04ca16873d',
					'references' => [
						[
							'hash' => $reference1->getHash(),
							'snaks' => [
								'P11' => [
									[
										'hash' => $snak1->getHash(),
										'results' => [
											$result5,
										]
									],
								],
							]
						],
					],
				],
			],
			'P2' => [
				[
					'id' => 'P2$4638ca58-5128-4a1f-88a9-b379fe9f8ad9',
					'references' => [
						[
							'hash' => $reference2->getHash(),
							'snaks' => [
								'P12' => [
									[
										'hash' => $snak3->getHash(),
										'results' => [
											$result5,
										]
									],
								],
							]
						],
					],
				],
			],
		];
		$this->assertSame( [ 'Q1' ], array_keys( $actual ) );
		$this->assertSame( [ 'claims' ], array_keys( $actual['Q1'] ) );
		$this->assertSame( $expected, $actual['Q1']['claims'] );
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
		$snak1 = new PropertyNoValueSnak( new PropertyId( 'P11' ) );
		$snak2 = new PropertySomeValueSnak( new PropertyId( 'P11' ) );
		$snak3 = new PropertyNoValueSnak( new PropertyId( 'P12' ) );
		$reference1 = new Reference( [ $snak1, $snak2, $snak3 ] );
		$statement1->getReferences()->addReference( $reference1 );
		$reference2 = new Reference( [ $snak2, $snak3 ] );
		$statement1->getReferences()->addReference( $reference2 );
		$statement2->getReferences()->addReference( $reference1 );
		$statement3->getReferences()->addReference( $reference2 );
		$context1 = new ReferenceContext( $entity, $statement1, $reference1, $snak1 );
		$context2 = new ReferenceContext( $entity, $statement1, $reference1, $snak2 );
		$context3 = new ReferenceContext( $entity, $statement1, $reference1, $snak3 );
		$context4 = new ReferenceContext( $entity, $statement1, $reference2, $snak2 );
		$context5 = new ReferenceContext( $entity, $statement1, $reference2, $snak3 );
		$context6 = new ReferenceContext( $entity, $statement2, $reference1, $snak1 );
		$context7 = new ReferenceContext( $entity, $statement3, $reference2, $snak3 );

		$actual = [];
		$context1->storeCheckResultInArray( null, $actual );
		$context2->storeCheckResultInArray( null, $actual );
		$context3->storeCheckResultInArray( null, $actual );
		$context4->storeCheckResultInArray( null, $actual );
		$context5->storeCheckResultInArray( null, $actual );
		$context6->storeCheckResultInArray( null, $actual );
		$context7->storeCheckResultInArray( null, $actual );

		$expected = [
			'P1' => [
				[
					'id' => 'P1$13ea0742-0190-4d88-b7b0-baee67573818',
					'references' => [
						[
							'hash' => $reference1->getHash(),
							'snaks' => [
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
							]
						],
						[
							'hash' => $reference2->getHash(),
							'snaks' => [
								'P11' => [
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
							]
						],
					],
				],
				[
					'id' => 'P1$9fbfae7f-6f21-4967-8e2c-ec04ca16873d',
					'references' => [
						[
							'hash' => $reference1->getHash(),
							'snaks' => [
								'P11' => [
									[
										'hash' => $snak1->getHash(),
										'results' => []
									],
								],
							]
						],
					],
				],
			],
			'P2' => [
				[
					'id' => 'P2$4638ca58-5128-4a1f-88a9-b379fe9f8ad9',
					'references' => [
						[
							'hash' => $reference2->getHash(),
							'snaks' => [
								'P12' => [
									[
										'hash' => $snak3->getHash(),
										'results' => []
									],
								],
							]
						],
					],
				],
			],
		];
		$this->assertSame( [ 'Q1' ], array_keys( $actual ) );
		$this->assertSame( [ 'claims' ], array_keys( $actual['Q1'] ) );
		$this->assertSame( $expected, $actual['Q1']['claims'] );
	}

}
