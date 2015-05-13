<?php

namespace WikidataQuality\ConstraintReport\Maintenance;

// @codeCoverageIgnoreStart
use WikidataQuality\ConstraintReport\Constraint;
use WikidataQuality\ConstraintReport\ConstraintReportFactory;


$basePath = getenv( "MW_INSTALL_PATH" ) !== false ? getenv( "MW_INSTALL_PATH" ) : __DIR__ . "/../../..";
require_once $basePath . "/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

class UpdateTable extends \Maintenance {

	public function __construct() {
		parent::__construct();

		$this->mDescription = "Reads csv file and writes its contents into constraints table";
		$this->addOption( 'csv-file', 'csv file that contains constraints parsed from the property talk pages.', true, true );
		$this->setBatchSize( 1000 );
	}

	public function execute(){
		$csvFile = fopen( $this->getOption( 'csv-file' ), 'rb' );
		$constraintRepo = ConstraintReportFactory::getDefaultInstance()->getConstraintRepository();
		$constraintRepo->deleteAll( $this->mBatchSize );
		$i = 0;
		$accumulator = array();
		while ( true ) {
			$data = fgetcsv( $csvFile );
			if ( $data === false || ++$i % $this->mBatchSize === 0 ) {
				$constraintRepo->insertBatch( $accumulator );
				if ( !$this->isQuiet() ) {
					print "\r\033[K";
					print "$i rows inserted";
				}

				$accumulator = array();

				if ( $data === false ) {
					break;
				}
			}

			$constraintParameters = (array) json_decode( $data[3] );
			$accumulator[] = new Constraint( $data[0], $data[1], $data[2], $constraintParameters );
		}

		fclose( $csvFile );
	}
}

// @codeCoverageIgnoreStart
$maintClass = 'WikidataQuality\ConstraintReport\Maintenance\UpdateTable';
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd