<?php

namespace WikibaseQuality\ConstraintReport\Tests\Html;

use InvalidArgumentException;
use PHPUnit4And6Compat;
use WikibaseQuality\ConstraintReport\Html\HtmlTableBuilder;
use WikibaseQuality\ConstraintReport\Html\HtmlTableCellBuilder;
use WikibaseQuality\ConstraintReport\Html\HtmlTableHeaderBuilder;

/**
 * @covers \WikibaseQuality\ConstraintReport\Html\HtmlTableBuilder
 *
 * @group WikibaseQuality
 *
 * @uses   \WikibaseQuality\ConstraintReport\Html\HtmlTableHeaderBuilder
 * @uses   \WikibaseQuality\ConstraintReport\Html\HtmlTableCellBuilder
 *
 * @author BP2014N1
 * @license GPL-2.0-or-later
 */
class HtmlTableBuilderTest extends \PHPUnit\Framework\TestCase {
	use PHPUnit4And6Compat;

	/**
	 * @dataProvider constructDataProvider
	 */
	public function testConstruct(
		$headers,
		array $expectedHeaders = null,
		$expectedIsSortable,
		$expectedException
	) {
		if ( $expectedException !== null ) {
			$this->setExpectedException( $expectedException );
		}
		$htmlTable = new HtmlTableBuilder( $headers );

		$this->assertEquals( $expectedHeaders, $htmlTable->getHeaders() );
		$this->assertEquals( $expectedIsSortable, $htmlTable->isSortable() );
	}

	public function constructDataProvider() {
		return [
			[
				[
					'foo',
					'bar'
				],
				[
					new \WikibaseQuality\ConstraintReport\Html\HtmlTableHeaderBuilder( 'foo' ),
					new \WikibaseQuality\ConstraintReport\Html\HtmlTableHeaderBuilder( 'bar' )
				],
				false,
				null
			],
			[
				[
					new HtmlTableHeaderBuilder( 'foo', true ),
					'bar'
				],
				[
					new \WikibaseQuality\ConstraintReport\Html\HtmlTableHeaderBuilder( 'foo', true ),
					new \WikibaseQuality\ConstraintReport\Html\HtmlTableHeaderBuilder( 'bar' )
				],
				true,
				null
			],
			[
				[
					new \WikibaseQuality\ConstraintReport\Html\HtmlTableHeaderBuilder( 'foo', true ),
					new \WikibaseQuality\ConstraintReport\Html\HtmlTableHeaderBuilder( 'bar' )
				],
				[
					new \WikibaseQuality\ConstraintReport\Html\HtmlTableHeaderBuilder( 'foo', true ),
					new HtmlTableHeaderBuilder( 'bar' )
				],
				true,
				null
			],
			[
				[ 42 ],
				null,
				false,
				InvalidArgumentException::class
			],
		];
	}

	public function testAppendRow() {
		$htmlTable = new HtmlTableBuilder( [ 'fu', 'bar' ] );
		$htmlTable->appendRow( [ 'foo', 'bar' ] );

		$this->assertEquals(
			[
				[
					new \WikibaseQuality\ConstraintReport\Html\HtmlTableCellBuilder( 'foo' ),
					new \WikibaseQuality\ConstraintReport\Html\HtmlTableCellBuilder( 'bar' )
				]
			],
			$htmlTable->getRows()
		);
	}

	/**
	 * @dataProvider appendRowsDataProvider
	 */
	public function testAppendRows(
		array $rows,
		array $expectedRows = null,
		$expectedException = null
	) {
		if ( $expectedException ) {
			$this->setExpectedException( $expectedException );
		}

		$htmlTable = new HtmlTableBuilder( [ 'fu', 'bar' ] );
		$htmlTable->appendRows( $rows );

		$this->assertEquals( $expectedRows, $htmlTable->getRows() );
	}

