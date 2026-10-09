<?php

declare(strict_types=1);

namespace PhpChecker\Support;

/**
 * Word wrapping helper shared by the terminal renderers.
 */
final class TextWrapper
{
	/**
	 * Wraps the given text so that no line is longer than $width.
	 *
	 * Existing line breaks are preserved and words are never split, so a
	 * single word longer than $width is returned as-is. Empty lines are
	 * dropped to keep the wrapped output predictable.
	 *
	 * @return list<string>
	 */
	public static function wrap(string $text, int $width): array
	{
		$width = max(1, $width);

		$wrapped = explode("\n", wordwrap($text, $width, "\n", false));

		return array_values(array_filter(
			$wrapped,
			static fn (string $line): bool => $line !== '',
		));
	}
}
