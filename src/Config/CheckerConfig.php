<?php

declare(strict_types=1);

namespace PhpChecker\Config;

final class CheckerConfig
{
	/**
	 * @param list<string> $paths
	 * @param list<string> $useRules
	 * @param list<string> $skipRules
	 * @param list<string> $excludePaths
	 * @param int|null $level PHPStan built-in rule level. `null` (the
	 *     default) runs only the configured custom rules; setting a level
	 *     explicitly opts into PHPStan's built-in analysis as well.
	 */
	public function __construct(
		private readonly array $paths = ['.'],
		private readonly array $useRules = [],
		private readonly array $skipRules = [],
		private readonly array $excludePaths = [
			'vendor',
			'node_modules',
			'tests',
		],
		private readonly ?int $level = null,
	) {}

	/**
	 * @return list<string>
	 */
	public function getPaths(): array
	{
		return $this->paths;
	}

	/**
	 * @return list<string>
	 */
	public function getUseRules(): array
	{
		return $this->useRules;
	}

	/**
	 * @return list<string>
	 */
	public function getSkipRules(): array
	{
		return $this->skipRules;
	}

	/**
	 * @return list<string>
	 */
	public function getExcludePaths(): array
	{
		return $this->excludePaths;
	}

	/**
	 * @return int|null `null` means PHPStan's built-in rules are disabled
	 *     and only the configured custom rules run.
	 */
	public function getLevel(): ?int
	{
		return $this->level;
	}

	public function shouldSkipRule(string $ruleId): bool
	{
		return in_array($ruleId, $this->skipRules, true);
	}

	public function shouldUseRule(string $ruleId): bool
	{
		if ($this->shouldSkipRule($ruleId)) {
			return false;
		}

		// An empty selection runs no custom rules. This lets users disable
		// every rule from the configuration without falling back to "all".
		return in_array($ruleId, $this->useRules, true);
	}
}
