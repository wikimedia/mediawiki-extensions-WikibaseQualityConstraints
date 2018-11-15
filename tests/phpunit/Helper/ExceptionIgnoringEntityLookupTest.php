<?php


namespace WikibaseQuality\ConstraintReport\Tests\Helper;

use PHPUnit\Framework\TestCase;
use PHPUnit4And6Compat;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\DataModel\Services\Lookup\UnresolvedEntityRedirectException;
use Wikibase\Repo\Tests\NewItem;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ExceptionIgnoringEntityLookup;

/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ExceptionIgnoringEntityLookup
 *
 * @group WikibaseQualityConstraints
 *
 * @license GPL-2.0-or-later
 */
class ExceptionIgnoringEntityLookupTest extends TestCase {

	use PHPUnit4And6Compat;

	public function testGetEntity_returnsEntity() {
		$entity = NewItem::withId( 'Q1' )->build();
		$entityId = $entity->getId();
		$innerLookup = $this->createMock( EntityLookup::class );
		$innerLookup->expects( $this->once() )
			->method( 'getEntity' )
			->with( $entityId )
			->willReturn( $entity );
		$outerLookup = new ExceptionIgnoringEntityLookup( $innerLookup );

		$actual = $outerLookup->getEntity( $entityId );

		$this->assertSame( $entity, $actual );
	}

	public function testGetEntity_returnsNull() {
		$entityId = new ItemId( 'Q999999999' );
		$innerLookup = $this->createMock( EntityLookup::class );
		$innerLookup->expects( $this->once() )
			->method( 'getEntity' )
			->with( $entityId )
			->willReturn( null );
		$outerLookup = new ExceptionIgnoringEntityLookup( $innerLookup );

		$actual = $outerLookup->getEntity( $entityId );

		$this->assertNull( $actual );
	}

	public function testGetEntity_catchesUnresolvedEntityRedirectException() {
		$entityId = new ItemId( 'Q2' );
		$innerLookup = $this->createMock( EntityLookup::class );
		$innerLookup->expects( $this->once() )
			->method( 'getEntity' )
			->with( $entityId )
			->willThrowException( new UnresolvedEntityRedirectException(
				$entityId,
				new ItemId( 'Q1' )
			) );
		$outerLookup = new ExceptionIgnoringEntityLookup( $innerLookup );

		$actual = $outerLookup->getEntity( $entityId );

		$this->assertNull( $actual );
	}

	/**
	 * @dataProvider provideBooleans
	 */
	public function testHasEntity( $expected ) {
		$entityId = new ItemId( 'Q1' );
		$innerLookup = $this->createMock( EntityLookup::class );
		$innerLookup->expects( $this->once() )
			->method( 'hasEntity' )
			->with( $entityId )
			->willReturn( $expected );
		$outerLookup = new ExceptionIgnoringEntityLookup( $innerLookup );

		$actual = $outerLookup->hasEntity( $entityId );

		$this->assertSame( $expected, $actual );
	}

	public function provideBooleans() {
		return [
			[ true ],
			[ false ],
		];
	}

}
