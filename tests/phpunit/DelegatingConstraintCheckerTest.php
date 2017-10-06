<?php

namespace WikibaseQuality\ConstraintReport\Test\ConstraintChecker;

use Wikibase\DataModel\Entity\DispatchingEntityIdParser;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\Lookup\EntityRetrievingDataTypeLookup;
use Wikibase\DataModel\Services\Lookup\InMemoryEntityLookup;
use Wikibase\DataModel\Services\Statement\StatementGuidParser;
use Wikibase\Rdf\RdfVocabulary;
use Wikibase\Repo\Tests\NewItem;
use Wikibase\Repo\Tests\NewStatement;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterException;
use WikibaseQuality\ConstraintReport\ConstraintCheck\DelegatingConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintReportFactory;
use WikibaseQuality\ConstraintReport\Tests\ConstraintParameters;
use WikibaseQuality\ConstraintReport\Tests\ResultAssertions;
use WikibaseQuality\ConstraintReport\Tests\TitleParserMock;
use Wikimedia\Rdbms\DBUnexpectedError;

/**
 * @covers \WikibaseQuality\ConstraintReport\ConstraintCheck\DelegatingConstraintChecker
 *
 * @group WikibaseQualityConstraints
 * @group Database
 *
 * @uses   \WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 * @uses   \WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser
 * @uses   \WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\RangeChecker
 * @uses   \WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\DiffWithinRangeChecker
 * @uses   \WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\SingleValueChecker
 * @uses   \WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\MultiValueChecker
 * @uses   \WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\UniqueValueChecker
 * @uses   \WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\OneOfChecker
 * @uses   \WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\CommonsLinkChecker
 * @uses   \WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\TargetRequiredClaimChecker
 * @uses   \WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\ItemChecker
 * @uses   \WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\ConflictsWithChecker
 * @uses   \WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\SymmetricChecker
 * @uses   \WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\InverseChecker
 * @uses   \WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\FormatChecker
 * @uses   \WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\QualifierChecker
 * @uses   \WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\QualifiersChecker
 * @uses   \WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\MandatoryQualifiersChecker
 * @uses   \WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\TypeChecker
 * @uses   \WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\ValueTypeChecker
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class DelegatingConstraintCheckerTest extends \MediaWikiTestCase {

	use ConstraintParameters, ResultAssertions, TitleParserMock;

	/**
	 * @var DelegatingConstraintChecker
	 */
	private $constraintChecker;

	/**
	 * @var InMemoryEntityLookup
	 */
	private $lookup;

	/**
	 * Number of constraints for P1.
	 * @var int
	 */
	private $constraintCount;

	protected function setUp() {
		parent::setUp();
		$this->lookup = new InMemoryEntityLookup();
		$entityIdParser = new DispatchingEntityIdParser( [
			'/^Q/' => function( $serialization ) {
				return new ItemId( $serialization );
			},
			'/^P/' => function( $serialization ) {
				return new PropertyId( $serialization );
			}
		] );
		$config = $this->getDefaultConfig();
		$rdfVocabulary = new RdfVocabulary(
			'http://www.wikidata.org/entity/',
			'http://www.wikidata.org/wiki/Special:EntityData/'
		);
		$titleParser = $this->getTitleParserMock();
		$factory = new ConstraintReportFactory(
			$this->lookup,
			new EntityRetrievingDataTypeLookup( $this->lookup ),
			new StatementGuidParser( $entityIdParser ),
			$config,
			$this->getConstraintParameterRenderer(),
			$this->getConstraintParameterParser(),
			$rdfVocabulary,
			$entityIdParser,
			$titleParser
		);
		$this->constraintChecker = $factory->getConstraintChecker();

		// specify database tables used by this test
		$this->tablesUsed[ ] = CONSTRAINT_TABLE;
	}

	/**
	 * @param string $name
	 */
	private function getConstraintTypeItemId( $name ) {
		return $this->getDefaultConfig()->get( 'WBQualityConstraints' . $name . 'ConstraintId' );
	}

	/**
	 * Adds temporary test data to database.
	 *
	 * @throws DBUnexpectedError
	 */
	public function addDBData() {
		$config = $this->getDefaultConfig();
		$constraints = [
			[
				'constraint_guid' => 'P1$ecb8f617-90f1-4ef3-afab-f4bf3881ec28',
				'pid' => 1,
				'constraint_type_qid' => $this->getConstraintTypeItemId( 'CommonsLink' ),
				'constraint_parameters' => json_encode(
					$this->namespaceParameter( 'File' )
				)
			],
			[
				'constraint_guid' => 'P10$0bdbe1cb-8afb-4d16-9fd0-c1d0a5b717ce',
				'pid' => 10,
				'constraint_type_qid' => $this->getConstraintTypeItemId( 'CommonsLink' ),
				'constraint_parameters' => json_encode( array_merge(
					$this->namespaceParameter( 'File' ),
					$this->exceptionsParameter( [ 'Q5' ] )
				) )
			],
			[
				'constraint_guid' => 'P11$01c56d1f-b3ce-4a1a-bef7-8c652f395eb2',
				'pid' => 11,
				'constraint_type_qid' => $this->getConstraintTypeItemId( 'UsedAsQualifier' ),
				'constraint_parameters' => json_encode( [
					$this->getDefaultConfig()->get( 'WBQualityConstraintsExceptionToConstraintId' ) => [
						[ 'snaktype' => 'novalue', 'property' => 'P2316' ]
					]
				] )
			],
			[
				'constraint_guid' => 'P1$6ad9eb64-13fd-43a1-afc8-84857108bd59',
				'pid' => 1,
				'constraint_type_qid' => $this->getConstraintTypeItemId( 'MandatoryQualifier' ),
				'constraint_parameters' => json_encode(
					$this->propertyParameter( 'P2' )
				)
			],
			[
				'constraint_guid' => 'P1$cfff6d73-320c-43c5-8582-e9cbb98e2ca2',
				'pid' => 1,
				'constraint_type_qid' => $this->getConstraintTypeItemId( 'ConflictsWith' ),
				'constraint_parameters' => json_encode(
					$this->propertyParameter( 'P2' )
				)
			],
			[
				'constraint_guid' => 'P1$c81a981e-4eab-44c9-8aa2-62c63072902e',
				'pid' => 1,
				'constraint_type_qid' => $this->getConstraintTypeItemId( 'Inverse' ),
				'constraint_parameters' => json_encode(
					$this->propertyParameter( 'P2' )
				)
			],
			[
				'constraint_guid' => 'P1$2040dee1-8c9d-45b7-ac01-2ce8046f578b',
				'pid' => 1,
				'constraint_type_qid' => $this->getConstraintTypeItemId( 'AllowedQualifiers' ),
				'constraint_parameters' => json_encode(
					$this->propertiesParameter( [ 'P2', 'P3' ] )
				)
			],
			[
				'constraint_guid' => 'P1$09a20b38-fe36-444b-b9ed-22eb46c3ea73',
				'pid' => 1,
				'constraint_type_qid' => $this->getConstraintTypeItemId( 'DifferenceWithinRange' ),
				'constraint_parameters' => json_encode( array_merge(
					$this->propertyParameter( 'P2' ),
					$this->rangeParameter( 'quantity', 0, 150 )
				) )
			],
			[
				'constraint_guid' => 'P1$3dac547d-3faf-4198-9b9c-0ba1eae32752',
				'pid' => 1,
				'constraint_type_qid' => $this->getConstraintTypeItemId( 'Format' ),
				'constraint_parameters' => json_encode(
					$this->formatParameter( '[0-9]' )
				)
			],
			[
				'constraint_guid' => 'P1$cc5708c8-3ec8-4bf3-8931-409530e4d634',
				'pid' => 1,
				'constraint_type_qid' => $this->getConstraintTypeItemId( 'MultiValue' ),
				'constraint_parameters' => '{}'
			],
			[
				'constraint_guid' => 'P1$021b2558-8e7c-4c2c-ba14-4596dc11536e',
				'pid' => 1,
				'constraint_type_qid' => $this->getConstraintTypeItemId( 'DistinctValues' ),
				'constraint_parameters' => '{}'
			],
			[
				'constraint_guid' => 'P1$3ddc8c54-c056-425c-8745-d257004d585f',
				'pid' => 1,
				'constraint_type_qid' => $this->getConstraintTypeItemId( 'SingleValue' ),
				'constraint_parameters' => '{}'
			],
			[
				'constraint_guid' => 'P1$dc4464ed-42a5-47f6-b725-04b1d9d1dfc6',
				'pid' => 1,
				'constraint_type_qid' => $this->getConstraintTypeItemId( 'Symmetric' ),
				'constraint_parameters' => '{}'
			],
			[
				'constraint_guid' => 'P1$713ec92d-cd08-413d-b4dc-8e6eeb8c7861',
				'pid' => 1,
				'constraint_type_qid' => $this->getConstraintTypeItemId( 'UsedAsQualifier' ),
				'constraint_parameters' => '{}'
			],
			[
				'constraint_guid' => 'P1$a083d339-7bd6-4737-a987-b55ae8a1a5f3',
				'pid' => 1,
				'constraint_type_qid' => $this->getConstraintTypeItemId( 'OneOf' ),
				'constraint_parameters' => json_encode(
					$this->itemsParameter( [ 'Q2', 'Q3' ] )
				)
			],
			[
				'constraint_guid' => 'P1$b8587fb1-7315-46ba-9d04-07f0e9af857d',
				'pid' => 1,
				'constraint_type_qid' => $this->getConstraintTypeItemId( 'Range' ),
				'constraint_parameters' => json_encode(
					$this->rangeParameter( 'time', '0', '2015' )
				)
			],
			[
				'constraint_guid' => 'P1$83ee554c-41fd-4bfa-ae9b-960d0eee2fa4',
				'pid' => 1,
				'constraint_type_qid' => $this->getConstraintTypeItemId( 'ValueRequiresClaim' ),
				'constraint_parameters' => json_encode( array_merge(
					$this->propertyParameter( 'P2' ),
					$this->itemsParameter( [ 'Q2' ] )
				) )
			],
			[
				'constraint_guid' => 'P1$370a45b5-b007-455d-b5fa-03b90c629fe5',
				'pid' => 1,
				'constraint_type_qid' => $this->getConstraintTypeItemId( 'ItemRequiresClaim' ),
				'constraint_parameters' => json_encode( array_merge(
					$this->propertyParameter( 'P2' ),
					$this->itemsParameter( [ 'Q2', 'Q3' ] )
				) )
			],
			[
				'constraint_guid' => 'P1$831d9d5d-ed77-48f2-8433-fb80a9ef3aad',
				'pid' => 1,
				'constraint_type_qid' => $this->getConstraintTypeItemId( 'Type' ),
				'constraint_parameters' => json_encode( array_merge(
					$this->relationParameter( 'instance' ),
					$this->classParameter( [ 'Q2', 'Q3' ] )
				) )
			],
			[
				'constraint_guid' => 'P1$fe667c64-be46-4521-a54d-8a895b6005b0',
				'pid' => 1,
				'constraint_type_qid' => $this->getConstraintTypeItemId( 'ValueType' ),
				'constraint_parameters' => json_encode( array_merge(
					$this->relationParameter( 'instance' ),
					$this->classParameter( [ 'Q2', 'Q3' ] )
				) )
			],
			[
				'constraint_guid' => 'P3$0a011ed8-1e2b-470c-a306-fb8ea6953779',
				'pid' => 3,
				'constraint_type_qid' => 'Is not inside',
				'constraint_parameters' => '{}'
			],
			[
				'constraint_guid' => 'P6$ad792000-6a12-413d-9fe5-11d2467b7a92',
				'pid' => 6,
				'constraint_type_qid' => $this->getConstraintTypeItemId( 'UsedAsQualifier' ),
				'constraint_parameters' => json_encode(
					$this->statusParameter( 'mandatory' )
				)
			],
			[
				'constraint_guid' => 'P7$a3f746e7-66a0-46fd-96ab-6ff6638332a4',
				'pid' => 7,
				'constraint_type_qid' => $this->getConstraintTypeItemId( 'UsedAsQualifier' ),
				'constraint_parameters' => '{}'
			],
			[
				'constraint_guid' => 'P8$34c8af8e-bb50-4458-994b-f355ff899fff',
				'pid' => 8,
				'constraint_type_qid' => $this->getConstraintTypeItemId( 'UsedAsQualifier' ),
				'constraint_parameters' => '{"@error":{"toolong":true}}'
			],
			[
				'constraint_guid' => 'P9$43053ee8-79da-4326-a2ac-f85098291db3',
				'pid' => 9,
				'constraint_type_qid' => $this->getConstraintTypeItemId( 'UsedAsQualifier' ),
				'constraint_parameters' => '{"P2316":[{"snaktype":"novalue","property":"P2316"}],"P2303":[{"snaktype":"novalue","property":"P2316"}]}'
			],
			[
				'constraint_guid' => 'P1$a1b1f3d8-6215-4cb6-9edd-3af126ae134f',
				'pid' => 1,
				'constraint_type_qid' => $this->getConstraintTypeItemId( 'UsedForValuesOnly' ),
				'constraint_parameters' => '{}'
			],
		];
		$this->constraintCount = count( array_filter(
			$constraints,
			function ( $constraint ) {
				return $constraint['pid'] === 1;
			}
		) );

		$this->db->delete( CONSTRAINT_TABLE, '*' );
		$this->db->insert( CONSTRAINT_TABLE, $constraints );
	}

	public function testCheckOnEntityId() {
		$entity = NewItem::withId( 'Q1' )
			->andStatement(
				NewStatement::forProperty( 'P1' )
					->withValue( 'foo' )
			)
			->build();
		$this->lookup->addEntity( $entity );

		$result = $this->constraintChecker->checkAgainstConstraintsOnEntityId( $entity->getId() );

		$this->assertCount( $this->constraintCount, $result, 'Every constraint should be represented by one result' );
		foreach ( $result as $checkResult ) {
			$this->assertNotSame( 'todo', $checkResult->getStatus(), 'Constraints should not be unimplemented' );
		}
	}

	public function testCheckOnEntityIdEmptyResult() {
		$entity = NewItem::withId( 'Q2' )
			->andStatement(
				NewStatement::forProperty( 'P2' )
					->withValue( 'foo' )
			)
			->build();
		$this->lookup->addEntity( $entity );

		$result = $this->constraintChecker->checkAgainstConstraintsOnEntityId( $entity->getId() );

		$this->assertCount( 0, $result, 'Should be empty' );
	}

	public function testCheckOnEntityIdUnknownConstraint() {
		$entity = NewItem::withId( 'Q3' )
			->andStatement(
				NewStatement::forProperty( 'P3' )
					->withValue( 'foo' )
			)
			->build();
		$this->lookup->addEntity( $entity );

		$result = $this->constraintChecker->checkAgainstConstraintsOnEntityId( $entity->getId() );

		$this->assertCount( 1, $result, 'Should be one result' );
		$this->assertEquals( 'todo', $result[ 0 ]->getStatus(), 'Should be marked as a todo' );
	}

	public function testCheckOnEntityIdNoValue() {
		$entity = NewItem::withId( 'Q4' )
			->andStatement(
				NewStatement::noValueFor( 'P4' )
			)
			->build();
		$this->lookup->addEntity( $entity );

		$result = $this->constraintChecker->checkAgainstConstraintsOnEntityId( $entity->getId() );

		$this->assertCount( 0, $result, 'Should be empty' );
	}

	public function testCheckOnEntityIdKnownException() {
		$entity = NewItem::withId( 'Q5' )
			->andStatement(
				NewStatement::forProperty( 'P10' )
					->withValue( 'foo' )
			)
			->build();
		$this->lookup->addEntity( $entity );

		$result = $this->constraintChecker->checkAgainstConstraintsOnEntityId( $entity->getId() );

		$this->assertEquals( 'exception', $result[ 0 ]->getStatus(), 'Should be an exception' );
	}

	public function testCheckOnEntityIdBrokenException() {
		$entity = NewItem::withId( 'Q5' )
			->andStatement( NewStatement::noValueFor( 'P11' ) )
			->build();
		$this->lookup->addEntity( $entity );

		$result = $this->constraintChecker->checkAgainstConstraintsOnEntityId( $entity->getId() );

		$this->assertEquals( 'bad-parameters', $result[ 0 ]->getStatus(), 'Should be a bad parameter but not throw an exception' );
	}

	public function testCheckOnEntityIdMandatoryConstraint() {
		$entity = NewItem::withId( 'Q6' )
			->andStatement( NewStatement::noValueFor( 'P6' ) )
			->build();
		$this->lookup->addEntity( $entity );

		$results = $this->constraintChecker->checkAgainstConstraintsOnEntityId( $entity->getId() );

		$this->assertCount( 1, $results );
		$result = $results[0];
		$this->assertViolation( $result );
		$this->assertArrayHasKey( 'constraint_status', $result->getParameters() );
		$this->assertSame( [ 'mandatory' ], $result->getParameters()[ 'constraint_status' ] );
	}

	public function testCheckOnEntityIdNonMandatoryConstraint() {
		$entity = NewItem::withId( 'Q7' )
			->andStatement( NewStatement::noValueFor( 'P7' ) )
			->build();
		$this->lookup->addEntity( $entity );

		$result = $this->constraintChecker->checkAgainstConstraintsOnEntityId( $entity->getId() );

		$this->assertEquals( 'warning', $result[ 0 ]->getStatus(), 'Should be a warning' );
	}

	public function testCheckOnEntityIdSelectConstraintIds() {
		$entity = NewItem::withId( 'Q1' )
			->andStatement(
				NewStatement::forProperty( 'P1' )
					->withValue( 'foo' )
			)
			->build();
		$this->lookup->addEntity( $entity );

		$result = $this->constraintChecker->checkAgainstConstraintsOnEntityId(
			$entity->getId(),
			[
				'P1$ecb8f617-90f1-4ef3-afab-f4bf3881ec28',
				'P1$6ad9eb64-13fd-43a1-afc8-84857108bd59',
				'P1$cfff6d73-320c-43c5-8582-e9cbb98e2ca2',
			]
		);

		$this->assertCount( 3, $result, 'Every selected constraint should be represented by one result' );
		foreach ( $result as $checkResult ) {
			$this->assertNotSame( 'todo', $checkResult->getStatus(), 'Constraints should not be unimplemented' );
		}
	}

	public function testCheckOnClaimId() {
		$statement = NewStatement::forProperty( 'P1' )
			->withValue( 'foo' )
			->withGuid( 'Q1$c0f25a6f-9e33-41c8-be34-c86a730ff30b' )
			->build();
		$entity = NewItem::withId( 'Q1' )
			->andStatement( $statement )
			->build();
		$this->lookup->addEntity( $entity );

		$result = $this->constraintChecker->checkAgainstConstraintsOnClaimId(
			$statement->getGuid()
		);

		$this->assertCount( $this->constraintCount, $result, 'Every constraint should be represented by one result' );
	}

	public function testCheckOnClaimIdEmptyResult() {
		$statement = NewStatement::forProperty( 'P2' )
			->withValue( 'foo' )
			->withGuid( 'Q2$1d1fd258-91ca-4db5-91e4-26219c5aae7a' )
			->build();
		$entity = NewItem::withId( 'Q2' )
			->andStatement( $statement )
			->build();
		$this->lookup->addEntity( $entity );

		$result = $this->constraintChecker->checkAgainstConstraintsOnClaimId(
			$statement->getGuid()
		);

		$this->assertCount( 0, $result, 'Should be empty' );
	}

	public function testCheckOnClaimIdUnknownClaimId() {
		$result = $this->constraintChecker->checkAgainstConstraintsOnClaimId(
			'Q99$does-not-exist' );

		$this->assertCount( 0, $result, 'Should be empty' );
	}

	public function testCheckConstraintParametersOnPropertyId() {
		$entity = new Property( new PropertyId( 'P1' ), null, 'time' );
		$this->lookup->addEntity( $entity );

		$result = $this->constraintChecker->checkConstraintParametersOnPropertyId(
			$entity->getId()
		);

		$this->assertCount( $this->constraintCount, $result, 'Every constraint should be represented by one result' );
		foreach ( $result as $constraintGuid => $constraintResult ) {
			$this->assertSame( [], $constraintResult, 'Constraint should have no bad parameters' );
		}
	}

	public function testCheckConstraintParametersOnPropertyIdWithError() {
		$result = $this->constraintChecker->checkConstraintParametersOnPropertyId( new PropertyId( 'P8' ) );

		$this->assertCount( 1, $result, 'Every constraint should be represented by one result' );
		$this->assertCount( 1, $result['P8$34c8af8e-bb50-4458-994b-f355ff899fff'], 'The constraint should have one exception' );
		$this->assertInstanceOf( ConstraintParameterException::class, $result['P8$34c8af8e-bb50-4458-994b-f355ff899fff'][0] );
	}

	public function testCheckConstraintParametersOnPropertyIdWithMetaErrors() {
		$result = $this->constraintChecker->checkConstraintParametersOnPropertyId( new PropertyId( 'P9' ) );

		$this->assertCount( 1, $result, 'Every constraint should be represented by one result' );
		$this->assertCount( 2, $result['P9$43053ee8-79da-4326-a2ac-f85098291db3'], 'The constraint should have two exceptions' );
		$this->assertInstanceOf( ConstraintParameterException::class, $result['P9$43053ee8-79da-4326-a2ac-f85098291db3'][0] );
		$this->assertInstanceOf( ConstraintParameterException::class, $result['P9$43053ee8-79da-4326-a2ac-f85098291db3'][1] );
	}

	public function testCheckConstraintParametersOnConstraintId() {
		$result = $this->constraintChecker->checkConstraintParametersOnConstraintId( 'P1$ecb8f617-90f1-4ef3-afab-f4bf3881ec28' );

		$this->assertSame( [], $result, 'Constraint should exist and have no bad parameters' );
	}

	public function testCheckConstraintParametersOnConstraintIdWhenConstraintDoesNotExist() {
		$result = $this->constraintChecker->checkConstraintParametersOnConstraintId( 'P1$1735c111-e88c-42f9-8b7a-0692c9c797a3' );

		$this->assertNull( $result, 'Constraint should not exist' );
	}

	public function testCheckConstraintParametersOnConstraintIdWithError() {
		$result = $this->constraintChecker->checkConstraintParametersOnConstraintId( 'P8$34c8af8e-bb50-4458-994b-f355ff899fff' );

		$this->assertCount( 1, $result, 'The constraint should have one exception' );
		$this->assertInstanceOf( ConstraintParameterException::class, $result[0] );
	}

	public function testCheckConstraintParametersOnConstraintIdWithMetaErrors() {
		$result = $this->constraintChecker->checkConstraintParametersOnConstraintId( 'P9$43053ee8-79da-4326-a2ac-f85098291db3' );

		$this->assertCount( 2, $result, 'The constraint should have two exceptions' );
		$this->assertInstanceOf( ConstraintParameterException::class, $result[0] );
		$this->assertInstanceOf( ConstraintParameterException::class, $result[1] );
	}

}
