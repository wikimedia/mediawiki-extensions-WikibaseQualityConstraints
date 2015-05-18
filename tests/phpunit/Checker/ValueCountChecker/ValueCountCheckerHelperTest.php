<?php

namespace WikibaseQuality\ConstraintReport\Test\ValueCountChecker;

use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Claim\Claim;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\StatementList;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ValueCountCheckerHelper;


/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ValueCountCheckerHelper
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class ValueCountCheckerHelperTest extends \MediaWikiTestCase {

	private $statementList;

	protected function setUp() {
		parent::setUp();
		$statement1 = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P1' ), new EntityIdValue( new ItemId( 'Q1' ) ) ) ) );
		$statement2 = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P2' ), new EntityIdValue( new ItemId( 'Q2' ) ) ) ) );
		$statement3 = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P2' ), new EntityIdValue( new ItemId( 'Q3' ) ) ) ) );
		$statement4 = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P2' ), new EntityIdValue( new ItemId( 'Q4' ) ) ) ) );
		$statement4->setRank( Statement::RANK_DEPRECATED );
		$this->statementList = new StatementList( array( $statement1, $statement2, $statement3, $statement4 ) );
	}

	protected function tearDown() {
		unset( $this->statementList );
		parent::tearDown();
	}

	public function testGetPropertyCount() {
		$checker = new ValueCountCheckerHelper();
		$propertyCount = $checker->getPropertyCount( $this->statementList );

		$this->assertEquals( 1, $propertyCount[1] );
		$this->assertEquals( 2, $propertyCount[2] );
	}
}