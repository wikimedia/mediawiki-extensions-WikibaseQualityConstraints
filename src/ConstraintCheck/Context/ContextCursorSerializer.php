<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Context;

/**
 * A serializer for {@link ContextCursor}s.
 *
 * @license GNU GPL v2+
 */
class ContextCursorSerializer {

	/**
	 * @param ContextCursor $cursor
	 * @return array
	 */
	public function serialize( ContextCursor $cursor ) {
		$type = $cursor->getType();
		$serialization = [
			't' => $type,
			'i' => $cursor->getEntityId(),
			'p' => $cursor->getStatementPropertyId(),
			'g' => $cursor->getStatementGuid(),
			'h' => $cursor->getSnakHash(),
		];

		if ( $type === Context::TYPE_QUALIFIER || $type === Context::TYPE_REFERENCE ) {
			$serialization['P'] = $cursor->getSnakPropertyId();
			if ( $type === Context::TYPE_REFERENCE ) {
				/** @var ReferenceContextCursor $cursor */
				$serialization['r'] = $cursor->getReferenceHash();
			}
		}

		return $serialization;
	}

}
