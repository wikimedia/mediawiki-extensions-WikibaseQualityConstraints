<?php

namespace WikibaseQuality\ConstraintReport\Api;

use Config;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\EntityId\EntityIdFormatter;
use Wikibase\Lib\Store\EntityTitleLookup;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\Metadata;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachedCheckConstraintsResponse;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\Context;
use WikibaseQuality\ConstraintReport\ConstraintCheck\DelegatingConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageRenderer;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\NullResult;
use WikibaseQuality\ConstraintReport\ConstraintParameterRenderer;

/**
 * @author Lucas Werkmeister
 * @license GPL-2.0-or-later
 */
class CheckingResultsBuilder implements ResultsBuilder {

	/**
	 * @var DelegatingConstraintChecker
	 */
	private $delegatingConstraintChecker;

	/**
	 * @var EntityTitleLookup
	 */
	private $entityTitleLookup;

	/**
	 * @var EntityIdFormatter
	 */
	private $entityIdLabelFormatter;

	/**
	 * @var ConstraintParameterRenderer
	 */
	private $constraintParameterRenderer;

	/**
	 * @var ViolationMessageRenderer
	 */
	private $violationMessageRenderer;

	/**
	 * @var Config
	 */
	private $config;

	public function __construct(
		DelegatingConstraintChecker $delegatingConstraintChecker,
		EntityTitleLookup $entityTitleLookup,
		EntityIdFormatter $entityIdLabelFormatter,
		ConstraintParameterRenderer $constraintParameterRenderer,
		ViolationMessageRenderer $violationMessageRenderer,
		Config $config
	) {
		$this->delegatingConstraintChecker = $delegatingConstraintChecker;
		$this->entityTitleLookup = $entityTitleLookup;
		$this->entityIdLabelFormatter = $entityIdLabelFormatter;
		$this->constraintParameterRenderer = $constraintParameterRenderer;
		$this->violationMessageRenderer = $violationMessageRenderer;
		$this->config = $config;
	}

	/**
	 * @param EntityId[] $entityIds
	 * @param string[] $claimIds
	 * @param string[]|null $constraintIds
	 * @param string[] $statuses
	 * @return CachedCheckConstraintsResponse
	 */
	public function getResults(
		array $entityIds,
		array $claimIds,
		array $constraintIds = null,
		array $statuses
	) {
		$response = [];
		$metadatas = [];
		$statusesFlipped = array_flip( $statuses );
		foreach ( $entityIds as $entityId ) {
			$results = $this->delegatingConstraintChecker->checkAgainstConstraintsOnEntityId(
				$entityId,
				$constraintIds,
				[ $this, 'defaultResults' ]
			);
			foreach ( $results as $result ) {
				$metadatas[] = $result->getMetadata();
				if ( $this->statusSelected( $statusesFlipped, $result ) ) {
					$resultArray = $this->checkResultToArray( $result );
					$result->getContextCursor()->storeCheckResultInArray( $resultArray, $response );
				}
			}
		}
		foreach ( $claimIds as $claimId ) {
			$results = $this->delegatingConstraintChecker->checkAgainstConstraintsOnClaimId(
				$claimId,
				$constraintIds,
				[ $this, 'defaultResults' ]
			);
			foreach ( $results as $result ) {
				$metadatas[] = $result->getMetadata();
				if ( $this->statusSelected( $statusesFlipped, $result ) ) {
					$resultArray = $this->checkResultToArray( $result );
					$result->getContextCursor()->storeCheckResultInArray( $resultArray, $response );
				}
			}
		}
		return new CachedCheckConstraintsResponse(
			$response,
			Metadata::merge( $metadatas )
		);
	}

	public function defaultResults( Context $context ) {
		return $context->getType() === Context::TYPE_STATEMENT ?
			[ new NullResult( $context ) ] :
			[];
	}

	public function statusSelected( array $statusesFlipped, CheckResult $result ) {
		return array_key_exists( $result->getStatus(), $statusesFlipped ) ||
			$result instanceof NullResult;
	}

	public function checkResultToArray( CheckResult $checkResult ) {
		if ( $checkResult instanceof NullResult ) {
			return null;
		}

		$constraintId = $checkResult->getConstraint()->getConstraintId();
		$typeItemId = $checkResult->getConstraint()->getConstraintTypeItemId();
		$constraintPropertyId = new PropertyId( $checkResult->getContextCursor()->getSnakPropertyId() );

		$title = $this->entityTitleLookup->getTitleForId( $constraintPropertyId );
		$typeLabel = $this->entityIdLabelFormatter->formatEntityId( new ItemId( $typeItemId ) );
		// TODO link to the statement when possible (T169224)
		$link = $title->getFullURL() . '#' . $this->config->get( 'WBQualityConstraintsPropertyConstraintId' );

		$constraint = [
			'id' => $constraintId,
			'type' => $typeItemId,
			'typeLabel' => $typeLabel,
			'link' => $link,
			'discussLink' => $title->getTalkPage()->getFullURL(),
		];
		if ( $this->config->get( 'WBQualityConstraintsIncludeDetailInApi' ) ) {
			$parameters = $checkResult->getParameters();
			$constraint += [
				'detail' => $parameters,
				'detailHTML' => $this->constraintParameterRenderer->formatParameters( $parameters ),
			];
		}

		$result = [
			'status' => $checkResult->getStatus(),
			'property' => $constraintPropertyId->getSerialization(),
			'constraint' => $constraint
		];
		$message = $checkResult->getMessage();
		if ( $message ) {
			$result['message-html'] = $this->violationMessageRenderer->render( $message );
		}
		if ( $checkResult->getContextCursor()->getType() === Context::TYPE_STATEMENT ) {
			$result['claim'] = $checkResult->getContextCursor()->getStatementGuid();
		}
		$cachingMetadataArray = $checkResult->getMetadata()->getCachingMetadata()->toArray();
		if ( $cachingMetadataArray !== null ) {
			$result['cached'] = $cachingMetadataArray;
		}

		return $result;
	}

}
