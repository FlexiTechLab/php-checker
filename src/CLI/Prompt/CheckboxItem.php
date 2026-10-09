<?php

declare(strict_types=1);

namespace PhpChecker\CLI\Prompt;

/**
 * A single entry of an interactive {@see CheckboxList}.
 *
 * The identifier is what gets saved to the configuration, while the name
 * and description are shown to the user.
 */
final class CheckboxItem
{
	public function __construct(
		public readonly string $identifier,
		public readonly string $name,
		public readonly string $description = '',
	) {}
}
