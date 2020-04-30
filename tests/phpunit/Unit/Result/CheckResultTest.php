<?php

namespace WikibaseQuality\ConstraintReport\Tests\Unit\Result;

use DataValues\StringValue;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachingMetadata;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\Metadata;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessage;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikibaseQuality\ConstraintReport\Tests\Fake\AppendingContextCursor;
use WikibaseQuality\ConstraintReport\Tests\Fake\FakeSnakContext;

/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 *
 * @group WikibaseQualityConstraints
 * @author BP2014N1
 * @license GPL-2.0-or-later
 */
class CheckResultTest extends \MediaWikiUnitTestCase {

	public function testConstructAndGetters_Context() {
		$propertyId = new PropertyId( 'P1' );
		$entityId = new ItemId( 'Q1' );
		$snak = new PropertyValueSnak( $propertyId, new StringValue( 'Foo' ) );
		$constraintId = '1';
		$parameters = [ 'test' => 'parameters' ];
		$constraint = new Constraint( $constraintId, new PropertyId( 'P1' ), 'Q100', $parameters );
		$status = CheckResult::STATUS_COMPLIANCE;
		$message = new ViolationMessage( 'wbqc-violation-message-single-value' );
		$context = new FakeSnakContext( $snak, new Item( $entityId ) );
		$metadata = Metadata::ofCachingMetadata( CachingMetadata::ofMaximumAgeInSeconds( 42 ) );

		$checkResult = new CheckResult( $context, $constraint, $parameters, $status, $message );
		$checkResult->withMetadata( $metadata );

		$this->assertEquals( $context->getCursor(), $checkResult->getContextCursor() );
		$this->assertSame( $snak->getType(), $checkResult->getSnakType() );
		$this->assertSame( $snak->getDataValue(), $checkResult->getDataValue() );
		$this->assertSame( $constraint, $checkResult->getConstraint() );
		$this->assertSame( $constraintId, $checkResult->getConstraintId() );
		$this->assertSame( $parameters, $checkResult->getParameters() );
		$this->assertSame( $status, $checkResult->getStatus() );
		$this->assertSame( $message, $checkResult->getMessage() );
		$this->assertSame( $metadata, $checkResult->getMetadata() );
	}

	public function testConstructAndGetters_Cursor() {
		$propertyId = new PropertyId( 'P1' );
		$entityId = new ItemId( 'Q1' );
		$snak = new PropertyValueSnak( $propertyId, new StringValue( 'Foo' ) );
		$constraintId = '1';
		$parameters = [ 'test' => 'parameters' ];
		$constraint = new Constraint( $constraintId, new PropertyId( 'P1' ), 'Q100', $parameters );
		$status = CheckResult::STATUS_COMPLIANCE;
		$message = new ViolationMessage( 'wbqc-violation-message-single-value' );
		$context = new AppendingContextCursor();
		$metadata = Metadata::ofCachingMetadata( CachingMetadata::ofMaximumAgeInSeconds( 42 ) );

		$checkResult = new CheckResult( $context, $constraint, $parameters, $status, $message );
		$checkResult->withMetadata( $metadata );

		$this->assertSame( $context, $checkResult->getContextCursor() );
		$this->assertNull( $checkResult->getSnakType() );
		$this->assertNull( $checkResult->getDataValue() );
		$this->assertSame( $constraint, $checkResult->getConstraint() );
		$this->assertSame( $constraintId, $checkResult->getConstraintId() );
		$this->assertSame( $parameters, $checkResult->getParameters() );
		$this->assertSame( $status, $checkResult->getStatus() );
		$this->assertSame( $message, $checkResult->getMessage() );
		$this->assertSame( $metadata, $checkResult->getMetadata() );
	}

	public function testAddParameter() {
		$context = new FakeSnakContext( new PropertyNoValueSnak( new PropertyId( 'P1' ) ) );
		$constraint = new Constraint( '', new PropertyId( 'P1' ), 'Q1', [] );
		$checkResult = new CheckResult( $context, $constraint );

		$this->assertSame( [], $checkResult->getParameters() );

		$checkResult->addParameter( 'constraint_status', 'mandatory' );
		$this->assertSame( [ 'constraint_status' => [ 'mandatory' ] ], $checkResult->getParameters() );
	}

	public function testSetStatus() {
		$context = new FakeSnakContext( new PropertyNoValueSnak( new PropertyId( 'P1' ) ) );
		$constraint = new Constraint( '', new PropertyId( 'P1' ), 'Q1', [] );
		$checkResult = new CheckResult( $context, $constraint, [], CheckResult::STATUS_VIOLATION );

		$this->assertSame( CheckResult::STATUS_VIOLATION, $checkResult->getStatus() );

		$checkResult->setStatus( CheckResult::STATUS_WARNING );
		$this->assertSame( CheckResult::STATUS_WARNING, $checkResult->getStatus() );
	}

}
