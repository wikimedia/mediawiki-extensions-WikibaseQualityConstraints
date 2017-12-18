<?php

namespace WikibaseQuality\ConstraintReport\Test\CheckResult;

use LogicException;
use PHPUnit_Framework_TestCase;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use DataValues\StringValue;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachingMetadata;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\Metadata;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikibaseQuality\ConstraintReport\Tests\Fake\FakeSnakContext;

/**
 * @covers \WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 *
 * @group WikibaseQualityConstraints
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class CheckResultTest extends PHPUnit_Framework_TestCase {

	public function testConstructAndGetters() {
		$propertyId = new PropertyId( 'P1' );
		$entityId = new ItemId( 'Q1' );
		$snak = new PropertyValueSnak( $propertyId, new StringValue( 'Foo' ) );
		$constraintId = '1';
		$parameters = [ 'test' => 'parameters' ];
		$constraint = new Constraint( $constraintId, new PropertyId( 'P1' ), 'Q100', $parameters );
		$status = CheckResult::STATUS_COMPLIANCE;
		$message = 'All right';
		$context = new FakeSnakContext( $snak, new Item( $entityId ) );
		$metadata = Metadata::ofCachingMetadata( CachingMetadata::ofMaximumAgeInSeconds( 42 ) );

		$checkResult = new CheckResult( $context, $constraint, $parameters, $status, $message );
		$checkResult->withMetadata( $metadata );

		$this->assertSame( $context, $checkResult->getContext() );
		$this->assertSame( $entityId, $checkResult->getEntityId() );
		$this->assertSame( $snak->getType(), $checkResult->getSnakType() );
		$this->assertSame( $snak->getDataValue(), $checkResult->getDataValue() );
		$this->assertSame( $constraint, $checkResult->getConstraint() );
		$this->assertSame( $constraintId, $checkResult->getConstraintId() );
		$this->assertSame( $parameters, $checkResult->getParameters() );
		$this->assertSame( $status, $checkResult->getStatus() );
		$this->assertSame( $message, $checkResult->getMessage() );
		$this->assertSame( $metadata, $checkResult->getMetadata() );
	}

	public function testWithWrongSnakType() {
		$propertyId = new PropertyId( 'P1' );
		$snak = new PropertyNoValueSnak( $propertyId );
		$context = new FakeSnakContext( $snak );
		$checkResult = new CheckResult(
			$context,
			new Constraint( '1', $propertyId, 'Q100', [] )
		);

		$this->setExpectedException( LogicException::class );
		$checkResult->getDataValue();
	}

}
