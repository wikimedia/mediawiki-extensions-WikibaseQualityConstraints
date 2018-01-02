<?php

namespace WikibaseQuality\ConstraintReport\Test\ValueCountChecker;

use PHPUnit_Framework_TestCase;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\StatementList;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ValueCountCheckerHelper;

/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ValueCountCheckerHelper
 *
 * @group WikibaseQualityConstraints
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class ValueCountCheckerHelperTest extends PHPUnit_Framework_TestCase {

	/**
	 * @var StatementList
	 */
	private $statementList;

	protected function setUp() {
		parent::setUp();
		$statement1 = new Statement( new PropertyValueSnak( new PropertyId( 'P1' ), new EntityIdValue( new ItemId( 'Q1' ) ) ) );
		$statement2 = new Statement( new PropertyValueSnak( new PropertyId( 'P2' ), new EntityIdValue( new ItemId( 'Q2' ) ) ) );
		$statement3 = new Statement( new PropertyValueSnak( new PropertyId( 'P2' ), new EntityIdValue( new ItemId( 'Q3' ) ) ) );
		$statement4 = new Statement( new PropertyValueSnak( new PropertyId( 'P2' ), new EntityIdValue( new ItemId( 'Q4' ) ) ) );
		$statement4->setRank( Statement::RANK_DEPRECATED );
		$this->statementList = new StatementList( [ $statement1, $statement2, $statement3, $statement4 ] );
	}

	public function testGetPropertyCount() {
		$checker = new ValueCountCheckerHelper();
		$propertyCount = $checker->getPropertyCount( $this->statementList );

		$this->assertEquals( 1, $propertyCount['P1'] );
		$this->assertEquals( 2, $propertyCount['P2'] );
	}

}
