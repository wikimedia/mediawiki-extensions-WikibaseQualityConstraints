<?php

namespace WikibaseQuality\ConstraintReport;

use Wikibase\DataModel\Entity\PropertyId;

/**
 * @author Lucas Werkmeister
 * @license GNU GPL v2+
 */
class ConstraintDeserializer {

	public function deserialize( array $serialization ) {
		return new Constraint(
			$serialization['id'],
			new PropertyId( $serialization['pid'] ),
			$serialization['qid'],
			array_key_exists( 'params', $serialization ) ?
				$serialization['params'] :
				[]
		);
	}

}
