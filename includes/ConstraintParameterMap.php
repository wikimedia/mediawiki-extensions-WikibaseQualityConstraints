<?php

namespace WikidataQuality\ConstraintReport;


class ConstraintParameterMap {

	/**
	 * Maps the constraint name to an array of parameters they need to have
	 *
	 * @return array
	 */
	static function getMap() {
		return array(
			'Commons link' => array( 'namespace' ),
			'Conflicts with' => array( 'property', 'item' ),
			'Diff within range' => array( 'property', 'minimum_quantity', 'maximum_quantity' ),
			'Format' => array( 'pattern' ),
			'Inverse' => array( 'property' ),
			'Item' => array( 'property', 'item' ),
			'Mandatory qualifiers' => array( 'property' ),
			'Multi value' => array(),
			'One of' => array( 'item' ),
			'Qualifier' => array(),
			'Qualifiers' => array( 'property' ),
			'Range' => array( 'minimum_quantity', 'maximum_quantity', 'minimum_date', 'maximum_date' ),
			'Single value' => array(),
			'Symmetric' => array(),
			'Target required claim' => array( 'property', 'item' ),
			'Type' => array( 'class', 'relation' ),
			'Unique value' => array(),
			'Value type' => array( 'class', 'relation' )
		);
	}
}