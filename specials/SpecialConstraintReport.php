<?php

namespace WikibaseQuality\ConstraintReport\Specials;

use SpecialPage;
use ValueFormatters\FormatterOptions;
use ValueFormatters\ValueFormatter;
use Wikibase\Lib\EntityIdHtmlLinkFormatter;
use Wikibase\Lib\EntityIdLabelFormatter;
use Wikibase\Lib\EntityIdLinkFormatter;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\Lib\LanguageNameLookup;
use Wikibase\Lib\SnakFormatter;
use Wikibase\Lib\Store\EntityLookup;
use Wikibase\Lib\Store\LanguageLabelDescriptionLookup;
use Wikibase\Repo\WikibaseRepo;
use DataValues;
use DataValues\DataValue;
use Html;
use Doctrine\Instantiator\Exception\InvalidArgumentException;
use Doctrine\Instantiator\Exception\UnexpectedValueException;
use Wikibase\DataModel\Entity\Entity;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\EntityIdParsingException;
use Traversable;
use Countable;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\Lib\Store\EntityTitleLookup;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikibaseQuality\ConstraintReport\ConstraintReportFactory;
use WikibaseQuality\ConstraintReport\Violations\CheckResultToViolationTranslator;
use WikibaseQuality\Html\HtmlTable;
use WikibaseQuality\Html\HtmlTableHeader;
use WikibaseQuality\Violations\ViolationRepo;
use WikibaseQuality\WikibaseQualityFactory;


