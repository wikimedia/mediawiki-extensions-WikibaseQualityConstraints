<?php

declare( strict_types = 1 );

namespace WikibaseQuality\ConstraintReport;

use MediaWiki\Config\Config;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\JobQueue\JobSpecification;
use Wikibase\DataModel\Entity\NumericPropertyId;
use Wikibase\Lib\Changes\Change;
use Wikibase\Lib\Changes\EntityChange;
use Wikibase\Lib\Changes\EntityDiffChangedAspects;
use Wikibase\Repo\Hooks\WikibaseChangeNotificationHook;
use WikibaseQuality\ConstraintReport\Job\CheckConstraintsJob;

/**
 * Handler for WikibaseCahngeNotificationHook
 *
 * @license GPL-2.0-or-later
 */
class WikibaseChangeNotificationHookHandler implements WikibaseChangeNotificationHook {

	private Config $config;
	private JobQueueGroup $jobQueueGroup;

	public function __construct(
		JobQueueGroup $jobQueueGroup,
		Config $config
	) {
		$this->config = $config;
		$this->jobQueueGroup = $jobQueueGroup;
	}

	/** @inheritDoc */
	public function onWikibaseChangeNotification( Change $change ): void {
		if ( !( $change instanceof EntityChange ) ) {
			return;
		}
		/** @var EntityChange $change */

		// If jobs are enabled and the results would be stored in some way run a job.
		if (
			$this->config->get( 'WBQualityConstraintsEnableConstraintsCheckJobs' ) &&
			$this->config->get( 'WBQualityConstraintsCacheCheckConstraintsResults' ) &&
			$this->isSelectedForJobRunBasedOnPercentage()
		) {
			$params = [ 'entityId' => $change->getEntityId()->getSerialization() ];
			$this->jobQueueGroup->lazyPush(
				new JobSpecification( CheckConstraintsJob::COMMAND, $params )
			);
		}

		if ( $this->config->get( 'WBQualityConstraintsEnableConstraintsImportFromStatements' ) &&
			$this->isConstraintStatementsChange( $change )
		) {
			$params = [ 'propertyId' => $change->getEntityId()->getSerialization() ];
			$metadata = $change->getMetadata();
			if ( array_key_exists( 'rev_id', $metadata ) ) {
				$params['revisionId'] = $metadata['rev_id'];
			}
			$this->jobQueueGroup->push(
				new JobSpecification( 'constraintsTableUpdate', $params )
			);
		}
	}

	private function isSelectedForJobRunBasedOnPercentage(): bool {
		$percentage = $this->config->get( 'WBQualityConstraintsEnableConstraintsCheckJobsRatio' );

		return mt_rand( 1, 100 ) <= $percentage;
	}

	private function isConstraintStatementsChange( Change $change ): bool {
		if ( !( $change instanceof EntityChange ) ||
			 $change->getAction() !== EntityChange::UPDATE ||
			 !( $change->getEntityId() instanceof NumericPropertyId )
		) {
			return false;
		}

		$info = $change->getInfo();

		if ( !array_key_exists( 'compactDiff', $info ) ) {
			// the non-compact diff ($info['diff']) does not contain statement diffs (T110996),
			// so we only know that the change *might* affect the constraint statements
			return true;
		}

		/** @var EntityDiffChangedAspects $aspects */
		$aspects = $info['compactDiff'];

		$propertyConstraintId = $this->config->get( 'WBQualityConstraintsPropertyConstraintId' );
		return in_array( $propertyConstraintId, $aspects->getStatementChanges() );
	}

}
