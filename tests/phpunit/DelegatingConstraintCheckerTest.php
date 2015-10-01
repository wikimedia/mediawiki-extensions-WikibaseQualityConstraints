<?php

namespace WikibaseQuality\ConstraintReport\Test\ConstraintChecker;

use Wikibase\DataModel\Entity\ItemId;
use WikibaseQuality\ConstraintReport\ConstraintReportFactory;
use WikibaseQuality\Tests\Helper\JsonFileEntityLookup;


/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\DelegatingConstraintChecker
 *
 * @group WikibaseQualityConstraints
 * @group Database
 *
 * @uses   WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 * @uses   WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser
 * @uses   WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\RangeChecker
 * @uses   WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\DiffWithinRangeChecker
 * @uses   WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\SingleValueChecker
 * @uses   WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\MultiValueChecker
 * @uses   WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\UniqueValueChecker
 * @uses   WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\OneOfChecker
 * @uses   WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\CommonsLinkChecker
 * @uses   WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\TargetRequiredClaimChecker
 * @uses   WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\ItemChecker
 * @uses   WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\ConflictsWithChecker
 * @uses   WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\SymmetricChecker
 * @uses   WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\InverseChecker
 * @uses   WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\FormatChecker
 * @uses   WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\QualifierChecker
 * @uses   WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\QualifiersChecker
 * @uses   WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\MandatoryQualifiersChecker
 * @uses   WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\TypeChecker
 * @uses   WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\ValueTypeChecker
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class DelegatingConstraintCheckerTest extends \MediaWikiTestCase {

	private $constraintChecker;
	private $lookup;

	protected function setUp() {
		parent::setUp();
		$this->lookup = new JsonFileEntityLookup( __DIR__ );
		$factory = new ConstraintReportFactory( $this->lookup );
		$this->constraintChecker = $factory->getConstraintChecker();

		// specify database tables used by this test
		$this->tablesUsed[ ] = CONSTRAINT_TABLE;
	}

	protected function tearDown() {
		unset( $this->lookup );
		unset( $this->constraintChecker );
		parent::tearDown();
	}

	/**
	 * Adds temporary test data to database.
	 *
	 * @throws \DBUnexpectedError
	 */
	public function addDBData() {
		$this->db->delete(
			CONSTRAINT_TABLE,
			'*'
		);

		$this->db->insert(
			CONSTRAINT_TABLE,
			array (
				array (
					'constraint_guid' => '13',
					'pid' => 1,
					'constraint_type_qid' => 'Commons link',
					'constraint_parameters' => json_encode(
						array ( 'namespace' => 'File' ) )
				),
				array (
					'constraint_guid' => '19',
					'pid' => 10,
					'constraint_type_qid' => 'Commons link',
					'constraint_parameters' => json_encode(
						array (
							'namespace' => 'File',
							'known_exception' => 'Q5'
						) )
				),
				array (
					'constraint_guid' => '20',
					'pid' => 1,
					'constraint_type_qid' => 'Mandatory qualifiers',
					'constraint_parameters' => json_encode(
						array ( 'property' => 'P2' ) )
				),
				array (
					'constraint_guid' => '14',
					'pid' => 1,
					'constraint_type_qid' => 'Conflicts with',
					'constraint_parameters' => json_encode(
						array ( 'property' => 'P2' ) )
				),
				array (
					'constraint_guid' => '15',
					'pid' => 1,
					'constraint_type_qid' => 'Inverse',
					'constraint_parameters' => json_encode(
						array ( 'property' => 'P2' ) )
				),
				array (
					'constraint_guid' => '16',
					'pid' => 1,
					'constraint_type_qid' => 'Qualifiers',
					'constraint_parameters' => json_encode(
						array ( 'property' => 'P2,P3' ) )
				),
				array (
					'constraint_guid' => '17',
					'pid' => 1,
					'constraint_type_qid' => 'Diff within range',
					'constraint_parameters' => json_encode(
						array (
							'property' => 'P2',
							'minimum_quantity' => 0,
							'maximum_quantity' => 150
						) )
				),
				array (
					'constraint_guid' => '18',
					'pid' => 1,
					'constraint_type_qid' => 'Format',
					'constraint_parameters' => json_encode(
						array ( 'pattern' => '[0-9]' ) )
				),
				array (
					'constraint_guid' => '1',
					'pid' => 1,
					'constraint_type_qid' => 'Multi value',
					'constraint_parameters' => '{}'
				),
				array (
					'constraint_guid' => '2',
					'pid' => 1,
					'constraint_type_qid' => 'Unique value',
					'constraint_parameters' => '{}'
				),
				array (
					'constraint_guid' => '3',
					'pid' => 1,
					'constraint_type_qid' => 'Single value',
					'constraint_parameters' => '{}'
				),
				array (
					'constraint_guid' => '4',
					'pid' => 1,
					'constraint_type_qid' => 'Symmetric',
					'constraint_parameters' => '{}'
				),
				array (
					'constraint_guid' => '5',
					'pid' => 1,
					'constraint_type_qid' => 'Qualifier',
					'constraint_parameters' => '{}'
				),
				array (
					'constraint_guid' => '6',
					'pid' => 1,
					'constraint_type_qid' => 'One of',
					'constraint_parameters' => json_encode(
						array ( 'item' => 'Q2,Q3' ) )
				),
				array (
					'constraint_guid' => '7',
					'pid' => 1,
					'constraint_type_qid' => 'Range',
					'constraint_parameters' => json_encode(
						array (
							'minimum_quantity' => 0,
							'maximum_quantity' => 2015
						) )
				),
				array (
					'constraint_guid' => '8',
					'pid' => 1,
					'constraint_type_qid' => 'Target required claim',
					'constraint_parameters' => json_encode(
						array (
							'property' => 'P2',
							'item' => 'Q2'
						) )
				),
				array (
					'constraint_guid' => '9',
					'pid' => 1,
					'constraint_type_qid' => 'Item',
					'constraint_parameters' => json_encode(
						array (
							'property' => 'P2',
							'item' => 'Q2,Q3'
						) )
				),
				array (
					'constraint_guid' => '10',
					'pid' => 1,
					'constraint_type_qid' => 'Type',
					'constraint_parameters' => json_encode(
						array (
							'class' => 'Q2,Q3',
							'relation' => 'instance'
						) )
				),
				array (
					'constraint_guid' => '11',
					'pid' => 1,
					'constraint_type_qid' => 'Value type',
					'constraint_parameters' => json_encode(
						array (
							'class' => 'Q2,Q3',
							'relation' => 'instance'
						) )
				),
				array (
					'constraint_guid' => '12',
					'pid' => 3,
					'constraint_type_qid' => 'Is not inside',
					'constraint_parameters' => '{}'
				)
			)
		);
	}

	public function testCheckAgainstConstraints() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
		$result = $this->constraintChecker->checkAgainstConstraints( $entity );
		$this->assertEquals( 18, count( $result ), 'Every constraint should be represented by one result' );
	}

	public function testCheckAgainstConstraintsWithoutEntity() {
		$result = $this->constraintChecker->checkAgainstConstraints( null );
		$this->assertEquals( null, $result, 'Should return null' );
	}

	public function testCheckAgainstConstraintsDoesNotCrashWhenResultIsEmpty() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q2' ) );
		$result = $this->constraintChecker->checkAgainstConstraints( $entity );
		$this->assertEquals( 0, count( $result ), 'Should be empty' );
	}

	public function testCheckAgainstConstraintsWithConstraintThatDoesNotBelongToCheckedConstraints() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q3' ) );
		$result = $this->constraintChecker->checkAgainstConstraints( $entity );
		$this->assertEquals( 1, count( $result ), 'Should be one result' );
		$this->assertEquals( 'todo', $result[ 0 ]->getStatus(), 'Should be marked as a todo' );
	}

	public function testCheckAgainstConstraintsDoesNotCrashWhenStatementHasNovalue() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q4' ) );
		$result = $this->constraintChecker->checkAgainstConstraints( $entity );
		$this->assertEquals( 0, count( $result ), 'Should be empty' );
	}

	public function testCheckAgainstConstraintsWithKnownException() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q5' ) );
		$result = $this->constraintChecker->checkAgainstConstraints( $entity );
		$this->assertEquals( 'exception', $result[ 0 ]->getStatus(), 'Should be an exception' );
	}

}