<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Context;

use InvalidArgumentException;

/**
 * A deserializer for {@link ContextCursor}s.
 *
 * @license GNU GPL v2+
 */
class ContextCursorDeserializer {

	public function deserialize( array $serialization ) {
		switch ( $serialization['t'] ) {
			case Context::TYPE_STATEMENT:
				return new MainSnakContextCursor(
					$serialization['i'],
					$serialization['p'],
					$serialization['g'],
					$serialization['h']
				);
			case Context::TYPE_QUALIFIER:
				return new QualifierContextCursor(
					$serialization['i'],
					$serialization['p'],
					$serialization['g'],
					$serialization['h'],
					$serialization['P']
				);
			case Context::TYPE_REFERENCE:
				return new ReferenceContextCursor(
					$serialization['i'],
					$serialization['p'],
					$serialization['g'],
					$serialization['h'],
					$serialization['P'],
					$serialization['r']
				);
			default:
				throw new InvalidArgumentException(
					'Unknown serialization type ' . $serialization['t']
				);
		}
	}

}
