<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Checker;

use InvalidArgumentException;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Services\EntityId\EntityIdFormatter;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\StatementListProvider;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use Wikibase\DataModel\Statement\Statement;

/**
 * @package WikibaseQuality\ConstraintReport\ConstraintCheck\Checker
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class OneOfChecker implements ConstraintChecker {

	/**
	 * @var ConstraintParameterParser
	 */
	private $helper;

	/**
	 * @var EntityIdFormatter
	 */
	private $entityIdFormatter;

	/**
	 * @param ConstraintParameterParser $helper
	 * @param EntityIdFormatter $entityIdFormatter should return HTML
	 */
	public function __construct(
		ConstraintParameterParser $helper,
		EntityIdFormatter $entityIdFormatter
	) {
		$this->helper = $helper;
		$this->entityIdFormatter = $entityIdFormatter;
	}

	/**
	 * @var string $item
	 * @return string HTML
	 */
	private function formatValue( $item ) {
		try {
			return $this->entityIdFormatter->formatEntityId( new ItemId( $item ) );
		} catch ( InvalidArgumentException $e ) {
			return htmlspecialchars( $item );
		}
	}

	/**
	 * Checks 'One of' constraint.
	 *
	 * @param Statement $statement
	 * @param Constraint $constraint
	 * @param EntityDocument|StatementListProvider $entity
	 *
	 * @return CheckResult
	 */
	public function checkConstraint( Statement $statement, Constraint $constraint, EntityDocument $entity ) {
		$parameters = [];
		$constraintParameters = $constraint->getConstraintParameters();

		$items = false;
		if ( array_key_exists( 'item', $constraintParameters ) ) {
			$items = explode( ',', $constraintParameters['item'] );
			$items = array_map( 'strtoupper', $items ); // FIXME strtoupper should not be necessary, remove once constraints are imported from statements
			$parameters['item'] = $this->helper->parseParameterArray( $items );
		}

		if ( array_key_exists( 'constraint_status', $constraintParameters ) ) {
			$parameters['constraint_status'] = $this->helper->parseSingleParameter( $constraintParameters['constraint_status'], true );
		}

		$mainSnak = $statement->getMainSnak();

		/*
		 * error handling:
		 *   $mainSnak must be PropertyValueSnak, neither PropertySomeValueSnak nor PropertyNoValueSnak is allowed
		 */
		if ( !$mainSnak instanceof PropertyValueSnak ) {
			$message = wfMessage( "wbqc-violation-message-value-needed" )->params( $constraint->getConstraintTypeName() )->escaped();
			return new CheckResult( $entity->getId(), $statement, 'One of', $constraint->getConstraintId(), $parameters, CheckResult::STATUS_VIOLATION, $message );
		}

		$dataValue = $mainSnak->getDataValue();

		/*
		 * error handling:
		 *   type of $dataValue for properties with 'One of' constraint has to be 'wikibase-entityid'
		 *   parameter $itemArray must not be null
		 */
		if ( $dataValue->getType() !== 'wikibase-entityid' ) {
			$message = wfMessage( "wbqc-violation-message-value-needed-of-type" )->params( $constraint->getConstraintTypeName(), 'wikibase-entityid' )->escaped();
			return new CheckResult( $entity->getId(), $statement, $constraint->getConstraintTypeQid(), $constraint->getConstraintId(), $parameters, CheckResult::STATUS_VIOLATION, $message );
		}
		/** @var EntityIdValue $dataValue */

		if ( !$items ) {
			$message = wfMessage( "wbqc-violation-message-parameter-needed" )->params( $constraint->getConstraintTypeName(), 'item' )->escaped();
			return new CheckResult( $entity->getId(), $statement, $constraint->getConstraintTypeQid(), $constraint->getConstraintId(), $parameters, CheckResult::STATUS_VIOLATION, $message );
		}

		if ( in_array( $dataValue->getEntityId()->getSerialization(), $items ) ) {
			$message = '';
			$status = CheckResult::STATUS_COMPLIANCE;
		} else {
			$message = wfMessage( "wbqc-violation-message-one-of" );
			$message->rawParams(
				$this->entityIdFormatter->formatEntityId( $statement->getPropertyId() )
			);
			$message->numParams( count( $items ) );
			$message->rawParams(
				'<ul>'
				. implode( array_map(
					function ( $item ) {
						return '<li>' . $this->formatValue( $item ) . '</li>';
					},
					$items
				) )
				. '</ul>'
			);
			$message->rawParams( array_map( [ $this, 'formatValue' ], $items ) );
			$message = $message->escaped();
			$status = CheckResult::STATUS_VIOLATION;
		}

		return new CheckResult( $entity->getId(), $statement, $constraint->getConstraintTypeQid(), $constraint->getConstraintId(), $parameters, $status, $message );
	}

}
