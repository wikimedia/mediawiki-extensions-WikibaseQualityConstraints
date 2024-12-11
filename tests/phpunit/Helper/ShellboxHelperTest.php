<?php

declare( strict_types = 1 );

namespace WikibaseQuality\ConstraintReport\Tests\Helper;

use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ShellboxHelper;

/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ShellboxHelper
 *
 * @group WikibaseQualityConstraints
 *
 * @author Audrey Penven
 * @license GPL-2.0-or-later
 */
class ShellboxHelperTest extends \PHPUnit\Framework\TestCase {

	public static function provideValidRegex(): iterable {
		return [
			'pattern matches text' => [ '\d+', '123', 1 ],
			'pattern does not match text' => [ '\d+', 'letters do not match', 0 ],
			'partial match does not count' => [ '\d+ partial', '789 partial match', 0 ],
			'pattern with slashes' => [ '/abc/def', '/abc/def', 1 ],
			'pattern with back-reference' => [ '([a-z])/\1[a-z_]*', 'a/abcd', 1 ],
		];
	}

	/**
	 * @dataProvider provideValidRegex
	 */
	public function testRunRegexCheckValidRegex( string $pattern, string $text, int $expected ) {
		self::assertEquals( $expected, ShellboxHelper::runRegexCheck( $pattern, $text ) );
	}

	public static function provideInvalidRegex(): iterable {
		return [
			'unmatched, unescaped parentheses' => [ '(filler-text', '(filler-text' ],
			'pattern with invalid back-reference' => [ '([a-z])/\2[a-z_]*', 'a/abcd' ],
		];
	}

	/**
	 * @dataProvider provideInvalidRegex
	 */
	public function testRunRegexCheckInvalidRegex( string $pattern, string $text ) {
		// implicitly asserts that no warnings or errors occur
		self::assertFalse( ShellboxHelper::runRegexCheck( $pattern, $text ) );
	}
}
