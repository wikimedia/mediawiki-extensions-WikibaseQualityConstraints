<?php

namespace WikibaseQuality\ConstraintReport\Tests;

use ValueFormatters\ValueFormatter;
use Wikibase\DataModel\Services\EntityId\PlainEntityIdFormatter;
use WikibaseQuality\ConstraintReport\ConstraintParameterRenderer;
use Wikibase\Repo\WikibaseRepo;

/**
 * @author Lucas Werkmeister
 * @license GNU GPL v2+
 */
trait ConstraintParameters {

	use DefaultConfig;

	/**
	 * @var ConstraintParameterRenderer
	 */
	private $renderer;

	public function getConstraintParameterRenderer() {
		if ( $this->renderer === null ) {
			$valueFormatter = $this->getMock( ValueFormatter::class );
			$valueFormatter->method( 'format' )->willReturn( '' );
			$entityIdFormatter = new PlainEntityIdFormatter();
			$this->renderer = new ConstraintParameterRenderer(
				$entityIdFormatter,
				$valueFormatter
			);
		}

		return $this->renderer;
	}

}
