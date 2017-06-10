<?php

namespace WikibaseQuality\ConstraintReport\Tests;

use HashConfig;
use Wikibase\DataModel\Statement\Statement;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\SparqlHelper;

/**
 * @author Lucas Werkmeister
 * @license GNU GPL v2+
 */
trait SparqlHelperMock {

	/**
	 * @param string $expectedId
	 * @param string[] $expectedClasses
	 * @param boolean $expectedWithInstance
	 * @param boolean $result
	 * @return SparqlHelper
	 */
	private function getSparqlHelperMockHasType(
		$expectedId, array $expectedClasses, $expectedWithInstance,
		$result ) {

		$mock = $this->getMockBuilder( SparqlHelper::class )
			  ->disableOriginalConstructor()
			  ->getMock();

		$mock->expects( $this->exactly( 1 ) )
			->method( 'hasType' )
			->willReturn( $result )
			->withConsecutive( [
				$this->equalTo( $expectedId ),
				$this->equalTo( $expectedClasses ),
				$this->equalTo( $expectedWithInstance )
			] );

		return $mock;
	}

	/**
	 * @param Statement $expectedStatement
	 * @param (EntityId|null)[] $result
	 * @return SparqlHelper
	 */
	private function getSparqlHelperMockFindEntities(
		Statement $expectedStatement,
		$result ) {

		$mock = $this->getMockBuilder( SparqlHelper::class )
			  ->disableOriginalConstructor()
			  ->getMock();

		$mock->expects( $this->exactly( 1 ) )
			->method( 'findEntitiesWithSameStatement' )
			->willReturn( $result )
			->withConsecutive( [ $this->equalTo( $expectedStatement ) ] );

		return $mock;
	}

}
