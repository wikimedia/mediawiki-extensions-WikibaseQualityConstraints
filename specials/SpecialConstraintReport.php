<?php

namespace WikibaseQuality\ConstraintReport\Specials;

use JobQueueGroup;
use SpecialPage;
use ValueFormatters\FormatterOptions;
use ValueFormatters\ValueFormatter;
use Wikibase\DataModel\Services\EntityId\EntityIdFormatter;
use Wikibase\DataModel\Services\EntityId\EntityIdParser;
use Wikibase\DataModel\Services\EntityId\EntityIdParsingException;
use HTMLForm;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\DataModel\Services\Lookup\LanguageLabelDescriptionLookup;
use Wikibase\DataModel\Services\Lookup\TermLookup;
use Wikibase\Lib\OutputFormatValueFormatterFactory;
use Wikibase\Lib\SnakFormatter;
use DataValues;
use DataValues\DataValue;
use Html;
use InvalidArgumentException;
use UnexpectedValueException;
use Wikibase\DataModel\Entity\Entity;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\Lib\Store\EntityTitleLookup;
use Wikibase\Repo\EntityIdHtmlLinkFormatterFactory;
use Wikibase\Repo\EntityIdLabelFormatterFactory;
use Wikibase\Repo\WikibaseRepo;
use WikibaseQuality\ConstraintReport\ConstraintCheck\DelegatingConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikibaseQuality\ConstraintReport\ConstraintReportFactory;
use WikibaseQuality\ConstraintReport\EvaluateConstraintReportJob;
use WikibaseQuality\ConstraintReport\EvaluateConstraintReportJobService;
use WikibaseQuality\ConstraintReport\Violations\CheckResultToViolationTranslator;
use WikibaseQuality\Html\HtmlTableBuilder;
use WikibaseQuality\Html\HtmlTableCellBuilder;
use WikibaseQuality\Html\HtmlTableHeaderBuilder;
use WikibaseQuality\Violations\ViolationStore;
use WikibaseQuality\WikibaseQualityServices;


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
	 * @var EntityIdParser
	 */
	private $entityIdParser;

	/**
	 * @var EntityLookup
	 */
	private $entityLookup;

	/**
	 * @var EntityTitleLookup
	 */
	private $entityTitleLookup;

	/**
	 * @var ValueFormatter
	 */
	private $dataValueFormatter;

	/**
	 * @var EntityIdFormatter
	 */
	private $entityIdLabelFormatter;

	/**
	 * @var EntityIdFormatter
	 */
	private $entityIdLinkFormatter;

	/**
	 * @var DelegatingConstraintChecker
	 */
	private $constraintChecker;

	/**
	 * @var CheckResultToViolationTranslator
	 */
	private $checkResultToViolationTranslator;

	/**
	 * @var ViolationStore
	 */
	private $violationStore;

	public static function newFromGlobalState() {
		$wikibaseQuality = WikibaseQualityServices::getDefaultInstance();
		$constraintReportFactory = ConstraintReportFactory::getDefaultInstance();
		$wikibaseRepo = WikibaseRepo::getDefaultInstance();

		return new self(
			$wikibaseRepo->getEntityLookup(),
			$wikibaseRepo->getTermLookup(),
			$wikibaseRepo->getEntityTitleLookup(),
			new EntityIdLabelFormatterFactory(),
			$wikibaseRepo->getEntityIdHtmlLinkFormatterFactory(),
			$wikibaseRepo->getEntityIdParser(),
			$wikibaseRepo->getValueFormatterFactory(),
			$constraintReportFactory->getConstraintChecker(),
			$constraintReportFactory->getCheckResultToViolationTranslator(),
			$wikibaseQuality->getViolationStore()
		);
	}

	/**
	 * @param EntityLookup $entityLookup
	 * @param TermLookup $termLookup
	 * @param EntityTitleLookup $entityTitleLookup
	 * @param EntityIdLabelFormatterFactory $entityIdLabelFormatterFactory
	 * @param EntityIdHtmlLinkFormatterFactory $entityIdHtmlLinkFormatterFactory
	 * @param EntityIdParser $entityIdParser
	 * @param OutputFormatValueFormatterFactory $valueFormatterFactory
	 * @param DelegatingConstraintChecker $constraintChecker
	 * @param CheckResultToViolationTranslator $checkResultToViolationTranslator
	 * @param ViolationStore $violationStore
	 */
	public function __construct(
		EntityLookup $entityLookup,
		TermLookup $termLookup,
		EntityTitleLookup $entityTitleLookup,
		EntityIdLabelFormatterFactory $entityIdLabelFormatterFactory,
		EntityIdHtmlLinkFormatterFactory $entityIdHtmlLinkFormatterFactory,
		EntityIdParser $entityIdParser,
		OutputFormatValueFormatterFactory $valueFormatterFactory,
		DelegatingConstraintChecker $constraintChecker,
		CheckResultToViolationTranslator $checkResultToViolationTranslator,
		ViolationStore $violationStore
	) {
		parent::__construct( 'ConstraintReport' );

		$this->entityLookup = $entityLookup;
		$this->entityTitleLookup = $entityTitleLookup;
		$this->entityIdParser = $entityIdParser;

		$formatterOptions = new FormatterOptions();
		$formatterOptions->setOption( SnakFormatter::OPT_LANG, $this->getLanguage()->getCode() );
		$this->dataValueFormatter = $valueFormatterFactory->getValueFormatter( SnakFormatter::FORMAT_HTML, $formatterOptions );

		$labelLookup = new LanguageLabelDescriptionLookup( $termLookup, $this->getLanguage()->getCode() );
		$this->entityIdLabelFormatter = $entityIdLabelFormatterFactory->getEntityIdFormater( $labelLookup );
		$this->entityIdLinkFormatter = $entityIdHtmlLinkFormatterFactory->getEntityIdFormater( $labelLookup );

		$this->constraintChecker = $constraintChecker;
		$this->checkResultToViolationTranslator = $checkResultToViolationTranslator;
		$this->violationStore = $violationStore;
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

		$out->addModules( 'SpecialConstraintReportPage' );

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
				$this->msg( $message )->text()
			);
	}

	/**
	 * @return string HTML
	 */
	private function getExplanationText() {
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
	 * @see SpecialCheckResultPage::executeCheck
	 *
	 * @param Entity $entity
	 *
	 * @return string
	 */
	private function executeCheck( Entity $entity ) {
		$results = $this->constraintChecker->checkAgainstConstraints( $entity );

		if ( !defined( 'MW_PHPUNIT_TEST' ) ){
			$this->doEvaluation( $entity, $results );
		}

		return $results;
	}

	/**
	 * @see SpecialCheckResultPage::buildResultTable
	 *
	 * @param EntityId $entityId
	 * @param CheckResult[] $results
	 * @return string HTML
	 */
	private function buildResultTable( EntityId $entityId, $results ) {
		// Set table headers
		$table = new HtmlTableBuilder(
			array (
				new HtmlTableHeaderBuilder(
					$this->msg( 'wbqc-constraintreport-result-table-header-status' )->escaped(),
					true
				),
				new HtmlTableHeaderBuilder(
					$this->msg( 'wbqc-constraintreport-result-table-header-claim' )->escaped(),
					true
				),
				new HtmlTableHeaderBuilder(
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
					new HtmlTableCellBuilder(
						$statusColumn,
						array(),
						true
					),
					new HtmlTableCellBuilder(
						$claimColumn,
						array(),
						true
					),
					new HtmlTableCellBuilder(
						$constraintColumn,
						array(),
						true
					)
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
	private function buildResultHeader( EntityId $entityId ) {
		$entityLink = sprintf( '%s (%s)',
							   $this->entityIdLinkFormatter->formatEntityId( $entityId ),
							   htmlspecialchars( $entityId->getSerialization() ) );

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
	 * @param string $content (sanitized HTML)
	 * @param string $tooltipContent
	 * @param $indicator
	 *
	 * @throws InvalidArgumentException
	 *
	 * @return string HTML
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

		$tooltip = HTML::element(
			'div',
			array (
				'class' => 'wbqc-tooltip'
			),
			$tooltipContent
		);

		return
			Html::openElement(
				'span'
			)
			. sprintf( '%s %s %s', $content, $tooltipIndicator, $tooltip )
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
			return $this->formatValue( $value );
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
			$this->violationStore->insert( $violation, true );
		}
	}

	private function doEvaluation( $entity, $results ) {
		$checkTimeStamp = wfTimestamp( TS_UNIX );
		$service = new EvaluateConstraintReportJobService();
		$results = $service->buildResultSummary( $results );
		$jobs = array ();
		$jobs[] = EvaluateConstraintReportJob::newInsertNow( $entity->getId()->getSerialization(), $checkTimeStamp, $results );
		$jobs[] = EvaluateConstraintReportJob::newInsertDeferred( $entity->getId()->getSerialization(), $checkTimeStamp, 10*60 );
		$jobs[] = EvaluateConstraintReportJob::newInsertDeferred( $entity->getId()->getSerialization(), $checkTimeStamp, 60*60 );
		JobQueueGroup::singleton()->push( $jobs );
	}
}
