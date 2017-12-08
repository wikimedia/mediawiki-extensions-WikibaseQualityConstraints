<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Cache;

/**
 * A response of the CheckConstraints API action,
 * along with information whether and how it was cached.
 */
class CachedCheckConstraintsResponse extends CachedArray {

	/**
	 * @return array The API response.
	 * The format is based on the Wikibase JSON format;
	 * for details, see [[mw:Wikibase/API#wbcheckconstraints]].
	 */
	public function getArray() {
		return parent::getArray();
	}

}
