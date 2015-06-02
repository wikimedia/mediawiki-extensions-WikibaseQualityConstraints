<?php

namespace WikibaseQuality\ConstraintReport\Violations;


use Html;
use Doctrine\Instantiator\Exception\InvalidArgumentException;
use WikibaseQuality\ConstraintReport\ConstraintReportFactory;
use WikibaseQuality\Violations\Violation;
use WikibaseQuality\Violations\ViolationContext;

class ConstraintViolationContext implements ViolationContext {

    const CONTEXT_ID = 'wbqc';

    /**
     * @var array
     */
    private $types;

    /**
     * @param array $types
     */
    public function __construct( array $types ) {
        $this->types = $types;
    }

    /**
     * @see ViolationContext::getId
     * @codeCoverageIgnore
     *
     * @return string
     */
    public function getId() {
        return self::CONTEXT_ID;
    }

    /**
     * @see ViolationContext::getName
     * @codeCoverageIgnore
     *
     * @return string
     */
    public function getName() {
        return 'wbqc-violations-group';
    }

    /**
     * @see ViolationContext::getTypes
     *
     * @return array
     */
    public function getTypes() {
        return $this->types;
    }

    /**
     * @see ViolationContext::isContextFor
     *
     * @param Violation $violation
     * @return bool
     */
    public function isContextFor( Violation $violation ) {
        $splitConstraintId = explode( Violation::CONSTRAINT_ID_DELIMITER, $violation->getConstraintId() );
        $prefix = $splitConstraintId[0];

        return $prefix === $this->getId();
    }

    /**
     * @param Violation $violation
     * @return string
     */
    public function formatAdditionalInformation( Violation $violation ) {
        if ( !$this->isContextFor( $violation ) ) {
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
     * @return string
     */
    public function getIconPath( Violation $violation ) {
        if ( !$this->isContextFor( $violation ) ) {
            throw new InvalidArgumentException( 'Given violation is not part of current context.' );
        }
        //TODO: Choose depending on type
        return '/wikidata/extensions/Quality/images/severe_arrows.png';
    }

    /**
     * @param Violation $violation
     * @return string
     */
    public function getShortMessage( Violation $violation ) {
        //TODO: Implement message system depending on constraint type
        return wfMessage( 'wbqc-violation-message' )->text();
    }

    /**
     * @param Violation $violation
     * @param bool $permissionStatus
     * @return string
     */
    public function getLongMessage( Violation $violation, $permissionStatus ) {
        //TODO: Implement message system depending on constraint type
        return wfMessage( 'wbqc-violation-message' )->text();
    }
}