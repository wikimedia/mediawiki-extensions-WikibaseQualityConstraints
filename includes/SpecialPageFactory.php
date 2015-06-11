<?php

namespace WikibaseQuality\ConstraintReport;

use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\Lib\OutputFormatValueFormatterFactory;
use Wikibase\Lib\Store\EntityLookup;
use Wikibase\Lib\Store\EntityTitleLookup;
use Wikibase\Lib\Store\TermLookup;
use Wikibase\Repo\WikibaseRepo;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\DelegatingConstraintChecker;
use WikibaseQuality\ConstraintReport\Specials\SpecialConstraintReport;
use WikibaseQuality\ConstraintReport\Violations\CheckResultToViolationTranslator;
use WikibaseQuality\ExternalValidation\CrossCheck\CrossCheckInteractor;
use WikibaseQuality\ExternalValidation\DumpMetaInformation\DumpMetaInformationRepo;
use WikibaseQuality\ExternalValidation\Specials\SpecialCrossCheck;
use WikibaseQuality\ExternalValidation\Specials\SpecialExternalDbs;
use WikibaseQuality\ExternalValidation\Violations\CrossCheckResultToViolationTranslator;
use WikibaseQuality\Violations\ViolationStore;
use WikibaseQuality\WikibaseQualityFactory;


/**
 * Class SpecialPageFactory
 * @package WikibaseQuality\ConstraintReport
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class SpecialPageFactory {

	/**
	 * @var EntityLookup
	 */
	private $entityLookup;

	/**
	 * @var EntityTitleLookup
	 */
	private $entityTitleLookup;

	/**
	 * @var TermLookup
	 */
	private $termLookup;

	/**
	 * @var EntityIdParser
	 */
	private $entityIdParser;

	/**
	 * @var OutputFormatValueFormatterFactory
	 */
	private $valueFormatterFactory;

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


	private static function newFromGlobalState() {
		$wikibaseQuality = WikibaseQualityFactory::getDefaultInstance();
		$constraintReportFactory = ConstraintReportFactory::getDefaultInstance();
		$wikibaseRepo = WikibaseRepo::getDefaultInstance();

		return new self(
			$wikibaseRepo->getEntityLookup(),
			$wikibaseRepo->getTermLookup(),
			$wikibaseRepo->getEntityTitleLookup(),
			$wikibaseRepo->getEntityIdParser(),
			$wikibaseRepo->getValueFormatterFactory(),
			$constraintReportFactory->getConstraintChecker(),
			$constraintReportFactory->getCheckResultToViolationTranslator(),
			$wikibaseQuality->getViolationStore()
		);
	}

	/**
	 * @return SpecialCrossCheck
	 */
	public static function newSpecialConstraintReport() {
		return self::newFromGlobalState()->createSpecialConstraintReport();
	}

	/**
	 * @param EntityLookup $entityLookup
	 * @param TermLookup $termLookup
	 * @param EntityTitleLookup $entityTitleLookup
	 * @param EntityIdParser $entityIdParser
	 * @param OutputFormatValueFormatterFactory $valueFormatterFactory
	 * @param DelegatingConstraintChecker $constraintChecker
	 * @param CheckResultToViolationTranslator $checkResultToViolationTranslator
	 * @param ViolationStore $violationStore
	 */
	public function __construct( EntityLookup $entityLookup, TermLookup $termLookup, EntityTitleLookup $entityTitleLookup,
								 EntityIdParser $entityIdParser, OutputFormatValueFormatterFactory $valueFormatterFactory,
								 DelegatingConstraintChecker $constraintChecker, CheckResultToViolationTranslator $checkResultToViolationTranslator,
								 ViolationStore $violationStore ) {
		$this->entityLookup = $entityLookup;
		$this->termLookup = $termLookup;
		$this->entityTitleLookup = $entityTitleLookup;
		$this->entityIdParser = $entityIdParser;
		$this->valueFormatterFactory = $valueFormatterFactory;
		$this->constraintChecker = $constraintChecker;
		$this->checkResultToViolationTranslator = $checkResultToViolationTranslator;
		$this->violationStore = $violationStore;
	}

	/**
	 * @return SpecialCrossCheck
	 */
	public function createSpecialConstraintReport() {
		return new SpecialConstraintReport(
			$this->entityLookup,
			$this->termLookup,
			$this->entityTitleLookup,
			$this->entityIdParser,
			$this->valueFormatterFactory,
			$this->constraintChecker,
			$this->checkResultToViolationTranslator,
			$this->violationStore
		);
	}
}