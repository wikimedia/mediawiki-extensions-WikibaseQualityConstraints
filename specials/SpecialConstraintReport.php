<?php

namespace WikidataQuality\ConstraintReport\Specials;

use DataValues;
use DataValues\DataValue;
use Html;
use Wikibase\DataModel;
use Wikibase\DataModel\Entity\Entity;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\Lib\Store\EntityTitleLookup;
use Wikibase\Repo\WikibaseRepo;
use WikidataQuality\ConstraintReport\CheckForConstraintViolationsJob;
use WikidataQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikidataQuality\Html\HtmlTable;
use WikidataQuality\Html\HtmlTableHeader;
use WikidataQuality\Specials\SpecialCheckResultPage;
use JobQueueGroup;


/**
 * Class SpecialConstraintReport
 * Special page that displays all constraints that are defined on an Entity with additional information
 * (whether it complied or was a violation, which parameters the constraint has etc.).
 *
 * @package WikidataQuality\ConstraintReport\Specials
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class SpecialConstraintReport extends SpecialCheckResultPage {

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

	public function __construct() {
		parent::__construct( 'ConstraintReport' );

		$this->entityTitleLookup = WikibaseRepo::getDefaultInstance()->getEntityTitleLookup();
	}

	/**
	 * @see SpecialPage::getDescription
	 *
	 * @return string
	 */
	public function getDescription() {
		return $this->msg( 'wikidataquality-constraintreport' )->text();
	}

	/**
	 * @see SpecialCheckResultPage::getInstructionsText
	 *
	 * @return string
	 */
	protected function getInstructionsText() {
		return
			$this->msg( 'wikidataquality-constraintreport-instructions' )->text()
			. Html::element( 'br' )
			. $this->msg( 'wikidataquality-constraintreport-instructions-example' )->text();
	}

	/**
	 * @see SpecialCheckResultPage::getEmptyResultText
	 *
	 * @return string
	 */
	protected function getEmptyResultText() {
		return
			$this->msg( 'wikidataquality-constraintreport-empty-result' )->text();
	}

	/**
	 * @see SpecialCheckResultPage::executeCheck
	 *
	 * @param Entity $entity
	 *
	 * @return string
	 */
	protected function executeCheck( Entity $entity ) {
		// Run constraint checker
		$constraintChecker = new ConstraintChecker( $this->entityLookup );
		$results = $constraintChecker->execute( $entity );

		$this->doEvaluation( $entity, $results );
		return $results;
	}

	/**
	 * @see SpecialCheckResultPage::buildResultTable
	 *
	 * @param EntityId
	 * @param array|Traversable $results
	 *
	 * @return string
	 */
	protected function buildResultTable( EntityId $entityId, $results ) {
		// Set table headers
		$table = new HtmlTable(
			array (
				new HtmlTableHeader(
					$this->msg( 'wikidataquality-checkresult-result-table-header-status' )->text(),
					true
				),
				new HtmlTableHeader(
					$this->msg( 'wikidataquality-constraintreport-result-table-header-claim' )->text(),
					true
				),
				new HtmlTableHeader(
					$this->msg( 'wikidataquality-constraintreport-result-table-header-constraint' )->text(),
					true
				)
			)
		);

		foreach ( $results as $result ) {
			// Status column
			$statusColumn = $this->buildTooltipElement(
				$this->formatStatus( $result->getStatus() ),
				$result->getMessage()
			);

			// Claim column
			$property = $this->entityIdHtmlLinkFormatter->formatEntityId( $result->getPropertyId() );
			$value = $this->formatValue( $result->getDataValue() );
			$claimLink = $this->getClaimLink(
				$entityId,
				$result->getPropertyId(),
				$this->msg( 'wikidataquality-constraintreport-result-link-to-claim' )->text()
			);
			$claimColumn = sprintf( '%s: %s (%s)', $property, $value, $claimLink );

			// Constraint column
			$constraintLink = $this->getClaimLink(
				$result->getPropertyId(),
				new PropertyId( self::CONSTRAINT_PROPERTY_ID ),
				$this->msg( 'wikidataquality-constraintreport-result-link-to-constraint' )->text()
			);
			$constraintColumn = $this->buildTooltipElement(
				sprintf( '%s (%s)', $result->getConstraintName(), $constraintLink ),
				$this->formatParameters( $result->getParameters() )
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
	 * @param bool $linking
	 *
	 * @return string
	 */
	private function formatValue( $value, $linking = true ) {
		if ( is_string( $value ) ) {
			// Cases like 'Format' 'pattern' or 'minimum'/'maximum' values, which we have stored as strings
			return ( $value );
		} elseif ( $value instanceof EntityId ) {
			// Cases like 'Conflicts with' 'property', to which we can link
			if ( $linking ) {
				return $this->entityIdHtmlLinkFormatter->formatEntityId( $value );
			} else {
				return $this->entityIdLabelFormatter->formatEntityId( $value );
			}
		} else {
			// Cases where we format a DataValue
			return $this->formatDataValues( $value, $linking );
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
		//TODO: Push (deferred) job(s) in queue
		$checkTimeStamp = wfTimestamp( TS_MW );
		$jobs = array ();
		$jobs[ ] = CheckForConstraintViolationsJob::newInsertNow( $entity, $checkTimeStamp, $results );
		$jobs[ ] = CheckForConstraintViolationsJob::newInsertDeferred( $entity, $checkTimeStamp, 10 );

		$jobs[ 0 ]->run();
		$jobs[ 1 ]->run();
		JobQueueGroup::singleton()->push( $jobs );
	}

}
