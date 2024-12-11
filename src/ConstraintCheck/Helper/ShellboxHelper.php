<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Helper;

/**
 * Functions to run on Shellbox
 *
 * Since this file is sent on its own to Shellbox, it must not depend on
 * other MediaWiki code.
 *
 * @license GPL-2.0-or-later
 */
class ShellboxHelper {

	/**
	 * Wraps a call to preg_match, using parentheses as regex delimiters to avoid issues
	 * with escaping slashes.
	 * It silences warnings from preg_match when the regex is invalid, since this is
	 * expected. Without silencing, these warnings are enough to trigger a ShellboxError.
	 *
	 * @return false|int (from the preg_match documentation) returns 1 if the pattern
	 * matches given subject, 0 if it does not, or FALSE if an error occurred.
	 */
	public static function runRegexCheck( string $regex, string $text ) {
		$pattern = '(^(?:' . $regex . ')$)u';

		// `preg_match` emits an E_WARNING when the pattern is not valid regex.
		// Silence this warning to avoid throwing a ShellboxError.
		// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
		return @preg_match( $pattern, $text );
	}
}