/**
 * Class SpecialConstraintReport
 * Special page that displays all constraints that are defined on an Entity with additional information
 * (whether it complied or was a violation, which parameters the constraint has etc.).
 *
 * @package WikibaseQuality\ConstraintReport\Specials
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class SpecialConstraintReport extends SpecialPage {

	/**
	 * Maximum number of displayed values for parameters with multiple ones.
	 *
	 * @var int
	 */
	const MAX_PARAMETER_ARRAY_LENGTH = 5;

	/**
	 * Id of the property, that is used to specify constraints on entities.
	 *
	 * @var int
	 */
	// TODO set, when properties are created
	const CONSTRAINT_PROPERTY_ID = 'P1';

	/**
	 * @var EntityTitleLookup
	 */
	private $entityTitleLookup;

	/**
	 * @var EntityIdParser
	 */
	private $entityIdParser;

	/**
	 * @var EntityLookup
	 */
	private $entityLookup;

	/**
	 * @var ValueFormatter
	 */
	private $dataValueFormatter;

	/**
	 * @var EntityIdLabelFormatter
	 */
	private $entityIdLabelFormatter;

	/**
	 * @var EntityIdHtmlLinkFormatter
	 */
	private $entityIdHtmlLinkFormatter;

    /**
     * @var CheckResultToViolationTranslator
     */
    private $checkResultToViolationTranslator;

    /**
     * @var ViolationRepo
     */
    private $violationRepo;

	/**
	 * @param string $name
	 * @param string $restriction
	 * @param bool $listed
	 * @param bool $function
	 * @param string $file
	 * @param bool $includable
	 */
	public function __construct( $name = 'ConstraintReport', $restriction = '', $listed = true, $function = false, $file = '', $includable = false ) {
		parent::__construct( $name, $restriction, $listed, $function, $file, $includable );

        $repo = WikibaseRepo::getDefaultInstance();

        $this->entityLookup = $repo->getEntityLookup();
        $this->entityIdParser = $repo->getEntityIdParser();
        $this->entityTitleLookup = WikibaseRepo::getDefaultInstance()->getEntityTitleLookup();

        $formatterOptions = new FormatterOptions();
        $formatterOptions->setOption( SnakFormatter::OPT_LANG, $this->getLanguage()->getCode() );
        $this->dataValueFormatter = $repo->getValueFormatterFactory()->getValueFormatter( SnakFormatter::FORMAT_HTML, $formatterOptions );

        $entityTitleLookup = $repo->getEntityTitleLookup();
        $labelLookup = new LanguageLabelDescriptionLookup( $repo->getTermLookup(), $this->getLanguage()->getCode() );
        $this->entityIdLabelFormatter = new EntityIdLabelFormatter( $labelLookup );
        $this->entityIdHtmlLinkFormatter = new EntityIdHtmlLinkFormatter(
            $labelLookup,
            $entityTitleLookup,
            new LanguageNameLookup()
        );

        $this->checkResultToViolationTranslator = ConstraintReportFactory::getDefaultInstance()->getCheckResultToViolationTranslator();
        $this->violationRepo = WikibaseQualityFactory::getDefaultInstance()->getViolationRepo();
	}

    /**
     * @see SpecialPage::getGroupName
     *
     * @return string
     */
    function getGroupName() {
        return 'wikibasequality';
    }

    /**
     * @see SpecialPage::getDescription
     *
     * @return string
     */
    public function getDescription() {
        return $this->msg( 'wbqc-constraintreport' )->text();
    }

	/**
	 * @see SpecialPage::execute
	 *
	 * @param string|null $subPage
     *
     * @throws InvalidArgumentException
     * @throws EntityIdParsingException
     * @throws UnexpectedValueException
	 */
	public function execute( $subPage ) {
		$out = $this->getOutput();

		$postRequest = $this->getContext()->getRequest()->getVal( 'entityId' );
		if ( $postRequest ) {
			$out->redirect( $this->getPageTitle( strtoupper( $postRequest ) )->getLocalURL() );
			return;
		}

		$out->addModules( 'SpecialConstraintReportPage' );

		$this->setHeaders();

		$out->addHTML(
			$this->getExplanationText()
			. $this->buildEntityIdForm()
		);

		if ( !$subPage ) {
			return;
		}

		if ( !is_string( $subPage ) ) {
			throw new InvalidArgumentException( '$subPage must be string.' );
		}

		try {
			$entityId = $this->entityIdParser->parse( $subPage );
			$entity = $this->entityLookup->getEntity( $entityId );
		} catch ( EntityIdParsingException $e ) {
			$out->addHTML(
				$this->buildNotice( $this->msg( 'wbqc-constraintreport-invalid-entity-id' )->text(), true )
			);
			return;
		}

		if ( !$entity ) {
			$out->addHTML(
				$this->buildNotice( $this->msg( 'wbqc-constraintreport-not-existent-entity' )->text(), true )
			);
			return;
		}

		$results = $this->executeCheck( $entity );
        $this->saveResultsInViolationsTable( $entity, $results );

		if ( $results && count( $results ) > 0 ) {
			$out->addHTML(
				$this->buildResultHeader( $entityId )
				. $this->buildSummary( $results )
				. $this->buildResultTable( $entityId, $results )
			);
		} else {
			$out->addHTML(
				$this->buildResultHeader( $entityId )
				. $this->buildNotice( $this->msg( 'wbqc-constraintreport-empty-result' )->text() )
			);
		}
	}

	/**
	 * Returns html text of the entity id form
	 *
	 * @return string
	 */
	private function buildEntityIdForm() {
		return
			Html::openElement(
				'form',
				array (
					'class' => 'wbqc-constraintreport-form',
					'action' => $this->getPageTitle()->getLocalURL(),
					'method' => 'post'
				)
			)
			. Html::input(
				'entityId',
				'',
				'text',
				array (
					'class' => 'wbqc-constraintreport-form-entity-id',
					'placeholder' => $this->msg( 'wbqc-constraintreport-form-entityid-placeholder' )->text()
				)
			)
			. Html::input(
				'submit',
				$this->msg( 'wbqc-constraintreport-form-submit-label' )->text(),
				'submit',
				array (
					'class' => 'wbqc-constraintreport-form-submit'
				)
			)
			. Html::closeElement( 'form' );
	}

	/**
	 * Builds notice with given message. Optionally notice can be handles as error by settings $error to true
	 *
	 * @param string $message
	 * @param bool $error
     *
     * @throws InvalidArgumentException
	 *
	 * @return string
	 */
	private function buildNotice( $message, $error = false ) {
		if ( !is_string( $message ) ) {
			throw new InvalidArgumentException( '$message must be string.' );
		}
		if ( !is_bool( $error ) ) {
			throw new InvalidArgumentException( '$error must be bool.' );
		}

		$cssClasses = 'wbqc-constraintreport-notice';
		if ( $error ) {
			$cssClasses .= ' wbqc-constraintreport-notice-error';
		}

		return
			Html::element(
				'p',
				array (
					'class' => $cssClasses
				),
				$message );
	}

	private function getExplanationText() {
		return
			Html::openElement( 'div', array( 'class' => 'wbqc-explanation') )
			. $this->msg( 'wbqc-constraintreport-explanation-part-one' )
			. Html::closeElement( 'div' )
			. Html::element( 'br' )
			. Html::openElement( 'div', array( 'class' => 'wbqc-explanation') )
			. $this->msg( 'wbqc-constraintreport-explanation-part-two' )
			. Html::closeElement( 'div' );
	}

	/**
	 * @see SpecialCheckResultPage::executeCheck
	 *
	 * @param Entity $entity
	 *
	 * @return string
	 */
	private function executeCheck( Entity $entity ) {

		$constraintChecker = ConstraintReportFactory::getDefaultInstance()->getConstraintChecker();
		$results = $constraintChecker->checkAgainstConstraints( $entity );

		return $results;
	}

	/**
	 * @see SpecialCheckResultPage::buildResultTable
	 *
	 * @param EntityId $entityId
	 * @param CheckResult[] $results
	 *
	 * @return string
	 */
	private function buildResultTable( EntityId $entityId, $results ) {
		// Set table headers
		$table = new HtmlTable(
			array (
				new HtmlTableHeader(
					$this->msg( 'wbqc-constraintreport-result-table-header-status' )->text(),
					true
				),
				new HtmlTableHeader(
					$this->msg( 'wbqc-constraintreport-result-table-header-claim' )->text(),
					true
				),
				new HtmlTableHeader(
					$this->msg( 'wbqc-constraintreport-result-table-header-constraint' )->text(),
					true
				)
			)
		);

		foreach ( $results as $result ) {
			// Status column
			$statusColumn = $this->buildTooltipElement(
				$this->formatStatus( $result->getStatus() ),
				$result->getMessage(),
				'[?]'
			);

			// Claim column
			$property = $this->entityIdLabelFormatter->formatEntityId( $result->getPropertyId() );
			if ( $result->getMainSnakType() === 'value' ) {
				$value = $this->formatValue( $result->getDataValue() );
			} else {
				$value = $result->getMainSnakType();
			}

			$claimColumn = $this->getClaimLink(
				$entityId,
				$result->getPropertyId(),
				$property . ': ' . $value
			);

			// Constraint column
			$constraintLink = $this->getClaimLink(
				$result->getPropertyId(),
				new PropertyId( self::CONSTRAINT_PROPERTY_ID ),
				$result->getConstraintName()
			);
			$constraintColumn = $this->buildExpandableElement(
				$constraintLink,
				$this->formatParameters( $result->getParameters() ),
				'[...]'
			);

			// Append cells
			$table->appendRow(
				array (
					$statusColumn,
					$claimColumn,
					$constraintColumn
				)
			);
		}

		return $table->toHtml();
	}

	/**
	 * Returns html text of the result header
	 *
	 * @param EntityId $entityId
	 *
	 * @return string
	 */
	private function buildResultHeader( EntityId $entityId ) {
		$entityLink = sprintf( '%s (%s)',
							   $this->entityIdHtmlLinkFormatter->formatEntityId( $entityId ),
							   $entityId->getSerialization() );

		return
			Html::openElement( 'h3' )
			. $this->msg( 'wbqc-constraintreport-result-headline', $entityLink )->text()
			. Html::closeElement( 'h3' );
	}

	/**
	 * Builds summary from given results
	 *
	 * @param array|Traversable $results
	 *
	 * @return string
	 */
	private function buildSummary( $results ) {
		$statuses = array ();
		foreach ( $results as $result ) {
			$status = strtolower( $result->getStatus() );
			if ( array_key_exists( $status, $statuses ) ) {
				$statuses[ $status ]++;
			} else {
				$statuses[ $status ] = 1;
			}
		}

		$statusElements = array ();
		foreach ( $statuses as $status => $count ) {
			if ( $count > 0 ) {
				$statusElements[ ] =
					$this->formatStatus( $status )
					. ': '
					. $count;
			}
		}
		$summary =
			Html::openElement( 'p' )
			. implode( ', ', $statusElements )
			. Html::closeElement( 'p' );

		return $summary;
	}

	/**
	 * Builds a html div element with given content and a tooltip with given tooltip content
	 * If $tooltipContent is null, no tooltip will be created
	 *
	 * @param string $content
	 * @param string $tooltipContent
	 * @param $indicator
     *
     * @throws InvalidArgumentException
	 *
	 * @return string
	 */
	private function buildTooltipElement( $content, $tooltipContent, $indicator ) {
		if ( !is_string( $content ) ) {
			throw new InvalidArgumentException( '$content has to be string.' );
		}
		if ( $tooltipContent && ( !is_string( $tooltipContent ) ) ) {
			throw new InvalidArgumentException( '$tooltipContent, if provided, has to be string.' );
		}

		if ( empty( $tooltipContent ) ) {
			return $content;
		}

		$tooltipIndicator = Html::element(
			'span',
			array (
				'class' => 'wbqc-indicator'
			),
			$indicator
		);

		return
			Html::openElement(
				'span',
				array (
					'tooltip' => $tooltipContent
				)
			)
			. sprintf( '%s %s', $content, $tooltipIndicator )
			. Html::closeElement( 'span' );
	}

	/**
	 * Builds a html div element with given content and a tooltip with given tooltip content
	 * If $tooltipContent is null, no tooltip will be created
	 *
	 * @param string $content
	 * @param string $expandableContent
	 * @param string $indicator
     *
     * @throes InvalidArgumentException
	 *
	 * @return string
	 */
	private function buildExpandableElement( $content, $expandableContent, $indicator ) {
		if ( !is_string( $content ) ) {
			throw new InvalidArgumentException( '$content has to be string.' );
		}
		if ( $expandableContent && ( !is_string( $expandableContent ) ) ) {
			throw new InvalidArgumentException( '$tooltipContent, if provided, has to be string.' );
		}

		if ( empty( $expandableContent ) ) {
			return $content;
		}

		$tooltipIndicator = Html::element(
			'span',
			array (
				'class' => 'wbqc-expandable-content-indicator wbqc-indicator'
			),
			$indicator
		);

		$expandableContent = Html::element(
			'div',
			array(
				'class' => 'wbqc-expandable-content'
			),
			$expandableContent
		);

		return
			sprintf( '%s %s %s', $content, $tooltipIndicator, $expandableContent );
	}

	/**
	 * Formats given status to html
	 *
	 * @param string $status
     *
     * @throws InvalidArgumentException
	 *
	 * @return string
	 */
    private function formatStatus( $status ) {
        $messageName = "wbqc-constraintreport-status-" . strtolower( $status );
        $message = $this->msg( $messageName )->text();

        $formattedStatus =
            Html::element(
                'span',
                array (
                    'class' => 'wbqc-status wbqc-status-' . $status
                ),
                $message
            );

        return $formattedStatus;
    }

	/**
	 * Parses data values to human-readable string
	 *
	 * @param DataValue|array $dataValues
	 * @param string $separator
     *
     * @throws InvalidArgumentException
	 *
	 * @return string
	 */
	private function formatDataValues( $dataValues, $separator = ', ' ) {
		if ( $dataValues instanceof DataValue ) {
			$dataValues = array ( $dataValues );
		} elseif ( !is_array( $dataValues ) ) {
			throw new InvalidArgumentException( '$dataValues has to be instance of DataValue or an array of DataValues.' );
		}

		$formattedDataValues = array ();
		foreach ( $dataValues as $dataValue ) {
			if ( !( $dataValue instanceof DataValue ) ) {
				throw new InvalidArgumentException( '$dataValues has to be instance of DataValue or an array of DataValues.' );
			}
			if ( $dataValue instanceof EntityIdValue ) {
				$formattedDataValues[ ] = $this->entityIdLabelFormatter->formatEntityId( $dataValue->getEntityId() );
			} else {
				$formattedDataValues[ ] = $this->dataValueFormatter->format( $dataValue );
			}
		}

		return implode( $separator, $formattedDataValues );
	}

	/**
	 * Returns html link to given entity with anchor to specified property.
	 *
	 * @param EntityId $entityId
	 * @param PropertyId $propertyId
	 * @param string $text
	 *
	 * @return string
	 */
	private function getClaimLink( EntityId $entityId, PropertyId $propertyId, $text ) {
		return
			Html::element(
				'a',
				array (
					'href' => $this->getClaimUrl( $entityId, $propertyId ),
					'target' => '_blank'
				),
				$text
			);
	}

	/**
	 * Returns url of given entity with anchor to specified property.
	 *
	 * @param EntityId $entityId
	 * @param PropertyId $propertyId
	 *
	 * @return string
	 */
	private function getClaimUrl( EntityId $entityId, PropertyId $propertyId ) {
		$title = $this->entityTitleLookup->getTitleForId( $entityId );
		$entityUrl = sprintf( '%s#%s', $title->getLocalURL(), $propertyId->getSerialization() );

		return $entityUrl;
	}

	/**
	 * Formats values of constraints.
	 *
	 * @param string|ItemId|PropertyId|DataValue $value
	 *
	 * @return string
	 */
	private function formatValue( $value ) {
		if ( is_string( $value ) ) {
			// Cases like 'Format' 'pattern' or 'minimum'/'maximum' values, which we have stored as strings
			return ( $value );
		} elseif ( $value instanceof EntityId ) {
			// Cases like 'Conflicts with' 'property', to which we can link
			return $this->entityIdLabelFormatter->formatEntityId( $value );
		} else {
			// Cases where we format a DataValue
			return $this->formatDataValues( $value );
		}
	}

	/**
	 * Formats constraint parameters.
	 *
	 * @param array $parameters
	 *
	 * @return string
	 */
	private function formatParameters( $parameters ) {
		if ( $parameters === null || count( $parameters ) == 0 ) {
			return null;
		}

		$valueFormatter = function ( $value ) {
			return $this->formatValue( $value, false );
		};

		$formattedParameters = array ();
		foreach ( $parameters as $parameterName => $parameterValue ) {
			$formattedParameterValues = implode( ', ', $this->limitArrayLength( array_map( $valueFormatter, $parameterValue ) ) );
			$formattedParameters[ ] = sprintf( '%s: %s', $parameterName, $formattedParameterValues );
		}

		return implode( '; ', $formattedParameters );
	}

	/**
	 * Cuts an array after n values and appends dots if needed.
	 *
	 * @param array $array
	 *
	 * @return array
	 */
	private function limitArrayLength( $array ) {
		if ( count( $array ) > self::MAX_PARAMETER_ARRAY_LENGTH ) {
			$array = array_slice( $array, 0, self::MAX_PARAMETER_ARRAY_LENGTH );
			array_push( $array, '...' );
		}

		return $array;
	}


    /**
     * @param Entity $entity
     * @param array $results
     */
    private function saveResultsInViolationsTable( $entity, $results ) {
        $violations = $this->checkResultToViolationTranslator->translateToViolation( $entity, $results );
        foreach( $violations as $violation ) {
            $this->violationRepo->save( $violation );
        }
    }

}