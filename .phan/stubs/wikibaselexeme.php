<?php

/**
 * Minimal set of classes necessary to fulfill needs of parts of WikibaseQualityConstraints relying on
 * the WikibaseLexeme extension.
 */

namespace Wikibase\Lexeme\Domain\Model {

	use Wikibase\DataModel\Entity\ItemId;

	class Lexeme {
		public const ENTITY_TYPE = 'lexeme';

		public function getLanguage() {
			return new ItemId( 'Q1' );
		}
	}

	class Form {
		public const ENTITY_TYPE = 'form';
	}

	class Sense {
		public const ENTITY_TYPE = 'sense';
	}
}
