<?php

namespace WikibaseQuality\ConstraintReport\Violations;


use Html;
use Doctrine\Instantiator\Exception\InvalidArgumentException;
use WikibaseQuality\Violations\Violation;
use WikibaseQuality\Violations\ViolationFormatter;

class ConstraintViolationFormatter implements ViolationFormatter {

	/**
     * @see ViolationFormatter::isFormatterFor
     *
     * @param Violation $violation
     * @return bool
     */
    public function isFormatterFor( Violation $violation ) {
        $splitConstraintId = explode( Violation::CONSTRAINT_ID_DELIMITER, $violation->getConstraintId() );
        $prefix = $splitConstraintId[0];

        return $prefix === WBQ_CONSTRAINTS_ID;
    }

    /**
     * @param Violation $violation
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
        if( array_key_exists( 'parameters', $additionalInformation ) ) {
            $parameters = $additionalInformation['parameters'];
            foreach( $parameters as $name => $value ) {
                $output .= Html::element( 'br' );
                $output .= sprintf( '%s: %s', $name, $value );
            }
        }
        else {
            $output .= Html::element( 'br' );
            $output .= 'none';
        }

        $output .= Html::closeElement( 'p' );

        return $output;
    }

    /**
     * @param Violation $violation
     * @throws InvalidArgumentException
     * @return string HTML
     */
    public function getIconClass( Violation $violation ) {
        if ( !$this->isFormatterFor( $violation ) ) {
            throw new InvalidArgumentException( 'Given violation is not part of current context.' );
        }
        //TODO: Choose depending on type
        return '';
    }

    /**
     * @param Violation $violation
     * @return string HTML
     */
    public function getShortMessage( Violation $violation ) {
        //TODO: Implement message system depending on constraint type
        return wfMessage( 'wbqc-violation-message' )->escaped();
    }

    /**
     * @param Violation $violation
     * @param bool $permissionStatus
     * @return string HTML
     */
    public function getLongMessage( Violation $violation, $permissionStatus ) {
        //TODO: Implement message system depending on constraint type
        return wfMessage( 'wbqc-violation-message' )->escaped();
    }
}