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
use Wikibase\DataModel\Statement\Statement;
use Wikibase\Lib\Store\EntityTitleLookup;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Api\CheckingResultsBuilder;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachingMetadata;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\MainSnakContext;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\QualifierContext;
use WikibaseQuality\ConstraintReport\ConstraintCheck\DelegatingConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\NullResult;
use WikibaseQuality\ConstraintReport\ConstraintParameterRenderer;
use WikibaseQuality\ConstraintReport\Tests\DefaultConfig;
use WikibaseQuality\ConstraintReport\Tests\Fake\FakeSnakContext;

/**
 * @covers \WikibaseQuality\ConstraintReport\ConstraintCheck\Api\CheckingResultsBuilder
 *
 * @license GNU GPL v2+
 */
class CheckingResultsBuilderTest extends \PHPUnit_Framework_TestCase {

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
				return $title;
			} ) );
		return new CheckingResultsBuilder(
			$delegatingConstraintChecker,
			$entityTitleLookup,
			$entityIdFormatter,
			new ConstraintParameterRenderer(
				$entityIdFormatter, $this->getMock( ValueFormatter::class )
			),
			$this->getDefaultConfig()
		);
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
		$checkResult->setCachingMetadata( CachingMetadata::ofMaximumAgeInSeconds( 10 ) );

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
		$checkResult->setCachingMetadata( CachingMetadata::ofMaximumAgeInSeconds( 10 ) );

		$result = $this->getResultsBuilder()->checkResultToArray( $checkResult );

		$this->assertSame( [ 'maximumAgeInSeconds' => 10 ], $result['cached'] );
	}

}
