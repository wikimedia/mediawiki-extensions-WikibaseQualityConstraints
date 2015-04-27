<?php

namespace WikidataQuality\ConstraintReport\ConstraintCheck;

use WikidataQuality\ConstraintReport\ConstraintCheck\Checker\CommonsLinkChecker;
use WikidataQuality\ConstraintReport\ConstraintCheck\Checker\FormatChecker;
use WikidataQuality\ConstraintReport\ConstraintCheck\Checker\OneOfChecker;
use WikidataQuality\ConstraintReport\ConstraintCheck\Checker\QualifierChecker;
use WikidataQuality\ConstraintReport\ConstraintCheck\Checker\RangeChecker;
use WikidataQuality\ConstraintReport\ConstraintCheck\Checker\TypeChecker;
use WikidataQuality\ConstraintReport\ConstraintCheck\Checker\ConflictsWithChecker;
use WikidataQuality\ConstraintReport\ConstraintCheck\Checker\QualifiersChecker;
use WikidataQuality\ConstraintReport\ConstraintCheck\Checker\TargetRequiredClaimChecker;
use WikidataQuality\ConstraintReport\ConstraintCheck\Checker\ItemChecker;
use WikidataQuality\ConstraintReport\ConstraintCheck\Checker\MandatoryQualifiersChecker;
use WikidataQuality\ConstraintReport\ConstraintCheck\Checker\ValueTypeChecker;
use WikidataQuality\ConstraintReport\ConstraintCheck\Checker\SymmetricChecker;
use WikidataQuality\ConstraintReport\ConstraintCheck\Checker\InverseChecker;
use WikidataQuality\ConstraintReport\ConstraintCheck\Checker\DiffWithinRangeChecker;
use WikidataQuality\ConstraintReport\ConstraintCheck\Checker\SingleValueChecker;
use WikidataQuality\ConstraintReport\ConstraintCheck\Checker\MultiValueChecker;
use WikidataQuality\ConstraintReport\ConstraintCheck\Checker\UniqueValueChecker;
use WikidataQuality\ConstraintReport\ConstraintCheck\Helper\ConnectionCheckerHelper;
use WikidataQuality\ConstraintReport\ConstraintCheck\Helper\RangeCheckerHelper;
use WikidataQuality\ConstraintReport\ConstraintCheck\Helper\TypeCheckerHelper;


/**
 * Builds a map that maps from the constraint name
 * to its corresponding checker
 *
 * @package WikidataQuality\ConstraintReport\ConstraintCheck
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class CheckerMapBuilder {

	/**
	 * @var CheckerMapBuilder
	 */
	private $checkerMap;

	public function __construct( $lookup, $constraintReportHelper ) {
		$connectionCheckerHelper = new ConnectionCheckerHelper();
		$rangeCheckerHelper = new RangeCheckerHelper();
		$typeCheckerHelper = new TypeCheckerHelper( $lookup );

		$map = array(
			'Conflicts with' => new ConflictsWithChecker( $lookup, $constraintReportHelper, $connectionCheckerHelper ),
			'Item' => new ItemChecker( $lookup, $constraintReportHelper, $connectionCheckerHelper ),
			'Target required claim' => new TargetRequiredClaimChecker( $lookup, $constraintReportHelper, $connectionCheckerHelper ),
			'Symmetric' => new SymmetricChecker( $lookup, $constraintReportHelper, $connectionCheckerHelper ),
			'Inverse' => new InverseChecker( $lookup, $constraintReportHelper, $connectionCheckerHelper ),
			'Qualifier' => new QualifierChecker( $constraintReportHelper ),
			'Qualifiers' => new QualifiersChecker( $constraintReportHelper ),
			'Mandatory qualifiers' => new MandatoryQualifiersChecker( $constraintReportHelper ),
			'Range' => new RangeChecker( $constraintReportHelper, $rangeCheckerHelper ),
			'Diff within range' => new DiffWithinRangeChecker( $constraintReportHelper, $rangeCheckerHelper ),
			'Type' => new TypeChecker( $lookup, $constraintReportHelper, $typeCheckerHelper ),
			'Value type' => new ValueTypeChecker( $lookup, $constraintReportHelper, $typeCheckerHelper ),
			'Single value' => new SingleValueChecker(),
			'Multi value' => new MultiValueChecker(),
			'Unique value' => new UniqueValueChecker(),
			'Format' => new FormatChecker( $constraintReportHelper ),
			'Commons link' => new CommonsLinkChecker( $constraintReportHelper ),
			'One of' => new OneOfChecker( $constraintReportHelper ),
		);
		$this->checkerMap = $map;
	}

	public function getCheckerMap() {
		return $this->checkerMap;
	}

}