	/**
	 * Test cases for testAppendRows
	 */
	public function appendRowsDataProvider() {
		return [
			[
				[
					[
						'foo',
						'bar'
					]
				],
				[
					[
						new \WikibaseQuality\ConstraintReport\Html\HtmlTableCellBuilder( 'foo' ),
						new HtmlTableCellBuilder( 'bar' )
					]
				]
			],
			[
				[
					[
						new \WikibaseQuality\ConstraintReport\Html\HtmlTableCellBuilder( 'foo' ),
						'bar'
					]
				],
				[
					[
						new \WikibaseQuality\ConstraintReport\Html\HtmlTableCellBuilder( 'foo' ),
						new \WikibaseQuality\ConstraintReport\Html\HtmlTableCellBuilder( 'bar' )
					]
				]
			],
			[
				[
					[
						'foo',
						42
					]
				],
				null,
				InvalidArgumentException::class
			],
			[
				[
					42
				],
				null,
				InvalidArgumentException::class
			]
		];
	}

	/**
	 * @dataProvider toHtmlDataProvider
	 */
	public function testToHtml( $headers, $rows, $expectedHtml ) {
		//Create table
		$htmlTable = new HtmlTableBuilder( $headers );
		$htmlTable->appendRows( $rows );

		// Run assertions
		$actualHtml = $htmlTable->toHtml();
		$this->assertEquals( $expectedHtml, $actualHtml );
	}

	public function toHtmlDataProvider() {
		return [
			[
				[
					$this->getHtmlTableHeaderMock( 'fu' ),
					$this->getHtmlTableHeaderMock( 'bar' )
				],
				[
					[
						$this->getHtmlTableCellMock( 'fucked up' ),
						$this->getHtmlTableCellMock( 'beyond all recognition' )
					]
				],
				'<table class="wikitable">'
					. '<tr><th>fu</th><th>bar</th></tr>'
					. '<tr><td>fucked up</td><td>beyond all recognition</td></tr>'
					. '</table>'
			],
			[
				[
					$this->getHtmlTableHeaderMock( 'fu' ),
					$this->getHtmlTableHeaderMock( 'bar', true )
				],
				[
					[
						$this->getHtmlTableCellMock( 'fucked up' ),
						$this->getHtmlTableCellMock( 'beyond all recognition' )
					]
				],
				'<table class="wikitable sortable jquery-tablesort">'
					. '<tr><th>fu</th><th>bar</th></tr>'
					. '<tr><td>fucked up</td><td>beyond all recognition</td></tr>'
					. '</table>'
			]
		];
	}

	/**
	 * Creates HtmlHeaderCell mock, which returns only the content when calling HtmlHeaderCell::toHtml()
	 *
	 * @param string $content
	 * @param bool $isSortable
	 *
	 * @return \WikibaseQuality\ConstraintReport\Html\HtmlTableHeaderBuilder
	 */
	private function getHtmlTableHeaderMock( $content, $isSortable = false ) {
		$cellMock = $this
			->getMockBuilder( \WikibaseQuality\ConstraintReport\Html\HtmlTableHeaderBuilder::class )
			->setConstructorArgs( [ $content, $isSortable ] )
			->setMethods( [ 'toHtml' ] )
			->getMock();
		$cellMock
			->expects( $this->any() )
			->method( 'toHtml' )
			->will( $this->returnValue( "<th>$content</th>" ) );

		return $cellMock;
	}

	/**
	 * Creates HtmlTableCell mock, which returns only the content when calling HtmlTableCell::toHtml()
	 *
	 * @param string $content
	 *
	 * @return \WikibaseQuality\ConstraintReport\Html\HtmlTableCellBuilder
	 */
	private function getHtmlTableCellMock( $content ) {
		$cellMock = $this
			->getMockBuilder( HtmlTableCellBuilder::class )
			->setConstructorArgs( [ $content ] )
			->getMock();
		$cellMock
			->expects( $this->any() )
			->method( 'toHtml' )
			->will( $this->returnValue( "<td>$content</td>" ) );

		return $cellMock;
	}

}
