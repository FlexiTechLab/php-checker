<?php

declare(strict_types=1);

namespace PhpChecker\Rules;

/**
 * Human-readable presentation data for a registered rule.
 *
 * The identifier is kept stable because it is persisted in
 * `php-checker.json`. The name and description exist purely to make the
 * `rules:configure` interface approachable, and are never used for
 * configuration.
 */
final class RuleMetadata
{
	public function __construct(
		public readonly string $identifier,
		public readonly string $name,
		public readonly string $description,
	) {}
}
