<?php

namespace WikibaseQuality\ConstraintReport\Tests;

use ValueFormatters\ValueFormatter;
use Wikibase\DataModel\Services\EntityId\PlainEntityIdFormatter;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintStatementParameterParser;
use WikibaseQuality\ConstraintReport\ConstraintParameterRenderer;
use Wikibase\Repo\WikibaseRepo;

/**
 * @author Lucas Werkmeister
 * @license GNU GPL v2+
 */
trait ConstraintParameters {

	use DefaultConfig;

	/**
	 * @var ConstraintStatementParameterParser
	 */
	private $parser;

	/**
	 * @var ConstraintParameterRenderer
	 */
	private $renderer;

	/**
	 * @return ConstraintStatementParameterParser
	 */
	public function getConstraintParameterParser() {
		if ( $this->parser === null ) {
			$this->parser = new ConstraintStatementParameterParser(
				$this->getDefaultConfig(),
				WikibaseRepo::getDefaultInstance()->getBaseDataModelDeserializerFactory(),
				$this->getConstraintParameterRenderer()
			);
		}

		return $this->parser;
	}

	public function getConstraintParameterRenderer() {
		if ( $this->renderer === null ) {
			$valueFormatter = $this->getMock( ValueFormatter::class );
			$valueFormatter->method( 'format' )->willReturn( '?' );
			$entityIdFormatter = new PlainEntityIdFormatter();
			$this->renderer = new ConstraintParameterRenderer(
				$entityIdFormatter,
				$valueFormatter
			);
		}

		return $this->renderer;
	}

}
