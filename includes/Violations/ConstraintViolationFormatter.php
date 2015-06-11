<?php

namespace WikibaseQuality\ConstraintReport\Violations;

use UnexpectedValueException;
use Html;
use Doctrine\Instantiator\Exception\InvalidArgumentException;
use WikibaseQuality\Violations\Violation;
use WikibaseQuality\Violations\ViolationFormatter;


class ConstraintViolationFormatter implements ViolationFormatter {

	const QUALIFIER_ICON_CLASS = 'wbq-icon-angle-arrow';
	// Icon class for value type and target required claim
	const ARROW_ICON_CLASS = 'wbq-icon-arrow';
	const INVERSE_ICON_CLASS = 'wbq-icon-arrows';
	// Icon type for rang and one of
	const BRACKETS_ICON_CLASS = 'wbq-icon-brackets';
	const COMMONS_ICON_CLASS = 'wbq-icon-commons';
	const CONFLICTS_WITH_ICON_CLASS = 'wbq-icon-x';
	const FORMAT_ICON_CLASS = 'wbq-icon-regex';
	const MULTI_VALUE_ICON_CLASS = 'wbq-icon-greater-one';
	const SINGLE_VALUE_ICON_CLASS = 'wbq-icon-one';
	const SYMMETRIC_ICON_CLASS = 'wbq-icon-double-arrow';
	// Icon type for type and item
	const BUBBLES_ICON_CLASS = 'wbq-icon-bubbles';
	const UNIQUE_VALUE_ICON_CLASS = 'wbq-icon-not-equal';

	/**
	 * @see ViolationFormatter::isFormatterFor
	 *
	 * @param Violation $violation
	 *
	 * @return bool
	 */
	public function isFormatterFor( Violation $violation ) {
		$splitConstraintId = explode( Violation::CONSTRAINT_ID_DELIMITER, $violation->getConstraintId() );
		$prefix = $splitConstraintId[0];

		return $prefix === WBQ_CONSTRAINTS_ID;
	}

	/**
	 * @param Violation $violation
	 *
	 * @return string HTML
	 */
	public function formatAdditionalInformation( Violation $violation ) {
		if ( !$this->isFormatterFor( $violation ) ) {
			throw new InvalidArgumentException( 'Given violation is not part of current context.' );
		}

		$output =
			Html::openElement( 'p' )
			. Html::element(
				'span',
				array(
					'class' => 'wbq-violations-additional-information-header'
				),
				wfMessage( 'wbqc-violation-header-parameters' )->text()
			);

		$additionalInformation = $violation->getAdditionalInfo();
		if ( array_key_exists( 'parameters', $additionalInformation ) ) {
			$parameters = $additionalInformation['parameters'];
			foreach ( $parameters as $name => $value ) {
				$output .= Html::element( 'br' );
				$output .= sprintf( '%s: %s', $name, $value );
			}
		} else {
			$output .= Html::element( 'br' );
			$output .= 'none';
		}

		$output .= Html::closeElement( 'p' );

		return $output;
	}

	/**
	 * @param Violation $violation
	 *
	 * @throws InvalidArgumentException
	 * @return string HTML
	 */
	public function getIconClass( Violation $violation ) {
		if ( !$this->isFormatterFor( $violation ) ) {
			throw new InvalidArgumentException( 'Given violation is not part of current context.' );
		}

		//TODO: ConstraintTypeEntityId as EntityId when implemented (currently string)

		$constraintType = $violation->getConstraintTypeEntityId();
		switch ( $constraintType ) {
			case 'Commons Link':
				$iconClass = ConstraintViolationFormatter::COMMONS_ICON_CLASS;
				break;
			case 'Conflicts with':
				$iconClass = ConstraintViolationFormatter::CONFLICTS_WITH_ICON_CLASS;
				break;
			case 'Diff within range':
			case 'Range':
			case 'One of':
				$iconClass = ConstraintViolationFormatter::BRACKETS_ICON_CLASS;
				break;
			case 'Format':
				$iconClass = ConstraintViolationFormatter::FORMAT_ICON_CLASS;
				break;
			case 'Inverse':
				$iconClass = ConstraintViolationFormatter::INVERSE_ICON_CLASS;
				break;
			case 'Item':
			case 'Type':
				$iconClass = ConstraintViolationFormatter::BUBBLES_ICON_CLASS;
				break;
			case 'Mandatory qualifiers':
			case 'Qualifier':
			case 'Qualifiers':
				$iconClass = ConstraintViolationFormatter::QUALIFIER_ICON_CLASS;
				break;
			case 'Multi value':
				$iconClass = ConstraintViolationFormatter::MULTI_VALUE_ICON_CLASS;
				break;
			case 'Single value':
				$iconClass = ConstraintViolationFormatter::SINGLE_VALUE_ICON_CLASS;
				break;
			case 'Symmetric':
				$iconClass = ConstraintViolationFormatter::SYMMETRIC_ICON_CLASS;
				break;
			case 'Target required claim':
			case 'Value type':
				$iconClass = ConstraintViolationFormatter::ARROW_ICON_CLASS;
				break;
			case 'Unique value':
				$iconClass = ConstraintViolationFormatter::UNIQUE_VALUE_ICON_CLASS;
				break;
			default:
				throw new UnexpectedValueException('There is no icon class for this constraint.');
		}

		$constraintParams = $violation->getAdditionalInfo();
		if ( array_key_exists( 'constraint_status', $constraintParams ) && $constraintParams['constraint_status'] === 'mandatory' ) {
			$iconClass .= '-severe';
		}

		return $iconClass;
	}

	/**
	 * @param Violation $violation
	 *
	 * @return string HTML
	 */
	public function getShortMessage( Violation $violation ) {
		//TODO: Implement message system depending on constraint type
		return wfMessage( 'wbqc-violation-message' )->escaped();
	}

	/**
	 * @param Violation $violation
	 * @param bool $permissionStatus
	 *
	 * @return string HTML
	 */
	public function getLongMessage( Violation $violation, $permissionStatus ) {
		//TODO: Implement message system depending on constraint type
		return wfMessage( 'wbqc-violation-message' )->escaped();
	}
}