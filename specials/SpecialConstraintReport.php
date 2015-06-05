<?php

namespace WikibaseQuality\ConstraintReport\Specials;

use SpecialPage;
use ValueFormatters\FormatterOptions;
use Wikibase\Lib\EntityIdHtmlLinkFormatter;
use Wikibase\Lib\EntityIdLabelFormatter;
use Wikibase\Lib\EntityIdLinkFormatter;
use Wikibase\Lib\HtmlUrlFormatter;
use HTMLForm;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\Lib\LanguageNameLookup;
use Wikibase\Lib\SnakFormatter;
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
use JobQueueGroup;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\Lib\Store\EntityTitleLookup;
use WikibaseQuality\ConstraintReport\ConstraintReportFactory;
use WikibaseQuality\ConstraintReport\EvaluateConstraintReportJob;
use WikibaseQuality\ConstraintReport\EvaluateConstraintReportJobService;
use WikibaseQuality\Html\HtmlTable;
use WikibaseQuality\Html\HtmlTableHeader;


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
	 * @var \Wikibase\DataModel\Entity\EntityIdParser
	 */
	protected $entityIdParser;

	/**
	 * @var \Wikibase\Lib\Store\EntityLookup
	 */
	protected $entityLookup;

	/**
	 * @var \ValueFormatters\ValueFormatter
	 */
	protected $dataValueFormatter;

	/**
	 * @var EntityIdLabelFormatter
	 */
	protected $entityIdLabelFormatter;

	/**
	 * @var EntityIdLinkFormatter
	 */
	protected $entityIdLinkFormatter;

	/**
	 * @var EntityIdHtmlLinkFormatter
	 */
	protected $entityIdHtmlLinkFormatter;

	/**
	 * @var HtmlUrlFormatter
	 */
	protected $htmlUrlFormatter;

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

		// Get entity lookup
		$this->entityLookup = $repo->getEntityLookup();

		// Get entity id parser
		$this->entityIdParser = $repo->getEntityIdParser();

		// Get value formatter
		$formatterOptions = new FormatterOptions();
		$formatterOptions->setOption( SnakFormatter::OPT_LANG, $this->getLanguage()->getCode() );
		$this->dataValueFormatter = $repo->getValueFormatterFactory()->getValueFormatter( SnakFormatter::FORMAT_HTML, $formatterOptions );

		// Get entity id link formatters
		$entityTitleLookup = $repo->getEntityTitleLookup();
		$labelLookup = new LanguageLabelDescriptionLookup( $repo->getTermLookup(), $this->getLanguage()->getCode() );
		$this->entityIdLabelFormatter = new EntityIdLabelFormatter( $labelLookup );
		$this->entityIdLinkFormatter = new EntityIdLinkFormatter( $entityTitleLookup );
		$this->entityIdHtmlLinkFormatter = new EntityIdHtmlLinkFormatter(
			$labelLookup,
			$entityTitleLookup,
			new LanguageNameLookup()
		);

		// Get url formatter
		$formatterOptions = new FormatterOptions();
		$this->htmlUrlFormatter = new HtmlUrlFormatter( $formatterOptions );

		$this->entityTitleLookup = WikibaseRepo::getDefaultInstance()->getEntityTitleLookup();
	}

	/**
	 * Returns array of modules that should be added
	 *
	 * @return array
	 */
	protected function getModules() {
		return array ( 'SpecialConstraintReportPage' );
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
		return $this->msg( 'wbqc-constraintreport' )->escaped();
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

        $postRequest = $this->getContext()->getRequest()->getVal( 'entityid' );
        if ( $postRequest ) {
            $out->redirect( $this->getPageTitle( strtoupper( $postRequest ) )->getLocalURL() );
            return;
        }

		$out->addModules( $this->getModules() );

		$this->setHeaders();

		$out->addHTML( $this->getExplanationText() );
        $this->buildEntityIdForm();

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
				$this->buildNotice( 'wbqc-constraintreport-invalid-entity-id', true )
			);
			return;
		}

		if ( !$entity ) {
			$out->addHTML(
				$this->buildNotice( 'wbqc-constraintreport-not-existent-entity', true )
			);
			return;
		}

		$results = $this->executeCheck( $entity );

		if ( $results && count( $results ) > 0 ) {
			$out->addHTML(
				$this->buildResultHeader( $entityId )
				. $this->buildSummary( $results )
				. $this->buildResultTable( $entityId, $results )
			);
		} else {
			$out->addHTML(
				$this->buildResultHeader( $entityId )
				. $this->buildNotice( 'wbqc-constraintreport-empty-result' )
			);
		}
	}
    
    /**
     * Builds html form for entity id input
     */
    private function buildEntityIdForm() {
        $formDescriptor = array(
            'entityid' => array(
                'class' => 'HTMLTextField',
                'section' => 'section',
                'name' => 'entityid',
                'label-message' => 'wbqc-constraintreport-form-entityid-label',
                'cssclass' => 'wbqc-constraintreport-form-entity-id',
                'placeholder' => $this->msg( 'wbqc-constraintreport-form-entityid-placeholder' )->escaped()
            )
        );
        $htmlForm = new HTMLForm( $formDescriptor, $this->getContext(), 'wbqc-constraintreport-form' );
        $htmlForm->setSubmitText( $this->msg( 'wbqc-constraintreport-form-submit-label' )->escaped() );
        $htmlForm->setSubmitCallback( function() {
            return false;
        } );
        $htmlForm->setMethod( 'post' );
        $htmlForm->show();
    }

	/**
	 * Builds notice with given message. Optionally notice can be handles as error by settings $error to true
	 *
	 * @param string $message
	 * @param bool $error
     *
     * @throws InvalidArgumentException
	 *
	 * @return string HTML
	 */
	protected function buildNotice( $message, $error = false ) {
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
				$this->msg( $message )->text()
            );
	}

    /**
     * @return string HTML
     */
	protected function getExplanationText() {
		return
			Html::openElement( 'div', array( 'class' => 'wbqc-explanation') )
			. $this->msg( 'wbqc-constraintreport-explanation-part-one' )->escaped()
			. Html::closeElement( 'div' )
			. Html::element( 'br' )
			. Html::openElement( 'div', array( 'class' => 'wbqc-explanation') )
			. $this->msg( 'wbqc-constraintreport-explanation-part-two' )->escaped()
			. Html::closeElement( 'div' );
	}

	/**
	 * @see SpecialCheckResultPage::getEmptyResultText
	 *
	 * @return string
	 */
	protected function getEmptyResultText() {
		return
			$this->msg( 'wbqc-constraintreport-empty-result' )->text();
	}

	/**
	 * @see SpecialCheckResultPage::executeCheck
	 *
	 * @param Entity $entity
	 *
	 * @return string
	 */
	protected function executeCheck( Entity $entity ) {

		$constraintChecker = ConstraintReportFactory::getDefaultInstance()->getConstraintChecker();
		$results = $constraintChecker->checkAgainstConstraints( $entity );

		if ( !defined( 'MW_PHPUNIT_TEST' ) ){
			$this->doEvaluation( $entity, $results );
		}
		return $results;
	}

	/**
	 * @see SpecialCheckResultPage::buildResultTable
	 *
	 * @param EntityId $entityId
	 * @param array $results
	 *
	 * @return string HTML
	 */
	protected function buildResultTable( EntityId $entityId, $results ) {
		// Set table headers
		$table = new HtmlTable(
			array (
				new HtmlTableHeader(
					$this->msg( 'wbqc-constraintreport-result-table-header-status' )->escaped(),
					true
				),
				new HtmlTableHeader(
					$this->msg( 'wbqc-constraintreport-result-table-header-claim' )->escaped(),
					true
				),
				new HtmlTableHeader(
					$this->msg( 'wbqc-constraintreport-result-table-header-constraint' )->escaped(),
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
	 * @return string HTML
	 */
	protected function buildResultHeader( EntityId $entityId ) {
		$entityLink = sprintf( '%s (%s)',
							   $this->entityIdHtmlLinkFormatter->formatEntityId( $entityId ),
							   $entityId->getSerialization() );

		return
			Html::openElement( 'h3' )
			. sprintf( '%s %s', $this->msg( 'wbqc-constraintreport-result-headline' )->escaped(), $entityLink )
			. Html::closeElement( 'h3' );
	}

	/**
	 * Builds summary from given results
	 *
	 * @param array $results
	 *
	 * @return string HTML
	 */
	protected function buildSummary( $results ) {
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
	 * @param string $content (sanitized HTML)
	 * @param string $tooltipContent
	 * @param $indicator
     *
     * @throws InvalidArgumentException
	 *
	 * @return string HTML
	 */
	protected function buildTooltipElement( $content, $tooltipContent, $indicator ) {
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
	 * @return string HTML
	 */
	protected function buildExpandableElement( $content, $expandableContent, $indicator ) {
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
	 * @return string HTML
	 */
    private function formatStatus( $status ) {
        $messageName = "wbqc-constraintreport-status-" . strtolower( $status );

        $formattedStatus =
            Html::element(
                'span',
                array (
                    'class' => 'wbqc-status wbqc-status-' . $status
                ),
                $this->msg( $messageName )->text()
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
	 * @return string HTML
	 */
	protected function formatDataValues( $dataValues, $separator = ', ' ) {
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
	 * @return string HTML
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
	 * @see SpecialCheckResultPage::getStatusMapping
	 *
	 * @return array
	 */
	protected function getStatusMapping() {
		return array (
			'compliance' => 'success',
			'exception' => 'warning',
			'violation' => 'error'
		);
	}

	protected function doEvaluation( $entity, $results ) {
		$checkTimeStamp = wfTimestamp( TS_UNIX );
		$service = new EvaluateConstraintReportJobService();
		$results = $service->buildResultSummary( $results );
		$jobs = array ();
		$jobs[] = EvaluateConstraintReportJob::newInsertNow( $entity->getId()->getSerialization(), $checkTimeStamp, $results );
		$jobs[] = EvaluateConstraintReportJob::newInsertDeferred( $entity->getId()->getSerialization(), $checkTimeStamp, 15*60 );
		$jobs[] = EvaluateConstraintReportJob::newInsertDeferred( $entity->getId()->getSerialization(), $checkTimeStamp, 60*60 );
		JobQueueGroup::singleton()->push( $jobs );
	}

}
